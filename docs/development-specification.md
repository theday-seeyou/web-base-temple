# WebBase CMS 开发规范文档

> 本文档定义了基于 WebBase CMS（ThinkPHP 6/8）基础模板开发新功能模块时必须遵守的规范和约定。

## 1. 概述

### 1.1 文档目的

本文档为开发者提供一套统一的开发标准，确保：
- 代码风格一致，便于团队协作和代码维护
- 新模块与现有系统无缝集成
- 遵循框架最佳实践，避免常见错误

### 1.2 适用范围

适用于基于本基础模板开发的所有后台管理模块，包括但不限于：
- 内容管理（CMS）
- SEO 管理
- 广告管理
- 多语言支持
- 其他业务功能模块

### 1.3 与 CLAUDE.md 的关系

| 文档 | 目标读者 | 侧重点 |
|------|----------|--------|
| `CLAUDE.md` | Claude Code AI | 操作指令、快速参考、命令速查 |
| 本文档 | 人类开发者 | 规范解释、设计原则、完整流程 |

两份文档互补，如有冲突以本文档为准。

---

## 2. 目录结构规范

### 2.1 新模块目录布局

每个功能模块应遵循以下目录结构：

```
app/admin/
├── controller/
│   └── {module}/           # 模块控制器目录（如 cms/、seo/、ad/）
│       ├── {Entity}.php    # 主控制器（如 Article.php）
│       └── ...
├── model/                  # 数据模型（可选，如需自定义模型）
│   └── {Entity}.php
├── view/
│   └── {module}/           # 模块模板目录
│       └── {entity}/       # 实体模板目录（小写下划线）
│           ├── index.html        # 列表页
│           ├── index_search.html # 搜索表单
│           ├── form.html         # 编辑表单
│           └── ...
└── Service.php             # 菜单注册（修改此文件）
```

### 2.2 命名空间规则

| 类型 | 命名空间 | 示例 |
|------|----------|------|
| 控制器 | `app\admin\controller\{module}` | `app\admin\controller\cms\Article` |
| 模型 | `app\admin\model` 或 `app\{app}\model` | `app\admin\model\Article` |
| 服务类 | `app\admin\service` | `app\admin\service\ArticleService` |

### 2.3 文件命名约定

| 类型 | 命名规则 | 示例 |
|------|----------|------|
| 控制器 | 大驼峰（PascalCase） | `Article.php`、`FriendLink.php` |
| 模型 | 大驼峰（PascalCase） | `CmsArticle.php`（建议加模块前缀） |
| 模板目录 | 小写下划线 | `cms/article/` |
| 模板文件 | 小写下划线 | `index.html`、`index_search.html` |

---

## 3. 数据库设计规范

### 3.1 表命名规则

| 规则 | 说明 | 示例 |
|------|------|------|
| 前缀 | 使用模块缩写作为前缀，避免冲突 | `cms_article`、`ad_position` |
| 格式 | 小写下划线 | `friend_link`、`seo_meta` |
| 多对多中间表 | `{主表}_{关联表}` | `article_tag` |

### 3.2 必备字段

每个业务表**必须**包含以下通用字段：

```sql
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
`create_at` datetime DEFAULT NULL COMMENT '创建时间',
`update_at` datetime DEFAULT NULL COMMENT '更新时间',
PRIMARY KEY (`id`)
```

支持软删除的表**必须**添加：

```sql
`deleted` tinyint(1) UNSIGNED DEFAULT 0 COMMENT '删除状态(0正常,1已删)',
```

支持状态切换的表**应该**添加：

```sql
`status` tinyint(1) DEFAULT 1 COMMENT '状态(0禁用,1启用)',
`sort` int(11) DEFAULT 0 COMMENT '排序权重',
```

### 3.3 字段类型约定

| 用途 | 推荐类型 | 说明 |
|------|----------|------|
| 主键 | `int(11) UNSIGNED AUTO_INCREMENT` | 无符号自增 |
| 状态/开关 | `tinyint(1)` | 0/1 布尔值 |
| 排序 | `int(11)` | 数值越大越靠前 |
| 标题/名称 | `varchar(200)` | 根据实际需求调整长度 |
| 封面图/链接 | `varchar(500)` | 支持长 URL |
| 富文本内容 | `longtext` | 支持大段 HTML |
| 时间戳 | `datetime` | 格式 `Y-m-d H:i:s` |
| 金额 | `decimal(10,2)` | 精确计算 |

### 3.4 索引规则

- **必须**为查询条件字段添加索引（如 `status`、`deleted`、`cate_id`）
- **必须**为唯一约束字段添加唯一索引（如 `code`、`short`）
- 联合索引遵循最左前缀原则

### 3.5 迁移文件规范

迁移文件位于 `database/migrations/`，命名格式：

```
{YYYYMMDD}{序号}_{描述}.php
```

示例：`20260604000001_cms_tables.php`

**迁移文件模板**：

```php
<?php

declare(strict_types=1);

use think\migration\db\Column;
use think\migration\Migrator;

/**
 * CMS 内容管理数据表迁移
 */
class CmsTables extends Migrator
{
    /**
     * 创建数据表
     */
    public function change(): void
    {
        // 文章表
        $this->table('cms_article', [
            'engine' => 'InnoDB',
            'comment' => 'CMS文章表',
        ])
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed' => false,
                'comment' => '主键ID',
            ])
            ->addColumn('cate_id', 'integer', [
                'default' => 0,
                'comment' => '分类ID',
            ])
            ->addColumn('title', 'string', [
                'limit' => 200,
                'comment' => '文章标题',
            ])
            ->addColumn('status', 'boolean', [
                'default' => 1,
                'comment' => '状态(0禁用,1启用)',
            ])
            ->addColumn('sort', 'integer', [
                'default' => 0,
                'comment' => '排序权重',
            ])
            ->addColumn('deleted', 'boolean', [
                'default' => 0,
                'comment' => '删除状态',
            ])
            ->addColumn('create_at', 'datetime', [
                'null' => true,
                'comment' => '创建时间',
            ])
            ->addColumn('update_at', 'datetime', [
                'null' => true,
                'comment' => '更新时间',
            ])
            ->addIndex(['status', 'deleted'], ['name' => 'idx_status_deleted'])
            ->addIndex(['cate_id'], ['name' => 'idx_cate_id'])
            ->create();
    }
}
```

**执行迁移**：

```bash
php think migrate:run
```

---

## 4. 模型规范

### 4.1 模型类结构

模型**必须**继承 `WebBase\Core\Model`，并添加 `@property` 注解：

```php
<?php

declare(strict_types=1);

namespace app\admin\model;

use WebBase\Core\Model;

/**
 * CMS文章模型.
 *
 * @property int $id 主键ID
 * @property int $cate_id 分类ID
 * @property string $title 文章标题
 * @property string $cover 封面图片
 * @property string $content 文章内容
 * @property int $status 状态(0禁用,1启用)
 * @property int $sort 排序权重
 * @property int $deleted 删除状态
 * @property string $create_at 创建时间
 * @property string $update_at 更新时间
 * @class CmsArticle
 */
class CmsArticle extends Model
{
    /**
     * 时间字段配置
     */
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

    /**
     * 软删除字段
     */
    protected $deleteTime = 'deleted';
    protected $defaultSoftDelete = 0;
}
```

### 4.2 访问器定义

访问器用于格式化输出，命名规则：`get{Field}Attr`：

```php
/**
 * 状态文本访问器
 */
public function getStatusTextAttr($value, array $data): string
{
    $status = [0 => '禁用', 1 => '启用'];
    return $status[$data['status']] ?? '未知';
}

/**
 * 创建时间格式化
 */
public function getCreateAtAttr($value): string
{
    return $value ? date('Y-m-d H:i', strtotime($value)) : '-';
}
```

### 4.3 模型关联

```php
/**
 * 所属分类
 */
public function category(): BelongsTo
{
    return $this->belongsTo(CmsCategory::class, 'cate_id', 'id');
}
```

---

## 5. 控制器规范

### 5.1 控制器类结构

控制器**必须**继承 `WebBase\Core\Controller`，**必须**添加 `declare(strict_types=1)`：

```php
<?php

declare(strict_types=1);

namespace app\admin\controller\cms;

use WebBase\Core\Controller;
use WebBase\Core\helper\QueryHelper;
use WebBase\Core\model\CmsArticle;

/**
 * CMS文章管理.
 * @class Article
 */
class Article extends Controller
{
    // 控制器方法...
}
```

### 5.2 RBAC 权限注解

每个动作方法**必须**添加权限注解：

```php
/**
 * 文章列表.
 * @auth true    需要权限验证
 * @menu true    显示在侧边栏菜单（仅 index 方法需要）
 */
public function index()
{
    // ...
}

/**
 * 添加文章.
 * @auth true
 */
public function add()
{
    // ...
}

/**
 * 编辑文章.
 * @auth true
 */
public function edit()
{
    // ...
}

/**
 * 修改文章状态.
 * @auth true
 */
public function state()
{
    // ...
}

/**
 * 删除文章.
 * @auth true
 */
public function remove()
{
    // ...
}
```

**注解说明**：

| 注解 | 值 | 说明 |
|------|-----|------|
| `@auth` | `true`/`false` | 是否需要权限验证 |
| `@menu` | `true`/`false` | 是否显示在侧边栏菜单 |
| `@login` | `true`/`false` | 是否需要登录 |

### 5.3 标准 CRUD 五动作

每个业务控制器**应该**实现以下五个标准动作：

#### 5.3.1 index（列表）

```php
/**
 * 文章列表.
 * @auth true @menu true
 */
public function index()
{
    $this->type = input('type', 'index');
    CmsArticle::mQuery()->layTable(function () {
        $this->title = '文章管理';
    }, function (QueryHelper $query) {
        // 数据过滤条件
        $query->where(['deleted' => 0]);
        $query->equal('status,cate_id')->like('title')->dateBetween('create_at');
    });
}
```

#### 5.3.2 add（新增）

```php
/**
 * 添加文章.
 * @auth true
 */
public function add()
{
    CmsArticle::mForm('form');
}
```

#### 5.3.3 edit（编辑）

```php
/**
 * 编辑文章.
 * @auth true
 */
public function edit()
{
    CmsArticle::mForm('form');
}
```

#### 5.3.4 state（状态切换）

```php
/**
 * 修改文章状态.
 * @auth true
 */
public function state()
{
    CmsArticle::mSave($this->_vali([
        'status.in:0,1' => '状态值范围异常！',
        'status.require' => '状态值不能为空！',
    ]));
}
```

#### 5.3.5 remove（删除）

```php
/**
 * 删除文章.
 * @auth true
 */
public function remove()
{
    CmsArticle::mDelete();
}
```

### 5.4 钩子方法

控制器可通过命名约定的钩子方法处理数据：

```php
/**
 * 表单数据处理（GET 加载 / POST 校验）.
 */
protected function _form_filter(array &$data)
{
    if ($this->request->isPost()) {
        // POST 时的数据校验和处理
        if (empty($data['title'])) {
            $this->error('标题不能为空！');
        }
    } else {
        // GET 时的数据准备
        $this->categories = CmsCategory::where(['status' => 1])->select();
    }
}

/**
 * 表单处理结果回调.
 */
protected function _form_result(bool $status, array $data)
{
    if ($status) {
        sysoplog('文章管理', "保存文章[{$data['id']}]成功");
    }
}

/**
 * 列表数据处理回调.
 */
protected function _index_page_filter(array &$data, array &$result)
{
    foreach ($data as &$item) {
        $item['category_name'] = $item['category']['name'] ?? '未分类';
    }
}
```

### 5.5 验证规则

使用 `$this->_vali()` 定义验证规则：

```php
$this->_vali([
    'title.require' => '标题不能为空！',
    'title.max:200' => '标题不能超过200字符！',
    'cate_id.require' => '请选择分类！',
    'status.in:0,1' => '状态值异常！',
]);
```

**常用验证规则**：

| 规则 | 说明 |
|------|------|
| `require` | 必填 |
| `in:a,b,c` | 在指定范围内 |
| `max:n` | 最大长度 |
| `min:n` | 最小长度 |
| `email` | 邮箱格式 |
| `phone` | 手机号格式 |
| `confirm:field` | 与另一字段相同 |

### 5.6 操作日志

重要操作**应该**记录日志：

```php
sysoplog('模块名称', '操作描述');
```

示例：

```php
sysoplog('文章管理', "删除文章[{$id}]成功");
sysoplog('用户管理', "修改用户[{$id}]密码");
```

---

## 6. 模板规范

### 6.1 布局选择规则

| 场景 | 布局模板 | 说明 |
|------|----------|------|
| 数据列表页 | `{extend name='table'}` | 包含搜索区 + 表格区 |
| 表单编辑页 | 无需继承（弹窗模式） | 直接使用 `<form>` 标签 |
| 独立页面 | `{extend name='main'}` | 内容卡片布局 |
| 登录/全屏页 | `{extend name='full'}` | 无后台框架 |

### 6.2 列表页模板（index.html）

```html
{extend name='table'}

{block name="button"}
<!--{if auth("add")}-->
<button data-modal='{:url("add")}' class='layui-btn layui-btn-sm layui-btn-primary'>添加</button>
<!--{/if}-->
<!--{if auth("state")}-->
<a data-action="{:url('state')}" data-rule="id#{id};status#0" data-confirm="确定要禁用吗？" data-table-id="ArticleTable" class='layui-btn layui-btn-sm layui-btn-primary'>批量禁用</a>
<!--{/if}-->
{/block}

{block name="content"}
<div class="layui-tab layui-tab-card">
    <ul class="layui-tab-title">
        <li data-open="{:url('index')}?type=index" class="layui-this">正常列表</li>
        <li data-open="{:url('index')}?type=recycle">回收站</li>
    </ul>
    <div class="layui-tab-content">
        {include file='article/index_search'}
        <table id="ArticleTable" data-url="{:sysuri()}" data-target-search="form.form-search"></table>
    </div>
</div>
{/block}

{block name='script'}
<script>
$(function () {
    $('#ArticleTable').layTable({
        even: true,
        height: 'full',
        sort: {field: 'sort desc,id', type: 'desc'},
        where: {type: '{$type|default="index"}'},
        cols: [[
            {checkbox: true, fixed: true},
            {field: 'sort', title: '排序', width: 80, sort: true, templet: '#SortInputTpl'},
            {field: 'title', title: '标题', minWidth: 200},
            {field: 'status', title: '状态', width: 100, templet: '#StatusSwitchTpl'},
            {field: 'create_at', title: '创建时间', width: 170, sort: true},
            {toolbar: '#toolbar', title: '操作', fixed: 'right', width: 160}
        ]]
    });

    // 状态切换事件
    layui.form.on('switch(StatusSwitch)', function (obj) {
        $.form.load("{:url('state')}", {id: obj.value, status: obj.elem.checked ? 1 : 0}, 'post');
    });
});
</script>

<script type="text/html" id="StatusSwitchTpl">
<!--{if auth("state")}-->
<input type="checkbox" value="{{d.id}}" lay-skin="switch" lay-text="启用|禁用" lay-filter="StatusSwitch" {{-d.status>0?'checked':''}}>
<!--{else}-->
{{-d.status ? '<b class="color-green">启用</b>' : '<b class="color-red">禁用</b>'}}
<!--{/if}-->
</script>

<script type="text/html" id="SortInputTpl">
<input type="number" min="0" data-blur-number="0" data-action-blur="{:sysuri()}" data-value="id#{{d.id}};action#sort;sort#{value}" data-loading="false" value="{{d.sort}}" class="layui-input text-center">
</script>

<script type="text/html" id="toolbar">
<!--{if auth("edit")}-->
<a class="layui-btn layui-btn-sm" data-event-dbclick data-modal='{:url("edit")}?id={{d.id}}'>编辑</a>
<!--{/if}-->
<!--{if auth("remove")}-->
<a class="layui-btn layui-btn-sm layui-btn-danger" data-confirm="确定要删除吗？" data-action="{:url('remove')}" data-value="id#{{d.id}}">删除</a>
<!--{/if}-->
</script>
{/block}
```

### 6.3 搜索表单模板（index_search.html）

```html
<form class="layui-form layui-form-pane form-search" action="{:sysuri()}" onsubmit="return false" method="get" autocomplete="off">

    <div class="layui-form-item layui-inline">
        <label class="layui-form-label">标题</label>
        <label class="layui-input-inline">
            <input name="title" value="{$get.title|default=''}" placeholder="请输入标题" class="layui-input">
        </label>
    </div>

    <div class="layui-form-item layui-inline">
        <label class="layui-form-label">状态</label>
        <div class="layui-input-inline">
            <select name="status" class="layui-select">
                <option value="">-- 全部 --</option>
                <option value="1" {if condition="input('get.status') eq '1'"}selected{/if}>启用</option>
                <option value="0" {if condition="input('get.status') eq '0'"}selected{/if}>禁用</option>
            </select>
        </div>
    </div>

    <div class="layui-form-item layui-inline">
        <label class="layui-form-label">创建时间</label>
        <div class="layui-input-inline">
            <input data-date-range name="create_at" value="{$get.create_at|default=''}" placeholder="请选择时间" class="layui-input">
        </div>
    </div>

    <div class="layui-form-item layui-inline">
        <input type="hidden" name="type" value="{$type|default='index'}">
        <button class="layui-btn layui-btn-primary"><i class="layui-icon">&#xe615;</i> 搜 索</button>
    </div>
</form>
```

### 6.4 表单模板（form.html）

表单页面**不继承布局**，直接使用 `<form>` 标签（弹窗模式）：

```html
<form action="{:sysuri()}" method="post" data-auto="true" class="layui-form layui-card" data-table-id="ArticleTable">

    <div class="layui-card-body padding-left-40">
        <label class="layui-form-item relative block">
            <span class="help-label"><b>文章标题</b>Title</span>
            <input name="title" value='{$vo.title|default=""}' required placeholder="请输入标题" class="layui-input">
            <span class="help-block">请输入文章标题</span>
        </label>

        <label class="layui-form-item relative block">
            <span class="help-label"><b>所属分类</b>Category</span>
            <select name="cate_id" class="layui-select" required>
                <option value="">-- 请选择分类 --</option>
                {foreach $categories as $cate}
                <option value="{$cate.id}" {if condition="isset($vo.cate_id) && $vo.cate_id eq $cate.id"}selected{/if}>{$cate.name}</option>
                {/foreach}
            </select>
        </label>

        <div class="layui-form-item relative block">
            <span class="help-label"><b>封面图片</b>Cover</span>
            <div>
                <input name="cover" value='{$vo.cover|default=""}' placeholder="请上传封面" class="layui-input" style="width:300px;display:inline-block;">
                <script>$('[name="cover"]').uploadOneImage();</script>
            </div>
        </div>

        <label class="layui-form-item relative block">
            <span class="help-label"><b>文章内容</b>Content</span>
            <textarea name="content" id="editor" class="layui-textarea" style="height:300px;">{$vo.content|default=''|raw}</textarea>
            <script>createEditor('#editor');</script>
        </label>

        <label class="layui-form-item relative block">
            <span class="help-label"><b>状态</b>Status</span>
            <input name="status" type="radio" value="1" title="启用" {if condition="!isset($vo.status) || $vo.status eq 1"}checked{/if}>
            <input name="status" type="radio" value="0" title="禁用" {if condition="isset($vo.status) && $vo.status eq 0"}checked{/if}>
        </label>
    </div>

    <div class="hr-line-dashed"></div>

    {notempty name='vo.id'}<input type='hidden' value='{$vo.id}' name='id'>{/notempty}

    <div class="layui-form-item text-center">
        <button class="layui-btn" type='submit'>保存数据</button>
        <button class="layui-btn layui-btn-danger" type='button' data-confirm="确定要取消编辑吗？" data-close>取消编辑</button>
    </div>
</form>
```

### 6.5 模板语法要点

**权限判断**：**必须**使用 HTML 注释语法：

```html
<!--{if auth("action")}-->
<button>有权限才显示</button>
<!--{/if}-->
```

**两种模板语法共存**：

| 语法 | 使用场景 | 示例 |
|------|----------|------|
| ThinkPHP `{}` | 服务端渲染的 HTML | `{$title}`、`{if $status eq 1}` |
| LayUI `{{}}` | 客户端 layTable 模板 | `{{d.id}}`、`{{-d.status ? '是' : '否'}}` |

---

## 7. 前端交互规范

### 7.1 data-* 属性速查

| 属性 | 用途 | 示例 |
|------|------|------|
| `data-modal` | 弹窗打开页面 | `data-modal='{:url("add")}'` |
| `data-open` | 当前页跳转 | `data-open='{:url("index")}'` |
| `data-action` | AJAX 提交（配合选中行） | `data-action="{:url('state')}"` |
| `data-value` | 手动指定参数 | `data-value="id#{{d.id}}"` |
| `data-rule` | 从表格提取参数 | `data-rule="id#{id};status#0"` |
| `data-confirm` | 操作前确认 | `data-confirm="确定删除吗？"` |
| `data-table-id` | 关联表格 ID | `data-table-id="ArticleTable"` |
| `data-close` | 关闭弹窗 | `data-close` |
| `data-auto` | 表单自动提交 | `data-auto="true"` |
| `data-title` | 弹窗标题 | `data-title="编辑文章"` |
| `data-event-dbclick` | 双击行触发 | 配合 `data-modal` |

### 7.2 layTable 配置约定

```javascript
$('#TableId').layTable({
    even: true,                    // 隔行背景
    height: 'full',                // 自适应高度
    sort: {field: 'sort desc,id', type: 'desc'},  // 默认排序
    where: {type: 'index'},        // 额外参数
    page: true,                    // 分页（树形列表用 false）
    cols: [[
        {checkbox: true, fixed: true},
        {field: 'id', title: 'ID', width: 80, sort: true},
        {field: 'name', title: '名称', minWidth: 100},
        {field: 'status', title: '状态', templet: '#StatusTpl'},
        {toolbar: '#toolbar', title: '操作', fixed: 'right', width: 160}
    ]]
});
```

### 7.3 表单提交模式

表单**必须**使用 `data-auto="true"` 启用 AJAX 提交：

```html
<form action="{:sysuri()}" method="post" data-auto="true" data-table-id="TableId">
```

提交成功后自动刷新关联表格并关闭弹窗。

### 7.4 状态切换模式

```html
<!-- 模板 -->
<script type="text/html" id="StatusSwitchTpl">
<!--{if auth("state")}-->
<input type="checkbox" value="{{d.id}}" lay-skin="switch" lay-text="启用|禁用" lay-filter="StatusSwitch" {{-d.status>0?'checked':''}}>
<!--{/if}-->
</script>

<!-- 事件处理 -->
<script>
layui.form.on('switch(StatusSwitch)', function (obj) {
    $.form.load("{:url('state')}", {id: obj.value, status: obj.elem.checked ? 1 : 0}, 'post');
});
</script>
```

---

## 8. 菜单与权限规范

### 8.1 菜单注册格式

在 `app/admin/Service.php` 的 `menu()` 方法中添加菜单：

```php
public static function menu(): array
{
    return [
        // 现有菜单...
        [
            'name' => '内容管理',
            'subs' => [
                ['name' => '文章管理', 'icon' => 'layui-icon layui-icon-form', 'node' => 'admin/cms/article/index'],
                ['name' => '分类管理', 'icon' => 'layui-icon layui-icon-app', 'node' => 'admin/cms/category/index'],
            ],
        ],
    ];
}
```

### 8.2 菜单项结构

```php
[
    'name' => '显示名称',           // 必填
    'icon' => 'layui-icon xxx',    // 可选，LayUI 图标类
    'node' => 'app/controller/action', // 必填，权限节点
]
```

### 8.3 权限节点命名规则

权限节点格式：`{应用}/{控制器}/{动作}`

| 示例 | 说明 |
|------|------|
| `admin/user/index` | admin 应用的 User 控制器 index 动作 |
| `admin/cms/article/add` | admin 应用的 cms 模块 Article 控制器 add 动作 |

**命名规则**：
- 应用名：小写（如 `admin`、`wechat`）
- 控制器名：小写下划线（如 `cms/article`、`friend_link`）
- 动作名：小写（如 `index`、`add`、`state`）

---

## 9. 配置规范

### 9.1 配置文件格式

新增模块配置文件放置于 `config/` 目录：

```php
<?php

return [
    // 模块配置
    'module_name' => [
        'option1' => 'value1',
        'option2' => 'value2',
    ],
];
```

### 9.2 环境变量使用

敏感配置**必须**使用环境变量：

```php
return [
    'api_key' => env('MODULE_API_KEY', ''),
    'api_secret' => env('MODULE_API_SECRET', ''),
];
```

`.env` 文件：

```ini
MODULE_API_KEY=your_api_key
MODULE_API_SECRET=your_api_secret
```

---

## 10. 代码风格

### 10.1 PHP 编码风格

- **必须**使用 `declare(strict_types=1)`
- 类和方法**必须**添加文档注释
- 变量命名：小驼峰（camelCase）
- 常量命名：大写下划线（UPPER_SNAKE_CASE）
- 缩进：4 空格
- 类左花括号换行，方法左花括号换行

```php
<?php

declare(strict_types=1);

namespace app\admin\controller;

/**
 * 控制器说明.
 * @class ClassName
 */
class ClassName
{
    /**
     * 方法说明.
     * @auth true
     */
    public function methodName(): void
    {
        $variableName = 'value';
    }
}
```

### 10.2 模板编码风格

- 缩进：4 空格
- 标签前后不加空格：`{$title}` 而非 `{ $title }`
- 权限判断使用 HTML 注释语法：`<!--{if auth("action")}-->`

### 10.3 JavaScript 编码风格

- 缩进：4 空格
- 变量命名：小驼峰
- 字符串使用单引号
- 语句末尾加分号

```javascript
$(function () {
    var tableId = 'ArticleTable';
    $('#' + tableId).layTable({
        even: true,
        height: 'full'
    });
});
```

---

## 11. Git 规范

### 11.1 分支命名

| 分支类型 | 命名规则 | 示例 |
|----------|----------|------|
| 功能分支 | `feature/{模块名}` | `feature/cms` |
| 修复分支 | `fix/{问题描述}` | `fix/login-error` |
| 发布分支 | `release/{版本号}` | `release/v1.0.0` |

### 11.2 提交信息格式

```
{类型}: {简短描述}

{详细描述（可选）}
```

**类型**：
- `feat`：新功能
- `fix`：修复 bug
- `docs`：文档更新
- `style`：代码格式调整
- `refactor`：重构
- `test`：测试相关
- `chore`：构建/工具相关

**示例**：

```
feat: 新增 CMS 文章管理模块

- 添加文章控制器和模型
- 添加文章列表和表单模板
- 注册文章管理菜单
```

---

## 12. 开发流程清单

### 12.1 新模块开发步骤

1. **数据库设计**
   - [ ] 设计数据表结构
   - [ ] 创建迁移文件
   - [ ] 执行迁移 `php think migrate:run`

2. **模型开发**
   - [ ] 创建模型类
   - [ ] 添加 `@property` 注解
   - [ ] 配置时间字段和软删除

3. **控制器开发**
   - [ ] 创建控制器类
   - [ ] 添加 RBAC 权限注解
   - [ ] 实现 CRUD 五动作
   - [ ] 添加钩子方法（如需要）

4. **模板开发**
   - [ ] 创建列表页模板 `index.html`
   - [ ] 创建搜索模板 `index_search.html`
   - [ ] 创建表单模板 `form.html`
   - [ ] 配置 layTable 列定义
   - [ ] 添加权限判断

5. **菜单注册**
   - [ ] 在 `Service.php` 中注册菜单

6. **测试验证**
   - [ ] 启动开发服务器 `php think run`
   - [ ] 登录后台验证菜单显示
   - [ ] 测试 CRUD 功能
   - [ ] 测试权限控制

7. **代码提交**
   - [ ] 检查代码风格
   - [ ] 编写提交信息
   - [ ] 提交代码

### 12.2 常见问题排查

| 问题 | 可能原因 | 解决方案 |
|------|----------|----------|
| 菜单不显示 | 未注册或权限不足 | 检查 Service.php 和用户权限 |
| 权限判断失效 | 注解格式错误 | 使用 `<!--{if auth("action")}-->` |
| 表单提交无响应 | 缺少 `data-auto="true"` | 添加属性 |
| 表格数据不加载 | URL 或选择器错误 | 检查 `data-url` 和表格 ID |
| 状态切换无效 | 未绑定事件 | 添加 `layui.form.on('switch(...)')` |

---

## 附录：快速参考

### A. 常用命令

```bash
php think run                    # 启动开发服务器
php think migrate:run            # 执行迁移
php think migrate:rollback       # 回滚迁移
php think clear                  # 清除缓存
composer install                 # 安装依赖
```

### B. 常用函数

| 函数 | 用途 |
|------|------|
| `url('route')` | 生成 URL |
| `sysuri()` | 当前页面 URL |
| `sysconf('key')` | 获取系统配置 |
| `sysoplog('module', 'desc')` | 记录操作日志 |
| `input('key', 'default')` | 获取输入参数 |
| `arr2str($arr)` | 数组转字符串 |
| `str2arr($str)` | 字符串转数组 |

### C. 常用模板变量

| 变量 | 说明 |
|------|------|
| `{$title}` | 页面标题 |
| `{$vo}` | 表单数据 |
| `{$get}` | 查询参数 |
| `{$type}` | 列表类型（index/recycle） |

---

**文档版本**：v1.0.0
**最后更新**：2026-06-04
