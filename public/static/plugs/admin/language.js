/* Administration language belongs to the common shell, never a module. */
(function ($) {
    'use strict';
    const selector = document.getElementById('admin-language');
    if (!selector) return;
    const buttons = Array.from(selector.querySelectorAll('button[data-locale]'));
    if (!buttons.length) return;
    const current = selector.dataset.current;
    const english = current === 'en-us';
    const text = (zh, en) => english ? en : zh;
    const baselines = new WeakMap();
    const forms = () => Array.from(document.querySelectorAll('form[data-auto]')).filter(form => !form.matches('.form-search'));
    const snapshot = form => JSON.stringify(Array.from(form.elements).filter(el => el.name && !el.disabled && el.name !== '_token_').map(el => [
        el.name, el.type === 'file' ? Array.from(el.files || []).map(file => [file.name, file.size, file.lastModified]) :
            el.type === 'checkbox' || el.type === 'radio' ? [el.value, el.checked] :
            el.multiple ? Array.from(el.selectedOptions).map(option => option.value) : el.value,
    ]));
    const remember = () => forms().forEach(form => {
        if (!baselines.has(form)) baselines.set(form, snapshot(form));
    });
    const dirty = () => forms().some(form => baselines.has(form) && baselines.get(form) !== snapshot(form));
    let changing = false;
    const unavailable = buttons.length < 2;
    const updateButtons = function () {
        selector.setAttribute('aria-busy', String(changing));
        buttons.forEach(button => { button.disabled = unavailable || changing; });
    };

    $.ajaxPrefilter(function (options, original, xhr) {
        if (new URL(options.url, location.href).origin === location.origin) {
            xhr.setRequestHeader('X-Admin-Language', current);
        }
    });
    $(document).ajaxError(function (event, xhr) {
        if (xhr.status === 409) $.msg.tips(text('后台语言已在其他页面更新，请刷新后再操作。', 'The administration language changed in another page. Refresh before continuing.'));
    });
    $(function () {
        remember();
        updateButtons();
        $('body').on('reInit.webbaseLanguage', remember);
    });
    // Editors synchronize form fields and may acknowledge successful saves.
    window.WebBaseAdminLanguage = Object.freeze({current, isDirty: dirty, markSaved: form => baselines.set(form, snapshot(form))});
    selector.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-locale]');
        if (!button || !selector.contains(button) || button.disabled) return;
        const next = button.dataset.locale;
        if (changing || unavailable || next === current) return;
        const change = function () {
            changing = true;
            updateButtons();
            $.ajax({url: selector.dataset.url, method: 'POST', dataType: 'json', data: {locale: next}}).done(function (result) {
                if (result.code === 1) {
                    // Preserve the current hash, filters and module selection.
                    location.reload();
                } else {
                    $.msg.tips(result.info || text('语言保存失败，请稍后重试。', 'Unable to save the language. Please retry.'));
                }
            }).fail(function (xhr) {
                $.msg.tips(xhr.responseJSON && xhr.responseJSON.info || text('语言保存失败，请稍后重试。', 'Unable to save the language. Please retry.'));
            }).always(function () { changing = false; updateButtons(); });
        };
        if (!dirty()) return change();
        layer.confirm(text('有尚未保存的修改。请继续编辑并保存，或放弃修改后切换语言。', 'You have unsaved changes. Continue editing and save, or discard changes to switch language.'), {
            title: text('切换全局语言', 'Switch administration language'),
            btn: [text('继续编辑', 'Continue editing'), text('放弃修改并切换', 'Discard and switch')],
            btn2: function (index) { layer.close(index); change(); },
        }, function (index) { layer.close(index); button.focus(); });
    });
    // The framework uses anchors without href for data-open actions.
    document.querySelector('.wb-global-language [data-open]')?.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }
    });
})(window.jQuery);
