# 内容管理模块设计文档

## 概述

在 ThinkAdmin 后台新增"内容管理"一级菜单，首个子模块为"栏目管理"（三级树状分类）。

## 1. BaseModel（新模型基类）

### 文件位置
`app/admin/model/BaseModel.php`

### 设计说明
- 继承 `think\admin\Model`，引入 `SoftDelete` trait
- 所有 admin 模块下的新模型继承此类
- 查询自动过滤 `WHERE deleted_at IS NULL`
- 删除自动写入 `deleted_at = 当前时间`
- 重写 `mDelete()` 方法，支持 `deleted_at` 单字段软删除

### 代码结构
```php
<?php
namespace app\admin\model;

use think\admin\helper\DeleteHelper;

class BaseModel extends \think\admin\Model
{
    use \think\model\concern\SoftDelete;

    protected $deleteTime = 'deleted_at';
    protected $defaultSoftDelete = null;

    /**
     * 快捷删除（支持 deleted_at 单字段）
     */
    public static function mDelete(string $field = '', $where = [])
    {
        return DeleteHelper::instance(static::class)->init(static::class, $field, $where);
    }
}
```

### 框架方法兼容性
| 方法 | 兼容性 | 说明 |
|------|--------|------|
| `mQuery()` | ✅ 天然兼容 | SELECT 自动追加 `WHERE deleted_at IS NULL` |
| `mForm()` | ✅ 天然兼容 | GET/POST 自动过滤已删记录 |
| `mSave()` | ✅ 天然兼容 | UPDATE 自动跳过已删记录 |
| `mUpdate()` | ✅ 天然兼容 | 同 mSave |
| `mDelete()` | ⚠️ 需重写 | 走自定义 DeleteHelper |

---

## 2. DeleteHelper（自定义删除助手）

### 文件位置
`app/admin/helper/DeleteHelper.php`

### 设计说明
- 继承 `think\admin\Helper`
- 逻辑与 vendor 的 `DeleteHelper` 一致，但软删除检测改为 `deleted_at`
- 表有 `deleted_at` 字段 → 写入时间戳（软删除）
- 表无 `deleted_at` 字段 → 硬删除

### 代码结构
```php
<?php
namespace app\admin\helper;

use think\admin\Helper;
use think\db\BaseQuery;
use think\db\exception\DbException;

class DeleteHelper extends Helper
{
    public function init($dbQuery, string $field = '', $where = [])
    {
        $query = static::buildQuery($dbQuery);
        $field = $field ?: ($query->getPk() ?: 'id');
        $value = $this->app->request->post($field);

        if (!empty($where)) {
            $query->where($where);
        }
        if (!isset($where[$field]) && is_string($value)) {
            $query->whereIn($field, str2arr($value));
        }

        if ($this->class->callback('_delete_filter', $query, $where) === false) {
            return false;
        }

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
        $result = empty($data) ? $query->delete() : $query->update($data);

        if ($result !== false) {
            $model = $query->getModel();
            if ($model instanceof \think\admin\Model) {
                $model->onAdminDelete(strval($value));
            }
        }

        if ($this->class->callback('_delete_result', $result) === false) {
            return $result;
        }

        if ($result !== false) {
            $this->class->success('数据删除成功！', '');
        } else {
            $this->class->error('数据删除失败！');
        }
    }
}
```

---

## 3. Category 模型

### 文件位置
`app/admin/model/Category.php`

### 数据库表：`category`

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `id` | int | auto_increment | 主键 |
| `pid` | int | 0 | 上级栏目 ID，0 = 顶级 |
| `title` | varchar(100) | - | 栏目名称 |
| `code` | varchar(50) | NULL | 栏目标识（唯一，用于程序调用） |
| `icon` | varchar(100) | NULL | 栏目图标 |
| `cover` | varchar(255) | NULL | 封面图路径 |
| `desc` | text | NULL | 描述/备注 |
| `sort` | int | 0 | 排序值，越小越靠前 |
| `status` | tinyint | 1 | 状态：1 启用，0 禁用 |
| `deleted_at` | datetime | NULL | 软删除时间，NULL = 未删除 |
| `create_at` | datetime | - | 创建时间 |
| `update_at` | datetime | - | 更新时间 |

### 索引
- `idx_pid` (pid)
- `uniq_code` (code) — 唯一索引

### 代码结构
```php
<?php
namespace app\admin\model;

class Category extends BaseModel
{
    protected $name = 'category';

    // 栏目名称
    protected $oplogType = '栏目管理';
    protected $oplogName = '栏目';
}
```

---

## 4. Category 控制器

### 文件位置
`app/admin/controller/Category.php`

### 动作设计
| 方法 | 说明 | 框架方法 |
|------|------|----------|
| `index()` | 树形表格列表 | `mQuery()->layTable()` |
| `add()` | 新增栏目 | `mForm('form')` |
| `edit()` | 编辑栏目 | `mForm('form')` |
| `state()` | 状态切换 | `mSave()` |
| `remove()` | 删除（软删除） | `mDelete()` |

### 代码结构
```php
<?php
namespace app\admin\controller;

use think\admin\Controller;
use think\admin\extend\DataExtend;
use app\admin\model\Category;

class Category extends Controller
{
    /**
     * 栏目列表
     * @auth true
     * @menu true
     */
    public function index()
    {
        $this->title = '栏目管理';
        Category::mQuery()->layTable();
    }

    /**
     * 添加栏目
     * @auth true
     */
    public function add()
    {
        Category::mForm('form');
    }

    /**
     * 编辑栏目
     * @auth true
     */
    public function edit()
    {
        Category::mForm('form');
    }

    /**
     * 修改栏目状态
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
     * 删除栏目
     * @auth true
     */
    public function remove()
    {
        Category::mDelete();
    }

    /**
     * 列表数据处理（树形结构）
     */
    protected function _index_page_filter(array &$data)
    {
        $data = DataExtend::arr2table(DataExtend::arr2tree($data));
    }

    /**
     * 表单数据处理
     */
    protected function _form_filter(array &$vo)
    {
        if ($this->request->isGet()) {
            // 上级栏目选择
            $vo['pid'] = $vo['pid'] ?? input('pid', '0');
            $categories = Category::mk()->order('sort asc,id asc')->column('id,pid,title', 'id');
            $this->categories = DataExtend::arr2table(
                array_merge($categories, [['id' => '0', 'pid' => '-1', 'title' => '顶级栏目']])
            );
            // 限制三级
            foreach ($this->categories as $key => $cat) {
                if ($cat['spt'] >= 3) {
                    unset($this->categories[$key]);
                }
            }
        }
    }
}
```

---

## 5. 视图文件

### 列表页：`app/admin/view/category/index.html`

继承 `table` 布局，使用 `layTable` 组件展示树形表格。

**关键点：**
- `page: false` — 树形数据不分页
- `sort: {field: 'sort asc,id', type: 'asc'}` — 按排序值排序
- 使用 `DataExtend::arr2table()` 处理后的数据，通过 `spl` 字段显示缩进
- 操作列限制：三级栏目不能再添加子栏目

### 表单页：`app/admin/view/category/form.html`

继承 `main` 布局，LayUI 表单。

**字段：**
- 上级栏目（下拉选择，限制三级）
- 栏目名称（必填）
- 栏目标识（可选，唯一）
- 栏目图标（可选，图标选择器）
- 封面图（可选，图片上传）
- 描述/备注（可选，文本域）
- 排序（数字输入）

---

## 6. 菜单注册

在 `app/admin/Service.php` 的 `menu()` 方法中新增：

```php
[
    'name' => '内容管理',
    'subs' => [
        ['name' => '栏目管理', 'icon' => 'layui-icon layui-icon-app', 'node' => 'admin/category/index'],
    ],
],
```

---

## 7. 开发规范（记录到 CLAUDE.md）

### 后台管理模块规范
- 后台管理的内容都放在 `admin` 模块下
- 所有新模型继承 `app\admin\model\BaseModel`（自带软删除）
- 新模型统一使用 `deleted_at` 字段 + `SoftDelete` trait
- 控制器使用框架快捷方法：`mQuery()`、`mForm()`、`mSave()`、`mDelete()`

### 树形分类规范
- 使用 `pid` 字段实现父子关系，0 表示顶级
- 使用 `DataExtend::arr2tree()` 转树形结构
- 使用 `DataExtend::arr2table()` 转平铺表格（带缩进）
- 限制三级深度，`spt >= 3` 的节点不能再添加子节点
