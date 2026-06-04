# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

基于 **ThinkPHP 6/8** 的后台管理系统（ThinkAdmin），采用 **多应用 + Composer 插件** 架构。前端使用 LayUI 2.x + RequireJS。PHP >= 7.1。

## 常用命令

```bash
# 启动开发服务器（默认 127.0.0.1:8000）
php think run --host 127.0.0.1

# 数据库迁移（Phinx）
php think migrate:run

# 清除缓存
php think clear

# Composer 依赖
composer install
composer update

# 异步任务队列
php think xadmin:queue start    # 启动守护进程
php think xadmin:queue stop     # 停止
php think xadmin:queue query    # 查询状态
php think xadmin:queue listen   # 监听模式
```

## 项目架构

### 多应用目录结构

项目遵循 ThinkPHP 多应用模式，每个应用在 `app/<name>/` 下，本身就是一个独立的 Composer 插件：

```
app/
├── admin/          后台管理（用户、权限、菜单、配置、队列、文件）
│   ├── controller/ 控制器
│   ├── view/       模板
│   ├── Service.php 插件注册类 → zoujingli/think-plugs-admin
│   └── lang/       语言包
├── wechat/         微信模块（粉丝、图文、菜单、自动回复、支付）
│   ├── controller/
│   ├── model/      数据模型
│   ├── service/    业务服务
│   ├── command/    CLI 命令
│   ├── view/
│   └── Service.php → zoujingli/think-plugs-wechat
└── index/          前台入口（默认重定向到后台登录）
```

每个应用目录可包含：`controller/`、`view/`、`Service.php`，以及可选的 `model/`、`service/`、`command/`、`lang/`、`route/`。

### 插件机制

所有插件继承 `think\admin\Plugin`（来自 `zoujingli/think-library`）。每个应用的 `Service.php` 是插件注册类，定义插件名称、Composer 包名、菜单结构（`menu()`），并在 `register()` 中注册路由/命令/事件。

### 入口文件

- **Web 入口**: `public/index.php` → `RuntimeService::doWebsiteInit()`
- **CLI 入口**: `think` → `RuntimeService::doConsoleInit()`

### 配置文件

| 文件 | 用途 |
|------|------|
| `.env` | 环境变量（数据库、缓存、会话），从 `.env.example` 复制 |
| `config/database.php` | 数据库连接（MySQL + SQLite），通过 `DB_TYPE` 环境变量切换 |
| `config/app.php` | 应用设置、CORS、超级用户、RBAC 配置 |
| `config/route.php` | URL 路由规则 |
| `config/view.php` | 模板引擎配置（Think 模板引擎，定界符 `{}`） |
| `config/cache.php` | 缓存配置（file / redis） |
| `config/phinx.php` | Phinx 迁移配置 |

### 核心依赖

| 包名 | 作用 |
|------|------|
| `zoujingli/think-library` | 核心框架：Controller 基类、ORM 辅助、Storage、Queue、RBAC |
| `zoujingli/think-plugs-admin` | 后台管理插件 |
| `zoujingli/think-plugs-wechat` | 微信集成插件 |
| `topthink/think-orm` | 数据库 ORM |

## 后台页面开发指南

### 控制器开发

控制器继承 `think\admin\Controller`，遵循 `index`/`add`/`edit`/`state`/`remove` 的动作命名规范。

#### RBAC 权限注解

通过控制器方法的文档注释声明权限节点，系统会自动扫描生成权限树：

```php
/**
 * 用户列表
 * @auth true    需要权限验证
 * @menu true    显示在侧边栏菜单
 * @login true   需要登录
 */
public function index() { ... }
```

`config/app.php` 中 `rbac_ignore` 配置免鉴权的应用（如 `index`）。

#### CRUD 辅助方法

框架提供了一套静态模型方法和控制器混入方法来快速实现增删改查：

**数据列表**：
```php
// 方式一：模型静态方法（推荐）
// 继承 BaseModel 的模型自动过滤软删除，无需手动加条件
SystemUser::mQuery()->layTable(function () {
    $this->title = '用户管理';
}, function (QueryHelper $query) {
    $query->equal('status,usertype')->like('username')->dateBetween('create_at');
});

// 方式二：控制器方法
$this->_query('user')->like('username')->equal('status')->order('id desc')->page();
```

**表单（新增/编辑）**：
```php
// 方式一：模型静态方法
SystemUser::mForm('form');

// 方式二：控制器方法
$this->_form('user', 'form');
```

**保存/更新**：
```php
SystemUser::mSave($this->_vali([
    'status.in:0,1' => '状态值范围异常！',
    'status.require' => '状态值不能为空！',
]));
```

**删除**：
```php
SystemUser::mDelete();
```

**验证**：使用 `$this->_vali(['字段.规则' => '错误提示'])`。

#### 表单钩子方法

控制器中可通过命名约定的钩子方法处理表单数据：

```php
// 表单数据处理（GET 时加载，POST 时校验）
protected function _form_filter(array &$data)
{
    if ($this->request->isPost()) {
        // POST 时的数据校验和处理
    } else {
        // GET 时的数据准备
    }
}

// 表单处理结果回调
protected function _form_result(bool $status) { ... }

// 列表分页数据回调
protected function _index_page_filter(array $data, array &$result) { ... }
```

### 模板系统

使用 **ThinkPHP 模板引擎**，定界符为 `{}`，模板文件后缀 `.html`。模板路径映射：`app/<应用>/view/<控制器>/<方法>.html`。

#### 模板继承与布局

系统有四个核心布局模板：

| 布局模板 | 用途 | 特点 |
|----------|------|------|
| `index/index` | 完整后台布局 | 包含左侧菜单 + 顶部导航 + 主体内容区 |
| `table` | 数据表格布局 | `layui-card` 容器，内含搜索区 + 表格区 |
| `main` | 内容卡片布局 | `layui-card` 容器，通用内容展示 |
| `full` | 全屏独立布局 | 独立 HTML 页面，无后台框架（用于登录页等） |

子模板通过 `{extend name='布局名'}` 继承布局，通过 `{block name='xxx'}` 覆盖布局中的区块：

```html
<!-- 继承 table 布局 -->
{extend name='table'}

<!-- 覆盖按钮区块 -->
{block name="button"}
<button data-modal='{:url("add")}' class='layui-btn layui-btn-sm layui-btn-primary'>添加</button>
{/block}

<!-- 覆盖内容区块 -->
{block name="content"}
<table id="MyTable" data-url="{:sysuri()}" data-target-search="form.form-search"></table>
{/block}

<!-- 覆盖脚本区块 -->
{block name='script'}
<script>
$(function () {
    $('#MyTable').layTable({ ... });
});
</script>
{/block}
```

#### 模板语法速查

**变量输出**：
```html
<!-- 普通变量（自动 htmlentities 转义） -->
{$title}
{$vo.username|default=''}

<!-- 不转义输出（用于 HTML 内容） -->
{$content|raw}

<!-- 调用函数 -->
{:sysconf('site_name')}
{:url('admin/user/index')}
{:sysuri()}
{:lang('用户管理')}
{:date('Y-m-d')}
```

**条件判断**：
```html
{if $status eq 1}
    ...
{else}
    ...
{/if}

{notempty name='title'}
    <h1>{$title}</h1>
{/notempty}
```

**循环**：
```html
{foreach $list as $key => $item}
    <tr>
        <td>{$item.id}</td>
        <td>{$item.name}</td>
    </tr>
{/foreach}
```

**模板包含**：
```html
{include file='user/index_search'}
```

**权限判断**（注意：使用 HTML 注释语法 `<!--{if auth("action")}-->`）：
```html
<!--{if auth("add")}-->
<button data-modal='{:url("add")}'>添加</button>
<!--{/if}-->

<!--{if auth("state")}-->
<a data-action="{:url('state')}" data-rule="id#{id};status#1">启用</a>
<!--{/if}-->

<!--{if auth("remove")}-->
<a data-action="{:url('remove')}" data-value="id#{{d.id}}">删除</a>
<!--{/if}-->
```

**PHP 代码**：
```html
{php}sysconf('base.editor', 'ckeditor4');{/php}
```

**静态资源路径**：
```html
<link rel="stylesheet" href="__ROOT__/static/theme/css/console.css">
<script src="__ROOT__/static/plugs/layui/layui.js"></script>
```

#### LayUI 数据表格（layTable）

列表页的核心是 `layTable` 组件，通过 jQuery 插件方式初始化：

```html
<table id="MyTable" data-url="{:sysuri()}" data-target-search="form.form-search"></table>

<script>
$(function () {
    $('#MyTable').layTable({
        even: true,               // 隔行背景
        height: 'full',           // 自适应高度
        sort: {field: 'id', type: 'desc'},  // 默认排序
        where: {type: 'index'},   // 额外查询参数
        cols: [[
            {checkbox: true, fixed: true},                          // 复选框列
            {field: 'id', title: 'ID', width: 80, sort: true},     // 普通列
            {field: 'name', title: '名称', minWidth: 100},          // 弹性宽度
            {field: 'status', title: '状态', templet: '#StatusTpl'}, // 自定义模板
            {toolbar: '#toolbar', title: '操作', fixed: 'right'}    // 操作列
        ]]
    });
});
</script>
```

**列自定义模板**有两种写法：

方式一：`<script type="text/html">` 模板（支持 LayUI laytpl 语法 `{{d.field}}`、`{{-expression}}`）：
```html
<script type="text/html" id="StatusTpl">
    <!--{if auth("state")}-->
    <input type="checkbox" value="{{d.id}}" lay-skin="switch"
           lay-text="已激活|已禁用" lay-filter="StatusSwitch"
           {{-d.status > 0 ? 'checked' : ''}}>
    <!--{else}-->
    {{-d.status ? '<b class="color-green">已激活</b>' : '<b class="color-red">已禁用</b>'}}
    <!--{/if}-->
</script>
```

方式二：JavaScript 函数（适合复杂逻辑）：
```javascript
{field: 'name', templet: function (d) {
    return d.name || '-';
}}
```

**排序输入模板**（可直接修改排序值并提交）：
```html
<script type="text/html" id="SortInputTpl">
    <input type="number" min="0" data-blur-number="0"
           data-action-blur="{:sysuri()}"
           data-value="id#{{d.id}};action#sort;sort#{value}"
           data-loading="false" value="{{d.sort}}"
           class="layui-input text-center">
</script>
```

#### 前端交互 data-* 属性

LayUI 框架扩展了一系列 `data-*` 属性实现无刷新交互：

| 属性 | 用途 | 示例 |
|------|------|------|
| `data-modal` | 弹窗打开页面（iframe 模式） | `data-modal='{:url("add")}'` |
| `data-open` | 当前页跳转打开 | `data-open='{:url("edit")}?id={$id}'` |
| `data-load` | AJAX 请求 URL 并刷新 | `data-load="{:url('clean')}"` |
| `data-action` | AJAX 提交操作（配合选中行） | `data-action="{:url('state')}"` |
| `data-value` | 手动指定提交参数 | `data-value="id#{{d.id}}"` |
| `data-rule` | 从表格选中行提取参数 | `data-rule="id#{id};status#0"` |
| `data-confirm` | 操作前确认提示 | `data-confirm="确定要删除吗？"` |
| `data-table-id` | 指定关联的表格 ID | `data-table-id="MyTable"` |
| `data-close` | 关闭当前弹窗 | `data-close` |
| `data-queue` | 以队列方式执行任务 | `data-queue='{:url("redo")}'` |
| `data-target-search` | 指定搜索表单选择器 | `data-target-search="form.form-search"` |
| `data-auto` | 表单自动提交 | `data-auto="true"` |
| `data-title` | 弹窗标题 | `data-title="编辑用户"` |
| `data-event-dbclick` | 双击行触发 | 配合 `data-modal` 使用 |

**弹窗打开 vs 页面跳转的区别**：
- `data-modal`：在 Layer 弹窗中打开，适合表单编辑、详情查看
- `data-open`：直接跳转页面，适合需要完整页面的场景

**批量操作**：使用 `data-rule="id#{id}"` 从表格选中行提取 `id` 值，`{id}` 是占位符代表逗号分隔的选中 ID。

#### 搜索表单

搜索区域通常是独立的模板文件（如 `index_search.html`），通过 `{include}` 引入：

```html
<form class="layui-form layui-form-pane form-search" action="{:sysuri()}"
      onsubmit="return false" method="get" autocomplete="off">

    <div class="layui-form-item layui-inline">
        <label class="layui-form-label">用户名</label>
        <label class="layui-input-inline">
            <input name="username" value="{$get.username|default=''}"
                   placeholder="请输入用户名" class="layui-input">
        </label>
    </div>

    <div class="layui-form-item layui-inline">
        <label class="layui-form-label">创建时间</label>
        <div class="layui-input-inline">
            <input data-date-range name="create_at"
                   value="{$get.create_at|default=''}"
                   placeholder="请选择时间" class="layui-input">
        </div>
    </div>

    <div class="layui-form-item layui-inline">
        <button class="layui-btn layui-btn-primary">
            <i class="layui-icon">&#xe615;</i> 搜 索
        </button>
    </div>
</form>
```

表格通过 `data-target-search="form.form-search"` 绑定搜索表单，搜索时自动将表单参数传给表格。

#### 表单页面

表单页面通常继承 `main` 布局，使用 LayUI 表单组件：

```html
<form action="{:sysuri()}" method="post" data-auto="true"
      class="layui-form layui-card" data-table-id="MyTable">

    <div class="layui-card-body padding-left-40">
        <label class="layui-form-item relative block">
            <span class="help-label"><b>字段名称</b>Field Label</span>
            <input name="field_name" value='{$vo.field_name|default=""}'
                   required pattern="^.{4,}$" vali-name="字段名称"
                   placeholder="请输入" class="layui-input">
            <span class="help-block">字段说明文字</span>
        </label>
    </div>

    <div class="hr-line-dashed"></div>

    <!-- 编辑时携带 ID -->
    {notempty name='vo.id'}
    <input type='hidden' value='{$vo.id}' name='id'>
    {/notempty}

    <div class="layui-form-item text-center">
        <button class="layui-btn" type='submit'>保存数据</button>
        <button class="layui-btn layui-btn-danger" type='button'
                data-confirm="确定要取消编辑吗？" data-close>取消编辑</button>
    </div>
</form>
```

关键点：
- `action="{:sysuri()}"` 提交到当前控制器
- `data-auto="true"` 启用自动表单提交（AJAX 方式）
- `data-table-id="MyTable"` 提交成功后自动刷新关联表格
- `data-close` 取消按钮关闭弹窗
- 表单数据在 `$vo` 变量中，编辑时有 `id` 字段
- `vali-name` 属性设置验证提示的字段名
- `pattern` 属性设置正则验证
- 图片上传：`$('[name=headimg]').uploadOneImage()`

### 新建一个后台 CRUD 页面的完整流程

**1. 创建控制器** `app/admin/controller/Example.php`：

```php
namespace app\admin\controller;

use think\admin\Controller;
use think\admin\helper\QueryHelper;

class Example extends Controller
{
    /**
     * 数据列表
     * @auth true  @menu true
     */
    public function index()
    {
        $this->type = input('type', 'index');
        ModelName::mQuery()->layTable(function () {
            $this->title = '示例管理';
        }, function (QueryHelper $query) {
            $query->equal('status')->like('name')->dateBetween('create_at');
        });
    }

    /** @auth true */
    public function add() { ModelName::mForm('form'); }

    /** @auth true */
    public function edit() { ModelName::mForm('form'); }

    /** @auth true */
    public function state()
    {
        ModelName::mSave($this->_vali([
            'status.in:0,1' => '状态值异常！',
            'status.require' => '状态不能为空！',
        ]));
    }

    /** @auth true */
    public function remove() { ModelName::mDelete(); }
}
```

**2. 创建列表模板** `app/admin/view/example/index.html`：

继承 `table` 布局，定义 `button`、`content`、`script` 三个 block。

**3. 创建搜索模板** `app/admin/view/example/index_search.html`：

搜索表单，使用 `form.form-search` 类名。

**4. 创建表单模板** `app/admin/view/example/form.html`：

编辑表单，使用 `data-auto="true"` 自动提交。

**5. 注册菜单**：在 `app/admin/Service.php` 的 `menu()` 方法中添加菜单项。

### URL 生成函数

| 函数 | 用途 |
|------|------|
| `{:url('admin/user/index')}` | 生成路由 URL |
| `{:sysuri()}` | 当前页面 URL（含查询参数） |
| `sysuri('admin/user/index')` | 生成指定路由 URL |
| `{:request()->url()}` | 当前完整 URL |

## 代码规范

- 界面文本和 README 使用**中文**，PHP 代码和注释使用英文。
- 控制器动作命名：`index`（列表）、`add`（新增）、`edit`（编辑）、`state`（状态切换）、`remove`（删除）。
- 模板文件路径：`app/<应用>/view/<控制器>/<方法>.html`。
- 表单钩子方法：`_form_filter`（数据处理）、`_form_result`（结果回调）。
- 权限模板判断统一用 `<!--{if auth("action")}-->...<!--{/if}-->` 注释语法。
- 日志操作：`sysoplog('模块名', '操作描述')`。
- 事件触发：`$this->app->event->trigger('事件名', $data)`。

### 后台管理模块规范

- 后台管理的内容都放在 `admin` 模块下（控制器、模型、视图、Helper）。
- 所有新模型继承 `app\admin\model\BaseModel`（自带软删除过滤），不要直接继承 `think\admin\Model`。
- 新模型统一使用 `deleted_at`（datetime, nullable）字段实现软删除，不使用 `deleted`/`is_deleted` 字段。
- 控制器使用框架快捷方法：`mQuery()`、`mForm()`、`mSave()`、`mDelete()`，不要另造新方法名。
- `BaseModel` 已重写 `mDelete()` 以支持 `deleted_at` 单字段软删除，控制器直接调用即可。
- 当控制器与模型同名时，使用 `use app\admin\model\Category as CategoryModel` 别名引入，控制器内用 `CategoryModel::mXxx()` 调用。

### 树形分类规范

- 使用 `pid` 字段实现父子关系，0 表示顶级。
- 使用 `DataExtend::arr2tree()` 转树形结构，`DataExtend::arr2table()` 转平铺表格（带缩进）。
- 限制三级深度，`spt >= 3` 的节点不能再添加子节点。

## 重要约束

### vendor 目录只读

`vendor/` 目录已纳入版本控制，但**禁止修改其中的任何代码**。vendor 目录是 Composer 依赖包，所有代码变更只能在 `app/`、`config/`、`public/` 等自有目录中进行。如果需要修改依赖包的行为，应通过继承、事件监听或中间件等方式在项目代码中实现。
