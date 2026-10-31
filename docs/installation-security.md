# 安装、安全升级与部署

本说明对应 Starter 0.4，配套 Core ^0.4，包含 PasswordService、CsrfService 和 UploadPolicy。新项目从 README 的 composer create-project 命令开始；已有项目必须同时升级应用代码、正式依赖和数据库迁移，不能把 0.4 应用文件单独复制到 Core 0.3 上运行。验证脚本 tests/prepare_fixture.py 默认安装锁文件中的正式依赖；仅显式指定 WEBBASE_CORE_SOURCE 时使用本地源码联调。

## 本地环境与 phpStudy

使用 PHP 8.1+、MySQL 5.7/8.0、Composer 2。开启 curl、fileinfo、gd、mbstring、openssl、pdo_mysql；Office 文档校验还需要 zip。phpStudy 可切换 PHP 版本，但 CLI、站点和 Composer 应使用一致的版本。

创建独立的 utf8mb4 数据库，在 .env 填写 phpStudy 实际连接信息。DB_MYSQL_PREFIX 支持空值或 wb_ 等前缀。不要把测试迁移指向已有客户业务库。

```bash
composer install
php think migrate:run
php think admin:init
php think run -H 127.0.0.1 -p 8000
```

admin:init 只在没有任何用户（含回收站用户）的空库中创建配置指定的超级管理员，默认账号 admin、ID 10000。密码以隐藏方式输入，至少 12 字节、最多 72 字节。自动化部署可通过进程环境 WEBBASE_ADMIN_PASSWORD 提供密码，执行后立即从环境移除，不要把密码写进命令参数、仓库或部署日志。命令不会重置已有用户。

## 已有数据库升级

先备份数据库、.env 和上传文件，再安装配套依赖、执行 migrate:run 并清理应用缓存。升级密码列至 varchar(255)，不改写现有密码。MD5 账号第一次成功登录时升级为 bcrypt；旧会话统一重新登录。原有短密码仍可用于这次登录，升级后应通过安全设置改成长密码。

密码变更事件 PluginAdminChangePassword 只发送 uuid，不再发送明文 pass；依赖该字段的插件必须修改。账号禁用、删除和密码变化会在下一次请求使旧登录失效，权限节点在每次后台请求时重新加载。

默认编辑器统一为 wangEditor。新库允许 PDF/DOCX/XLSX 等常见资料类型；升级只替换完全等于旧默认值的配置，保留自定义配置。若要继续使用 CKEditor，可在升级后显式选择。升级前记录原设置。

## 请求与上传约定

后台写操作使用 POST。表单隐藏字段 _token_、User-Form-Token 或 X-CSRF-Token 可携带同一个会话令牌；AJAX 从后台 HTML 的 csrf-token meta 读取令牌，仅发送到同源地址。该令牌可用于并发请求，登录后轮换。脚本接口不返回令牌。独立业务应用若使用 Cookie 登录，应加入 app.csrf_apps；签名 API 需单独设计验证规则，不能用请求头存在与否来跳过后台保护。

CORS 默认关闭。开启时 cors_host 填完整、精确的来源（包含协议与非默认端口），例如 https://admin.example.com；空列表不授权。Cookie 默认 HttpOnly、SameSite=Lax，HTTPS 下 Secure。生产必须使用 HTTPS 传输登录密码。

config/upload.php 默认上限 50 MiB，前端和服务器共同限制。fileinfo 检查实际 MIME，图片校验尺寸，PDF 校验文件头，DOCX/XLSX 校验 ZIP 内的文档结构。支持的类型由 UploadPolicy 定义，后台白名单不能重新开放脚本和密钥文件。新增业务文件类型须同时扩展服务端校验和测试。

云存储默认经应用服务端校验后转发。direct_upload 是显式兼容选项；启用后必须在存储提供方配置等效的大小、类型与上传后扫描策略，浏览器直传无法使用本地文件内容校验。该分支不属于本轮本地存储验收范围。

登录频率限制保存在 runtime/security/ 下，跨会话有效，依靠文件锁保证单机并发计数。多台应用服务器部署前须替换为共享原子计数实现，不应通过清理 runtime/security 来日常解除限流。

## 生产部署

部署期间暂停写入，执行 composer install --no-dev --classmap-authoritative、migrate:run。Web 根目录必须指向 public/，运行用户仅对 runtime/、上传目录和配置使用的安全文件目录拥有所需写权限。不要对整个项目设置 777。PHP upload_max_filesize 至少 50M、post_max_size 至少 52M，Nginx client_max_body_size 与之匹配。

Nginx 示例（PHP-FPM 地址按环境调整）：

```nginx
root /srv/webbase/public;
index index.php;
client_max_body_size 52m;
location / { try_files $uri $uri/ /index.php?s=$uri&$args; }
location ^~ /upload/ { try_files $uri =404; }
location ~ /\. { deny all; }
location ~ \.php$ {
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

配置 HTTPS 和生产运行模式后，复查登录、权限、分类新增/编辑/回收站、资料上传与公开下载。备份必须包含数据库和文件；涉及 DDL 的升级失败不能只回退代码，需要按备份和迁移记录恢复。

## 自动化验证

Core 有密码、CSRF、验证码、限流和上传策略回归测试。Starter tests/integration.py 覆盖实际 HTTP 登录、权限刷新、账号新增/改密、会话失效、分类 CRUD/回收站和 PDF 上传保存下载。测试辅助程序只接受 CLI、WEBBASE_TEST_ISOLATED=1 以及 webbase_test_* / webbase_verify_* 数据库，且位于 public/ 外。

GitHub 工作流使用独立 MySQL 服务，在 PHP 8.1/8.2/8.3 上通过 composer install 安装正式锁定依赖，再验证迁移、后台操作和打包的 CSS/JS。发布后还须从无自定义 repositories 的空目录执行 composer create-project 验收，步骤见 publishing.md。
