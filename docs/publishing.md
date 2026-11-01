# Composer 发布流程

WebBase 使用两个独立 GitHub 与 Packagist 包：

- Starter：`theday-seeyou/web-base-temple`
- 核心包：`theday-seeyou/web-base-core`

## 0.5 配套版本规则

标准时间字段、迁移模板和密码校验要求 Starter 与 Core 配套。当前 Starter 依赖 **Core ^0.5.1**，使用全新安装基线，不自动转换旧数据库。先发布 Core 并确认 Packagist 可安装，再更新 Starter 依赖与 composer.lock。

公开 manifest 与锁文件只使用正式 Packagist 版本。tests/prepare_fixture.py 默认复制锁文件并以 composer install 验证正式依赖；需要联调未发布 Core 时可显式指定 WEBBASE_CORE_SOURCE，但临时 path 配置和锁文件不能提交到 Starter。

发布后必须在无自定义 repositories 的空目录执行 create-project，连接独立数据库，执行迁移、admin:init 和 HTTP 集成测试。若正式依赖缺少 PasswordService/CsrfService，应停止安装，不允许通过修改 vendor 文件绕过。

## 发布核心包

在 `web-base-core` 仓库执行：

1. `composer validate --strict`
2. PHP 语法检查和自动化测试
3. 提交代码并推送 `main`
4. 创建新的语义化版本标签，本轮为 `v0.5.1`，不改写已有标签
5. 推送标签，等待 Packagist 自动更新

安装指定版本：

```bash
composer require theday-seeyou/web-base-core:^0.5
```

## 发布 Starter

在 `web-base-temple` 仓库执行：

1. 将 `composer.json` 中的核心包约束更新到已发布版本。
2. 执行 `composer update theday-seeyou/web-base-core --with-all-dependencies`。
3. 执行迁移、后台登录页和最小启动测试。
4. 提交 `composer.json` 与 `composer.lock`。
5. 创建 Starter 版本标签并推送。
6. 等待 Packagist 自动更新。

使用临时目录验证正式发布包：

```bash
composer create-project theday-seeyou/web-base-temple webbase-release-test "^0.5"
```

测试目录中不得存在自定义 `repositories` 或本地 `path` 配置，确保安装内容确实来自 Packagist。
