define(['_weditor', 'upload'], function (editor) {
    window.wangEditor = editor;

    // ========== 注册附件上传自定义菜单 ==========
    var Boot = editor.Boot;

    // 附件上传菜单类
    function UploadAttachmentMenu() {
        this.title = '上传附件';
        this.iconSvg = '<svg viewBox="0 0 1024 1024"><path d="M563.2 597.333333V277.333333a51.2 51.2 0 0 0-102.4 0v320l-108.544-108.544a51.2 51.2 0 0 0-72.362667 72.362667l196.266667 196.266667a51.2 51.2 0 0 0 72.362667 0l196.266666-196.266667a51.2 51.2 0 0 0-72.362666-72.362667L563.2 597.333333z" fill="currentColor"/><path d="M870.4 716.8v102.4a51.2 51.2 0 0 1-51.2 51.2H204.8a51.2 51.2 0 0 1-51.2-51.2v-102.4a51.2 51.2 0 0 0-102.4 0v102.4a153.6 153.6 0 0 0 153.6 153.6h614.4a153.6 153.6 0 0 0 153.6-153.6v-102.4a51.2 51.2 0 0 0-102.4 0z" fill="currentColor"/></svg>';
        this.tag = 'button';
    }

    UploadAttachmentMenu.prototype.getValue = function (editor) {
        return '';
    };

    UploadAttachmentMenu.prototype.isActive = function (editor) {
        return false;
    };

    UploadAttachmentMenu.prototype.isDisabled = function (editor) {
        return editor.isDisabled();
    };

    UploadAttachmentMenu.prototype.exec = function (edt, value) {
        if (edt.isDisabled()) return;

        var input = document.createElement('input');
        input.type = 'file';
        input.accept = '.doc,.docx,.pdf,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.tar,.gz,.txt,.csv,.md,.json,.xml';
        input.style.display = 'none';
        document.body.appendChild(input);

        input.onchange = function (e) {
            var files = e.target.files;
            if (!files || files.length === 0) return;

            var file = files[0];
            var fileName = file.name;

            // 使用 AdminUploadAdapter 上传
            if (window.AdminUploadAdapter) {
                new window.AdminUploadAdapter().upload([file], function (url) {
                    edt.restoreSelection();
                    edt.dangerouslyInsertHtml(
                        '<a href="' + url + '" target="_blank" download="' + fileName + '" style="color:#5b95e8;text-decoration:underline;">📎 ' + fileName + '</a>'
                    );
                });
            } else {
                // 无上传适配器时，回退为 base64（仅小文件适用）
                var reader = new FileReader();
                reader.addEventListener('load', function () {
                    edt.restoreSelection();
                    edt.dangerouslyInsertHtml(
                        '<a href="' + reader.result + '" target="_blank" download="' + fileName + '" style="color:#5b95e8;text-decoration:underline;">📎 ' + fileName + '</a>'
                    );
                });
                reader.readAsDataURL(file);
            }

            document.body.removeChild(input);
        };

        input.click();
    };

    // 注册附件上传菜单
    Boot.registerMenu({
        key: 'uploadAttachment',
        factory: function () {
            return new UploadAttachmentMenu();
        }
    });

    // ========== 创建编辑器 ==========
    window.createEditor = function (ele, option) {
        if ($(ele).data('editorLayout')) return;
        const $layout = $('<div style="border:1px solid #ccc;z-index:1001;"><div style="border-bottom:1px solid #ccc;"></div><div style="height:400px;"></div></div>');
        $(ele).hide().data('editorLayout', $layout).after($layout).parent();
        // 创建编辑器
        const _editor = editor.createEditor({
            html: '<p><br></p>',
            selector: $layout.find("div:last").get(0),
            config: {
                height: (option || {}).height || 500,
                MENU_CONF: {
                    uploadImage: {
                        async customUpload(file, insertFn) {
                            if (window.AdminUploadAdapter) {
                                new window.AdminUploadAdapter().upload([file], url => insertFn(url, file.name))
                            } else {
                                let reader = new window.FileReader();
                                reader.addEventListener('load', () => insertFn(reader.result, file.name));
                                reader.readAsDataURL(file);
                            }
                        }
                    },
                    uploadVideo: {
                        async customUpload(file, insertFn) {
                            if (window.AdminUploadAdapter) {
                                new window.AdminUploadAdapter().upload([file], url => insertFn(url, file.name))
                            } else {
                                let reader = new window.FileReader();
                                reader.addEventListener('load', () => insertFn(reader.result, file.name));
                                reader.readAsDataURL(file);
                            }
                        }
                    }
                },
                placeholder: 'Type here...',
                onCreated(editor) {
                    editor.setHtml($(ele).val());
                },
                onChange(editor) {
                    $(ele).val(editor.getHtml())
                }
            },
            mode: 'default', // or 'simple'
        })
        // 设置工具栏（增加附件上传按钮）
        editor.createToolbar({
            editor: _editor,
            selector: $layout.find('div:first').get(0),
            config: {
                excludeKeys: ['fullScreen'],
                insertKeys: {
                    index: 20,
                    keys: ['uploadAttachment']
                }
            },
            mode: 'default',
        })
        // 兼容其他版本
        _editor.getData = function () {
            return _editor.getHtml()
        }
        _editor.setData = function (html) {
            return _editor.setHtml(html)
        }

        return _editor;
    }
});
