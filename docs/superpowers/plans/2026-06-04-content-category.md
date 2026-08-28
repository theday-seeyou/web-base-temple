# 内容管理 - 栏目管理 实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在 WebBase CMS 后台新增"内容管理"菜单，实现三级树状栏目管理（CRUD + 软删除）。

**Architecture:** 创建 BaseModel 基类（继承 WebBase\Core\Model + SoftDelete trait）作为所有新模型的父类；自定义 DeleteHelper 处理 `deleted_at` 软删除；Category 控制器使用框架标准快捷方法。树形展示使用框架自带的 DataExtend 工具类。

**Tech Stack:** PHP 7.1+ / ThinkPHP 6 / WebBase CMS / LayUI 2.x / MySQL

---

## File Structure

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `app/admin/helper/DeleteHelper.php` | 自定义删除助手，支持 `deleted_at` 单字段软删除 |
| Create | `app/admin/model/BaseModel.php` | 新模型基类，集成 SoftDelete trait + 重写 mDelete |
| Create | `app/admin/model/Category.php` | 栏目模型 |
| Create | `app/admin/controller/Category.php` | 栏目控制器（index/add/edit/state/remove） |
| Create | `app/admin/view/category/index.html` | 栏目树形列表页 |
| Create | `app/admin/view/category/form.html` | 栏目新增/编辑表单 |
| Modify | `app/admin/Service.php:47-68` | 注册"内容管理"菜单 |

---

### Task 1: 创建数据库表

**Files:**
- Reference: `web-base-core/src/model/SystemMenu.php`（独立核心包仓库中的现有表结构参考）

- [ ] **Step 1: 执行 SQL 创建 category 表**

在 MySQL 中执行：

```sql
CREATE TABLE `category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `pid` int unsigned NOT NULL DEFAULT '0' COMMENT '上级栏目ID',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '栏目名称',
  `code` varchar(50) DEFAULT NULL COMMENT '栏目标识',
  `icon` varchar(100) DEFAULT NULL COMMENT '栏目图标',
  `cover` varchar(255) DEFAULT NULL COMMENT '封面图',
  `desc` text COMMENT '描述',
  `sort` int unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态(1启用,0禁用)',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  `create_at` datetime DEFAULT NULL COMMENT '创建时间',
  `update_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_pid` (`pid`),
  UNIQUE KEY `uniq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='栏目管理';
```

- [ ] **Step 2: 验证表已创建**

Run: `php think run --host 127.0.0.1` 或用 MySQL 客户端确认 `SHOW TABLES LIKE 'category'` 返回结果。

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: create category table for content management"
```

---

### Task 2: 创建自定义 DeleteHelper

**Files:**
- Create: `app/admin/helper/DeleteHelper.php`
- Reference: `web-base-core/src/helper/DeleteHelper.php`（独立核心包仓库）
- Reference: `web-base-core/src/Helper.php`（独立核心包仓库中的基类构造函数）

逻辑与 vendor 的 `DeleteHelper` 完全一致，唯一区别是软删除检测：将 `deleted`/`is_deleted` 替换为 `deleted_at`。当表存在 `deleted_at` 字段时写入时间戳，不存在则硬删除。

- [ ] **Step 1: 创建 helper 目录和 DeleteHelper 文件**

```php
<?php

declare(strict_types=1);

namespace app\admin\helper;

use WebBase\Core\Helper;
use think\db\BaseQuery;
use think\db\exception\DbException;
use think\Model;

/**
 * 数据软删除管理器（支持 deleted_at 字段）.
 * @class DeleteHelper
 */
class DeleteHelper extends Helper
{
    /**
     * 逻辑器初始化.
     * @param BaseQuery|Model|string $dbQuery
     * @param string $field 操作数据主键
     * @param mixed $where 额外更新条件
     * @return bool|void
     * @throws DbException
     */
    public function init($dbQuery, string $field = '', $where = [])
    {
        $query = static::buildQuery($dbQuery);
        $field = $field ?: ($query->getPk() ?: 'id');
        $value = $this->app->request->post($field);

        // 查询限制处理
        if (!empty($where)) {
            $query->where($where);
        }
        if (!isset($where[$field]) && is_string($value)) {
            $query->whereIn($field, str2arr($value));
        }

        // 前置回调处理
        if ($this->class->callback('_delete_filter', $query, $where) === false) {
            return false;
        }

        // 阻止危险操作
        if (!$query->getOptions('where')) {
            $this->class->error('数据删除失败！');
        }

        // 组装执行数据（检测 deleted_at 字段）
        $data = [];
        if (method_exists($query, 'getTableFields')) {
            $fields = $query->getTableFields();
            if (in_array('deleted_at', $fields)) {
                $data['deleted_at'] = date('Y-m-d H:i:s');
            }
        }

        // 执行删除操作
        if ($result = (empty($data) ? $query->delete() : $query->update($data)) !== false) {
            // 模型自定义事件回调
            $model = $query->getModel();
            if ($model instanceof \WebBase\Core\Model) {
                $model->onAdminDelete(strval($value));
            }
        }

        // 结果回调处理
        if ($this->class->callback('_delete_result', $result) === false) {
            return $result;
        }

        // 回复返回结果
        if ($result !== false) {
            $this->class->success('数据删除成功！', '');
        } else {
            $this->class->error('数据删除失败！');
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/admin/helper/DeleteHelper.php
git commit -m "feat: add custom DeleteHelper with deleted_at soft delete support"
```

---

### Task 3: 创建 BaseModel 基类

**Files:**
- Create: `app/admin/model/BaseModel.php`
- Reference: `web-base-core/src/Model.php`（独立核心包仓库中的框架基类）
- Reference: `vendor/topthink/think-orm/src/model/concern/SoftDelete.php` (SoftDelete trait)

BaseModel 继承 `WebBase\Core\Model`，引入 `SoftDelete` trait 实现查询自动过滤，并重写 `mDelete()` 静态方法路由到自定义 DeleteHelper。

**关键点：**
- `$deleteTime = 'deleted_at'` — 软删除字段名
- `$defaultSoftDelete = null` — NULL 表示未删除，查询条件为 `WHERE deleted_at IS NULL`
- `mDelete()` 定义为静态方法后，PHP 直接调用它，不再走 `__callStatic` → `QueryHelper::make()` → vendor `DeleteHelper`

- [ ] **Step 1: 创建 model 目录和 BaseModel 文件**

```php
<?php

declare(strict_types=1);

namespace app\admin\model;

use app\admin\helper\DeleteHelper;

/**
 * 后台管理模型基类.
 * 集成 SoftDelete trait，查询自动过滤已删除记录，删除自动写入 deleted_at.
 * 所有 admin 模块下的新模型应继承此类.
 *
 * @class BaseModel
 */
class BaseModel extends \WebBase\Core\Model
{
    use \think\model\concern\SoftDelete;

    /**
     * 软删除字段.
     * @var string
     */
    protected $deleteTime = 'deleted_at';

    /**
     * 软删除默认值（null 表示未删除）.
     * @var mixed
     */
    protected $defaultSoftDelete = null;

    /**
     * 快捷删除（支持 deleted_at 单字段软删除）.
     * 重写框架 mDelete，路由到自定义 DeleteHelper.
     *
     * @param string $field 操作数据主键
     * @param mixed $where 额外更新条件
     * @return bool|void
     */
    public static function mDelete(string $field = '', $where = [])
    {
        return DeleteHelper::instance()->init(static::class, $field, $where);
    }
}
```

**注意：** `DeleteHelper::instance()` 来自 `Helper` 基类的 `instance()` 方法（独立仓库 `web-base-core/src/Helper.php:79-82`），调用 `Container::invokeClass()` 创建实例，构造函数 `App $app, Controller $class` 由容器从当前请求上下文自动解析注入。调用时不传参数，然后链式调用 `->init()` 传入模型类名和条件。

- [ ] **Step 2: Commit**

```bash
git add app/admin/model/BaseModel.php
git commit -m "feat: add BaseModel with SoftDelete trait and mDelete override"
```

---

### Task 4: 创建 Category 模型

**Files:**
- Create: `app/admin/model/Category.php`
- Reference: `app/wechat/model/WechatNews.php` (现有模型风格)

- [ ] **Step 1: 创建 Category 模型**

```php
<?php

declare(strict_types=1);

namespace app\admin\model;

/**
 * 栏目管理模型.
 *
 * @property int $id
 * @property int $pid 上级栏目ID
 * @property string $title 栏目名称
 * @property string $code 栏目标识
 * @property string $icon 栏目图标
 * @property string $cover 封面图
 * @property string $desc 描述
 * @property int $sort 排序
 * @property int $status 状态(1启用,0禁用)
 * @property string $deleted_at 删除时间
 * @property string $create_at 创建时间
 * @property string $update_at 更新时间
 * @class Category
 */
class Category extends BaseModel
{
    /**
     * 日志类型.
     * @var string
     */
    protected $oplogType = '栏目管理';

    /**
     * 日志名称.
     * @var string
     */
    protected $oplogName = '栏目';
}
```

- [ ] **Step 2: Commit**

```bash
git add app/admin/model/Category.php
git commit -m "feat: add Category model"
```

---

### Task 5: 创建 Category 控制器

**Files:**
- Create: `app/admin/controller/Category.php`
- Reference: `app/admin/controller/Menu.php` (树形列表 + 表单处理参考)
- Reference: `web-base-core/src/extend/DataExtend.php`（独立核心包仓库中的 arr2tree/arr2table）

- [ ] **Step 1: 创建 Category 控制器**

```php
<?php

declare(strict_types=1);

namespace app\admin\controller;

use WebBase\Core\Controller;
use WebBase\Core\extend\DataExtend;
use app\admin\model\Category;

/**
 * 栏目管理.
 * @class Category
 */
class Category extends Controller
{
    /**
     * 栏目列表.
     * @auth true
     * @menu true
     */
    public function index()
    {
        $this->title = '栏目管理';
        Category::mQuery()->layTable();
    }

    /**
     * 添加栏目.
     * @auth true
     */
    public function add()
    {
        Category::mForm('form');
    }

    /**
     * 编辑栏目.
     * @auth true
     */
    public function edit()
    {
        Category::mForm('form');
    }

    /**
     * 修改栏目状态.
     * @auth true
     */
    public function state()
    {
        Category::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除栏目.
     * @auth true
     */
    public function remove()
    {
        Category::mDelete();
    }

    /**
     * 列表数据处理（树形结构）.
     * @param array $data
     */
    protected function _index_page_filter(array &$data)
    {
        $data = DataExtend::arr2table(DataExtend::arr2tree($data));
    }

    /**
     * 表单数据处理.
     * @param array $vo
     */
    protected function _form_filter(array &$vo)
    {
        if ($this->request->isGet()) {
            // 上级栏目默认值
            $vo['pid'] = $vo['pid'] ?? input('pid', '0');

            // 获取所有栏目并转为树形平铺表
            $categories = Category::mk()->order('sort asc,id asc')->column('id,pid,title', 'id');
            $this->categories = DataExtend::arr2table(
                array_merge($categories, [['id' => '0', 'pid' => '-1', 'title' => '顶级栏目']])
            );

            // 限制三级深度：spt >= 3 的节点不能再作为父级
            foreach ($this->categories as $key => $cat) {
                if ($cat['spt'] >= 3) {
                    unset($this->categories[$key]);
                }
            }
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/admin/controller/Category.php
git commit -m "feat: add Category controller with tree listing and CRUD"
```

---

### Task 6: 创建栏目列表视图

**Files:**
- Create: `app/admin/view/category/index.html`
- Reference: `app/admin/view/menu/index.html` (树形表格参考)

列表页继承 `table` 布局。`page: false` 因为树形数据不分页。通过 `spl` 字段显示缩进层级。操作列中 `spt < 2` 的栏目显示"添加"按钮（即一、二级可添加子栏目，三级不可）。

- [ ] **Step 1: 创建 view/category 目录和列表视图**

```html
{extend name='table'}

{block name="button"}
<!--{if auth("add")}-->
<button data-modal='{:url("add")}' data-table-id="CategoryTable" class='layui-btn layui-btn-sm layui-btn-primary'>添加栏目</button>
<!--{/if}-->

<!--{if auth("state")}-->
<button data-action='{:url("state")}' data-table-id="CategoryTable" data-rule="id#{sps};status#0" class='layui-btn layui-btn-sm layui-btn-primary'>禁用栏目</button>
<!--{/if}-->
{/block}

{block name="content"}
<table id="CategoryTable" data-url="{:sysuri()}" data-target-search="form.form-search"></table>

<script>
$(function () {
    $('#CategoryTable').layTable({
        even: true, height: 'full', page: false,
        sort: {field: 'sort asc,id', type: 'asc'},
        cols: [[
            {checkbox: true, field: 'sps'},
            {field: 'sort', title: '排序', width: 100, align: 'center', templet: '#SortInputTpl'},
            {field: 'icon', title: '图标', width: 80, align: 'center', templet: '<div>{{- d.icon ? "<i class=\"" + d.icon + " font-s18\"></i>" : "-" }}</div>'},
            {field: 'title', title: '栏目名称', minWidth: 220, templet: '<div><span class="color-desc">{{d.spl}}</span>{{d.title}}</div>'},
            {field: 'code', title: '栏目标识', width: 150},
            {field: 'cover', title: '封面图', width: 100, align: 'center', templet: '#CoverTpl'},
            {field: 'status', title: '状态', width: 120, align: 'center', templet: '#StatusSwitchTpl'},
            {toolbar: '#toolbar', title: '操作', width: 200, align: 'center', fixed: 'right'},
        ]]
    });

    // 数据状态切换
    layui.form.on('switch(StatusSwitch)', function (object) {
        object.data = {status: object.elem.checked > 0 ? 1 : 0};
        object.data.id = object.value.split('|')[object.data.status] || object.value;
        $.form.load("{:url('state')}", object.data, 'post', function (ret) {
            if (ret.code < 1) $.msg.error(ret.info, 3, function () {
                $('#CategoryTable').trigger('reload');
            }); else {
                $('#CategoryTable').trigger('reload');
            }
            return false;
        }, false);
    });
});
</script>

<!-- 封面图模板 -->
<script type="text/html" id="CoverTpl">
    {{- d.cover ? '<img src="' + d.cover + '" style="max-height:30px;max-width:60px">' : '-' }}
</script>

<!-- 状态切换模板 -->
<script type="text/html" id="StatusSwitchTpl">
    <!--{if auth("state")}-->
    <input type="checkbox" value="{{d.sps}}|{{d.spp}}" lay-text="已激活|已禁用" lay-filter="StatusSwitch" lay-skin="switch" {{-d.status>0?'checked':''}}>
    <!--{else}-->
    {{-d.status ? '<b class="color-green">已激活</b>' : '<b class="color-red">已禁用</b>'}}
    <!--{/if}-->
</script>

<!-- 排序输入模板 -->
<script type="text/html" id="SortInputTpl">
    <input type="number" min="0" data-blur-number="0" data-action-blur="{:sysuri()}" data-value="id#{{d.id}};action#sort;sort#{value}" data-loading="false" value="{{d.sort}}" class="layui-input text-center">
</script>

<!-- 操作面板模板 -->
<script type="text/html" id="toolbar">
    <!--{if auth("add")}-->
    {{# if(d.spt<2){ }}
    <a class="layui-btn layui-btn-sm layui-btn-primary" data-title="添加子栏目" data-modal='{:url("add")}?pid={{d.id}}'>添加</a>
    {{# }else{ }}
    <a class="layui-btn layui-btn-sm layui-btn-disabled">添加</a>
    {{# } }}
    <!--{/if}-->
    {if auth('edit')}
    <a class="layui-btn layui-btn-sm" data-event-dbclick data-title="编辑栏目" data-modal='{:url("edit")}?id={{d.id}}'>编辑</a>
    {/if}
    {if auth('remove')}
    <a class="layui-btn layui-btn-sm layui-btn-danger" data-confirm="确定要删除该栏目吗？" data-action="{:url('remove')}" data-value="id#{{d.sps}}">删除</a>
    {/if}
</script>
{/block}
```

- [ ] **Step 2: Commit**

```bash
git add app/admin/view/category/index.html
git commit -m "feat: add category tree table view"
```

---

### Task 7: 创建栏目表单视图

**Files:**
- Create: `app/admin/view/category/form.html`
- Reference: `app/admin/view/menu/form.html` (表单风格参考)

表单继承 `main` 布局，包含：上级栏目选择（下拉）、栏目名称、栏目标识、图标、封面图、描述、排序。编辑时携带隐藏 `id` 字段。

- [ ] **Step 1: 创建表单视图**

```html
<form action="{:sysuri()}" method="post" data-auto="true" class="layui-form layui-card" data-table-id="CategoryTable">

    <div class="layui-card-body padding-left-40">

        <div class="layui-form-item">
            <label class="layui-form-label label-required-next">上级栏目</label>
            <div class="layui-input-block">
                <select name='pid' class='layui-select' lay-search>
                    {foreach $categories as $cat}
                    {eq name='cat.id' value='$vo.pid|default=0'}
                    <option selected value='{$cat.id}'>{$cat.spl|raw}{$cat.title}</option>
                    {else}
                    <option value='{$cat.id}'>{$cat.spl|raw}{$cat.title}</option>
                    {/eq}
                    {/foreach}
                </select>
                <p class="help-block"><b>必选</b>，请选择上级栏目（目前最多支持三级栏目）</p>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label label-required-next">栏目名称</label>
            <div class="layui-input-block">
                <input name="title" value='{$vo.title|default=""}' required vali-name="栏目名称" placeholder="请输入栏目名称" class="layui-input">
                <p class="help-block"><b>必填</b>，建议 4-6 个汉字</p>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">栏目标识</label>
            <div class="layui-input-block">
                <input name="code" value='{$vo.code|default=""}' placeholder="请输入栏目标识（如：news、product）" class="layui-input">
                <p class="help-block"><b>可选</b>，用于程序调用，唯一标识，只能使用英文字母和下划线</p>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">栏目图标</label>
            <div class="layui-input-block">
                <div class="layui-input-inline">
                    <input placeholder="请输入或选择图标" name="icon" value='{$vo.icon|default=""}' class="layui-input">
                </div>
                <span style="padding:0 12px;min-width:45px" class='layui-btn layui-btn-primary'>
                    <i style="font-size:1.2em;margin:0;float:none" class='{$vo.icon|default=""}'></i>
                </span>
                <button data-icon='icon' type='button' class='layui-btn layui-btn-primary'>选择图标</button>
                <p class="help-block"><b>可选</b>，设置栏目图标</p>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">封面图</label>
            <div class="layui-input-block">
                <input name="cover" value='{$vo.cover|default=""}' placeholder="请上传封面图" class="layui-input">
                <p class="help-block"><b>可选</b>，栏目封面图片</p>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">描述</label>
            <div class="layui-input-block">
                <textarea name="desc" placeholder="请输入栏目描述" class="layui-textarea">{$vo.desc|default=''}</textarea>
                <p class="help-block"><b>可选</b>，栏目描述或备注</p>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">排序</label>
            <div class="layui-input-block">
                <input type="number" name="sort" value='{$vo.sort|default=0}' min="0" placeholder="排序值，越小越靠前" class="layui-input">
                <p class="help-block"><b>可选</b>，排序值越小越靠前，默认为 0</p>
            </div>
        </div>

    </div>

    <div class="hr-line-dashed"></div>
    {notempty name='vo.id'}<input type='hidden' value='{$vo.id}' name='id'>{/notempty}

    <div class="layui-form-item text-center">
        <button class="layui-btn" type='submit'>保存数据</button>
        <button class="layui-btn layui-btn-danger" type='button' data-confirm="确定要取消编辑吗？" data-close>取消编辑</button>
    </div>
</form>

<script>
$(function () {
    $('[name="cover"]').uploadOneImage();
    $('[name="icon"]').on('change', function () {
        $(this).parent().next().find('i').get(0).className = this.value;
    });
});
</script>
```

- [ ] **Step 2: Commit**

```bash
git add app/admin/view/category/form.html
git commit -m "feat: add category form view"
```

---

### Task 8: 注册菜单

**Files:**
- Modify: `app/admin/Service.php:47-68`

在 `menu()` 返回数组中新增"内容管理"菜单组，位于现有菜单数组的最前面。

- [ ] **Step 1: 修改 Service.php，在 menu() 中添加内容管理菜单**

在 `menu()` 方法的 return 数组最前面新增一个菜单组：

```php
public static function menu(): array
{
    return [
        [
            'name' => '内容管理',
            'subs' => [
                ['name' => '栏目管理', 'icon' => 'layui-icon layui-icon-app', 'node' => 'admin/category/index'],
            ],
        ],
        [
            'name' => '系统配置',
            'subs' => [
                // ... 现有内容不变
```

即在现有 `return [` 后、第一个 `['name' => '系统配置'` 前插入"内容管理"菜单组。

- [ ] **Step 2: Commit**

```bash
git add app/admin/Service.php
git commit -m "feat: register content management menu with category management"
```

---

### Task 9: 手动验证

- [ ] **Step 1: 启动开发服务器**

Run: `php think run --host 127.0.0.1`

- [ ] **Step 2: 验证菜单显示**

1. 浏览器打开 `http://127.0.0.1:8000`
2. 登录后台
3. 确认左侧菜单出现"内容管理" → "栏目管理"
4. 点击进入栏目管理页面

- [ ] **Step 3: 验证 CRUD 功能**

1. **添加**：点击"添加栏目"，创建顶级栏目"新闻中心"，填写栏目标识 `news`，保存
2. **子栏目**：点击"新闻中心"行的"添加"按钮，创建子栏目"公司新闻"
3. **三级栏目**：点击"公司新闻"行的"添加"按钮，创建三级栏目"2025年新闻"
4. **三级不可添加**：确认"2025年新闻"行的"添加"按钮为禁用状态
5. **编辑**：点击"公司新闻"行的"编辑"，修改名称，保存
6. **状态切换**：切换"公司新闻"的启用/禁用状态
7. **排序**：修改排序值，确认顺序变化
8. **删除**：删除"2025年新闻"，确认列表中不再显示
9. **软删除验证**：用 MySQL 客户端执行 `SELECT * FROM category WHERE deleted_at IS NOT NULL`，确认记录存在且 `deleted_at` 有值

- [ ] **Step 4: 清理缓存**

Run: `php think clear`

---

### Task 10: 最终提交

- [ ] **Step 1: 确认所有文件已提交**

Run: `git status`
Expected: clean working tree

Run: `git log --oneline -10`
Expected: 看到所有 Task 1-8 的 commit

- [ ] **Step 2: 如有未提交的文件，一并提交**

```bash
git add -A
git commit -m "feat: complete content management module with category CRUD"
```
