# 后台 CRUD、回收站与富文本编辑器

## 模型约定

需要软删除和回收站的模型必须继承 `WebBase\Core\BaseModel`。数据表只允许使用一个删除字段：

```php
->addColumn('deleted_at', 'datetime', [
    'null' => true,
    'default' => null,
    'comment' => '删除时间',
])
```

不再使用 `deleted`、`is_deleted`、`delete_time` 或其他兼容字段。

## 控制器标准接口

```php
public function index()
{
    $this->type = input('type', 'index');
    $query = $this->type === 'recycle'
        ? Article::mRecycleQuery()
        : Article::mQuery();

    $query->layTable(null, function (QueryHelper $query) {
        $query->equal('status,category_id')
            ->like('title')
            ->dateBetween('create_at');
    });
}

public function remove()
{
    Article::mDelete();
}

public function restore()
{
    Article::mRestore();
}

public function destroy()
{
    Article::mForceDelete();
}
```

- `mQuery()` 自动查询 `deleted_at IS NULL`。
- `mRecycleQuery()` 只查询 `deleted_at IS NOT NULL`。
- `mAllQuery()` 同时查询正常和已删除数据，主要用于唯一性校验。
- `mDelete()` 只负责移入回收站。
- `mRestore()` 只恢复回收站记录。
- `mForceDelete()` 只允许永久删除回收站记录。

列表模板应明确区分正常列表和回收站。正常列表显示新增、编辑、状态、软删除操作；回收站只显示恢复和永久删除操作。参考 `用户管理`、`数据字典` 和 `栏目管理` 页面。

## 富文本编辑器与附件

表单统一通过系统编辑器入口加载，不直接依赖某一种编辑器：

```html
<textarea id="editorContent" name="content" class="layui-textarea">{$vo.content|default=''}</textarea>

<script>
require(['ckeditor'], function () {
    if (typeof window.createEditor === 'function') {
        window.createEditor('#editorContent', {
            height: 500,
            maxAttachmentSize: 50 * 1024 * 1024
        });
    }
});
</script>
```

选择 `wangEditor` 后，工具栏会显示“上传附件”，附件复用后台 `AdminUploadAdapter`，后缀白名单仍由 `storage.allow_exts` 控制。可执行文件后缀始终被服务端拒绝。
