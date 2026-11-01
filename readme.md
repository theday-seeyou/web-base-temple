# WebBase Starter

WebBase Starter 是基于 ThinkPHP 8 和自有 Composer 核心包
`theday-seeyou/web-base-core` 构建的后台应用起始项目。

本仓库只提供通用后台基础能力。产品级站点能力由独立的私有扩展提供，
不属于本公开仓库。

## 环境要求

- PHP 8.1 或更高版本。
- MySQL 5.7 / 8.0 或兼容的 MariaDB。
- Composer 2。
- PHP 扩展：`curl`、`fileinfo`、`gd`、`iconv`、`json`、`mbstring`、`openssl`、`pdo_mysql`、`zlib`；上传 DOCX/XLSX 还需要 `zip`。

## 创建新项目

推荐直接使用 Composer 创建独立站点：

```bash
composer create-project theday-seeyou/web-base-temple my-admin-project "^0.5"
cd my-admin-project
```

创建本地环境配置。

Windows PowerShell：

```powershell
Copy-Item .env.example .env
```

macOS / Linux：

```bash
cp .env.example .env
```

编辑 `.env`，至少填写数据库连接：

```dotenv
DB_MYSQL_HOST=127.0.0.1
DB_MYSQL_PORT=3306
DB_MYSQL_USERNAME=root
DB_MYSQL_DATABASE=webbase_app
DB_MYSQL_PASSWORD=your-password
```

创建一个使用 `utf8mb4` 的空数据库：

```sql
CREATE DATABASE `webbase_app`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

执行初始化迁移：

```bash
php think migrate:run
php think admin:init
```

启动开发服务器：

```bash
php think run -H 127.0.0.1 -p 8000
```

访问：

- 网站：`http://127.0.0.1:8000/`
- 后台：`http://127.0.0.1:8000/admin`
- 初始账号：`admin`
- 初始密码：由 `admin:init` 交互输入，不提供固定默认密码。

密码须为 12–72 字节。已有账号不会被初始化命令覆盖。若 8000 端口已被占用，可将 `-p 8000` 改为其他端口。

0.5 系列使用配套的 Core 0.5，仅面向全新安装。密码使用 bcrypt，不支持 MD5 账号，
不转换旧数据库的删除标记或时间字段。请使用独立空数据库初始化，详见
[安装、安全与部署](docs/installation-security.md)。

## 默认表结构与模型

所有默认应用表以及通过 `createTable()` 创建的新表都包含以下字段：

| 字段 | 数据库类型 | 含义与默认行为 |
| --- | --- | --- |
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | 自增主键 |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | 创建时间，新增记录时写入 |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | 更新时间，更新记录时写入 |
| `deleted_at` | `DATETIME NULL DEFAULT NULL` | 删除时间，NULL 表示未删除 |

`BaseModel` 默认开启自动时间写入和软删除；普通查询排除已删除数据，回收站查询只返回
`deleted_at IS NOT NULL` 的记录，恢复时将该字段设回 `NULL`。`Model` 提供相同的创建、
更新时间约定，供需要物理删除的基础设施模型显式使用。迁移工具自身的 `migrations`
记录表由 Phinx 管理，不属于应用表。

创建迁移文件：

```bash
php think migrate:create CreateArticles
```

默认生成的迁移继承 `WebBase\Core\migration\Migrator`。在 `change()` 中只需定义业务字段：

```php
$this->createTable('articles')
    ->addColumn('title', 'string', ['limit' => 200])
    ->create();
```

`createTable()` 自动添加上述四个系统字段和删除时间索引。调整现有表时使用
`$this->table('articles')->...->update()`；数据库变更仍需显式执行 `php think migrate:run`。

后台用户、角色、菜单、数据字典、附件记录和分类示例均默认使用软删除，提供正常列表、
回收站、恢复及永久删除。菜单的禁用状态独立于删除状态；恢复角色会保留其原有授权。
附件回收和永久删除只处理数据库记录，不删除可能仍被文章引用的实际文件。
任务、操作日志、配置和内部关联数据按各自的显式维护操作处理，详见
[后台 CRUD 与回收站](docs/crud-recycle-editor.md)。

## 默认首页与中英文切换

`/` 默认显示一个简单的公开首页，无需登录。可通过页面右上角的“中文 / English”切换：

- 中文：`/?lang=zh-cn`
- 英文：`/?lang=en-us`

语言选择优先级为 URL 的 `lang` 参数、`site_lang` Cookie、`config/site.php` 中的
默认语言。Cookie 保存 30 天，刷新或重新打开首页会保留选择；非法语言回到默认中文。
页面同步切换标题、描述、正文、导航、页脚和 HTML `lang`，响应包含 `Content-Language`。
网站语言与后台语言独立，后台当前默认中文。

修改首页模板：`app/index/view/index/index.html`；样式：`public/static/site/index.css`；
中文文案：`app/index/lang/zh-cn.php`；英文文案：`app/index/lang/en-us.php`。
这仅是起始首页，不包含私有 CMS 的内容翻译、动态模型或模板安装能力。

## 从 GitHub 开发

需要参与 Starter 开发时，也可以直接克隆：

```bash
git clone https://github.com/theday-seeyou/web-base-temple.git
cd web-base-temple
composer install
```

然后按照上面的步骤创建 `.env`、数据库并执行迁移。

核心包位于独立仓库：

- GitHub：<https://github.com/theday-seeyou/web-base-core>
- Packagist：<https://packagist.org/packages/theday-seeyou/web-base-core>

Starter 始终通过正式版本依赖核心包，不使用本地 `path` 仓库，
也不会在 Composer 安装阶段覆盖项目源码。

## 常用命令

```bash
php think migrate:status
php think migrate:run
php think route:list
php think clear
composer audit
```

WebBase Core 提供以下显式命令：

```bash
php think webbase:publish
php think webbase:database
php think webbase:queue
php think webbase:sysmenu
```

`webbase:publish` 默认不会覆盖已有文件。只有开发者显式传入 `--force` 时才允许覆盖。

## 生产部署

```bash
composer install --no-dev --classmap-authoritative
php think migrate:run --no-interaction
```

Web 服务器的站点根目录必须指向 `public/`。不要把 `.env`、源码根目录或数据库凭据暴露到公网。

## 项目范围

- 后台登录、权限、菜单、系统配置、文件与日志管理。
- 通用 CRUD、`deleted_at` 软删除与回收站。
- 存储、上传、队列和数据库迁移基础设施。
- 独立的中英文公开首页，后台默认中文。
- 一个用于演示树形数据、CRUD 和回收站的静态示例模块。

产品级扩展的协议、数据结构、源码和内部设计不在本公开仓库中维护。

架构与发布流程：

- [架构说明](docs/architecture.md)
- [后台 CRUD、回收站与富文本编辑器](docs/crud-recycle-editor.md)
- [Composer 发布说明](docs/publishing.md)
- [安装、安全升级与部署](docs/installation-security.md)
- [0.4.0 正式安装验收](docs/releases/0.4.0.md)
- [0.5.0 标准字段与全新安装验收](docs/releases/0.5.0.md)
- [0.5.1 后台与中英文首页验收](docs/releases/0.5.1.md)
- [第三方声明](THIRD_PARTY_NOTICES.md)

## 开源协议

项目自身代码使用 MIT License。由第三方开源项目改造的部分遵循其原始许可证，详见 `THIRD_PARTY_NOTICES.md`。
