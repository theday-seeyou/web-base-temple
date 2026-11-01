# 后台 CRUD、回收站与富文本编辑器

## 模型约定

需要软删除和回收站的模型必须继承 `WebBase\Core\BaseModel`，自动写入 `created_at`、
`updated_at`，并以 `deleted_at` 作为唯一删除字段。在继承
`WebBase\Core\migration\Migrator` 的迁移中创建表：

```php
$this->createTable('articles')
    ->addColumn('title', 'string', ['limit' => 200])
    ->create();
```

自动包含自增主键 `id` 和三个 DATETIME 时间字段；`deleted_at` 可空且默认 NULL，
创建、更新时间非空。不再使用 `deleted`、`is_deleted`、`delete_time` 或其他兼容字段。

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
            ->dateBetween('created_at');
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

列表模板应明确区分正常列表和回收站。正常列表显示新增、编辑、状态、软删除操作；回收站只显示恢复和永久删除操作。

## 默认后台的删除行为

| 模块 | 默认删除行为 | 回收站与恢复 | 永久删除 |
| --- | --- | --- | --- |
| 用户 | `deleted_at` 软删除 | 有；超级管理员受保护 | 仅回收站 |
| 角色 | `deleted_at` 软删除 | 有；保留授权节点，删除期间权限立即失效 | 仅回收站；同时删除该角色授权节点 |
| 菜单 | `deleted_at` 软删除 | 有；禁用仍在正常列表；父子关系校验 | 仅回收站；校验子树 |
| 数据字典 | `deleted_at` 软删除 | 有；唯一性检查包含回收站 | 仅回收站 |
| 附件记录 | `deleted_at` 软删除 | 有；普通管理员只能操作自己的记录 | 仅回收站；保留物理文件 |
| 分类示例 | `deleted_at` 软删除 | 有；父子关系校验 | 仅回收站 |
| 系统任务、操作日志 | 显式物理清理 | 无 | 维护操作 |
| 配置、内部数据、授权关联表 | 内部显式更新/清理 | 无通用回收站界面 | 由相应服务管理 |

附件页用 `scope=index/recycle` 区分列表范围，`type=local/alioss/qiniu/txcos` 仍表示存储方式；
字典页也用 `scope` 区分范围，避免与业务 `type` 冲突。其他列表使用 `type=index/recycle`。
清理重复附件按当前管理员的存储方式与文件路径分组，仅将较早的公开附件记录软删除，
不影响他人的记录、安全文件或实际文件。

后台用户序列化默认隐藏密码哈希。角色软删除/恢复会在后续请求重新计算用户权限，
不要求用户重新登录。混合提交正常与回收站角色 ID 时，只永久删除回收站角色及其节点。

维护操作和删除、恢复接口必须使用 POST，并通过 CSRF 和节点权限检查。

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
