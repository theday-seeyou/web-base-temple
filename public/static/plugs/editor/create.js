define(['_weditor', 'upload', 'weditor-attachment'], function (editor, upload, attachmentModule) {
    window.wangEditor = editor;

    // wangEditor 的模块注册是全局的，弹窗重复打开时可能再次执行此模块。
    try {
        if (attachmentModule && attachmentModule.default) {
            editor.Boot.registerModule(attachmentModule.default);
        }
    } catch (error) {
        // 已注册时无需中断编辑器初始化。
    }

    window.createEditor = function (selector, option) {
        var $element = $(selector);
        if (!$element.length) {
            return null;
        }

        var current = $element.data('editorInstance');
        var currentLayout = $element.data('editorLayout');
        if (current && currentLayout && document.body.contains(currentLayout.get(0))) {
            return current;
        }

        if (current && typeof current.destroy === 'function') {
            try {
                current.destroy();
            } catch (error) {
                // 忽略已销毁的实例。
            }
        }
        if (currentLayout) {
            currentLayout.remove();
        }
        $element.removeData('editorInstance').removeData('editorLayout').show();

        if (!editor || typeof editor.createEditor !== 'function' || typeof editor.createToolbar !== 'function') {
            $.msg.error('富文本编辑器加载失败，请刷新页面后重试！');
            return null;
        }

        var config = option || {};
        var $layout = $('<div class="webbase-wangeditor" style="border:1px solid #ccc;z-index:1001;"><div style="border-bottom:1px solid #ccc;"></div><div style="height:400px;"></div></div>');
        $element.hide().data('editorLayout', $layout).after($layout);

        var instance;
        try {
            instance = editor.createEditor({
                html: '<p><br></p>',
                selector: $layout.find('div:last').get(0),
                config: {
                    height: config.height || 500,
                    hoverbarKeys: {
                        attachment: {menuKeys: ['downloadAttachment']}
                    },
                    MENU_CONF: {
                        uploadImage: {
                            async customUpload(file, insertFn) {
                                if (window.AdminUploadAdapter) {
                                    new window.AdminUploadAdapter().upload([file], function (url) {
                                        insertFn(url, file.name);
                                    });
                                } else {
                                    var reader = new window.FileReader();
                                    reader.addEventListener('load', function () {
                                        insertFn(reader.result, file.name);
                                    });
                                    reader.readAsDataURL(file);
                                }
                            }
                        },
                        uploadVideo: {
                            async customUpload(file, insertFn) {
                                if (window.AdminUploadAdapter) {
                                    new window.AdminUploadAdapter().upload([file], function (url) {
                                        insertFn(url, file.name);
                                    });
                                } else {
                                    var reader = new window.FileReader();
                                    reader.addEventListener('load', function () {
                                        insertFn(reader.result, file.name);
                                    });
                                    reader.readAsDataURL(file);
                                }
                            }
                        },
                        uploadAttachment: {
                            maxFileSize: config.maxAttachmentSize || 50 * 1024 * 1024,
                            customUpload: function (file, insertFn) {
                                if (!window.AdminUploadAdapter) {
                                    $.msg.error('未找到文件上传组件，无法上传附件！');
                                    return;
                                }
                                new window.AdminUploadAdapter().upload([file], function (url) {
                                    insertFn(file.name, url);
                                });
                            },
                            onFailed: function (file, response) {
                                $.msg.error('附件上传失败：' + ((response || {}).message || '未知错误'));
                            },
                            onError: function (file, error) {
                                $.msg.error('附件上传异常：' + ((error || {}).message || '未知错误'));
                            }
                        }
                    },
                    placeholder: config.placeholder || '请输入内容...',
                    onCreated: function (createdEditor) {
                        createdEditor.setHtml($element.val() || '<p><br></p>');
                    },
                    onChange: function (changedEditor) {
                        $element.val(changedEditor.getHtml()).trigger('change');
                    }
                },
                mode: config.mode || 'default'
            });

            editor.createToolbar({
                editor: instance,
                selector: $layout.find('div:first').get(0),
                config: {
                    excludeKeys: ['fullScreen'],
                    insertKeys: {index: 20, keys: ['uploadAttachment']}
                },
                mode: config.mode || 'default'
            });
        } catch (error) {
            $layout.remove();
            $element.removeData('editorLayout').show();
            $.msg.error('富文本编辑器创建失败：' + (error.message || '未知错误'));
            return null;
        }

        instance.getData = function () {
            return instance.getHtml();
        };
        instance.setData = function (html) {
            return instance.setHtml(html);
        };
        $element.data('editorInstance', instance);
        return instance;
    };
});
