define(['_weditor', 'upload', 'weditor-attachment'], function (editor, upload, attachmentModule) {
    window.wangEditor = editor;

    // 注册官方附件上传插件（只能在创建编辑器之前注册一次）
    if (attachmentModule && attachmentModule.default) {
        editor.Boot.registerModule(attachmentModule.default);
    }

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
                // 选中附件节点时弹出下载菜单
                hoverbarKeys: {
                    attachment: {
                        menuKeys: ['downloadAttachment'],
                    },
                },
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
                    },
                    // 附件上传配置：使用 AdminUploadAdapter 自定义上传
                    uploadAttachment: {
                        maxFileSize: 50 * 1024 * 1024, // 50MB
                        customUpload(file, insertFn) {
                            if (window.AdminUploadAdapter) {
                                new window.AdminUploadAdapter().upload([file], function (url) {
                                    insertFn(file.name, url);
                                });
                            } else {
                                $.msg.error('未找到文件上传组件，无法上传附件！');
                            }
                        },
                        onFailed(file, res) {
                            $.msg.error('附件上传失败：' + (res.message || '未知错误'));
                        },
                        onError(file, err) {
                            $.msg.error('附件上传异常：' + (err.message || '未知错误'));
                        },
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
        // 设置工具栏（插入附件上传按钮）
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
