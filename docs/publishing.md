# Composer 发布流程

WebBase 使用两个独立 GitHub 与 Packagist 包：

- CMS：`theday-seeyou/web-base-temple`
- 核心包：`theday-seeyou/web-base-core`

## 发布核心包

在 `web-base-core` 仓库执行：

1. `composer validate --strict`
2. PHP 语法检查和自动化测试
3. 提交代码并推送 `main`
4. 创建语义化版本标签，例如 `v0.3.0`
5. 推送标签，等待 Packagist 自动更新

安装指定版本：

```bash
composer require theday-seeyou/web-base-core:^0.3
```

## 发布 CMS

在 `web-base-temple` 仓库执行：

1. 将 `composer.json` 中的核心包约束更新到已发布版本。
2. 执行 `composer update theday-seeyou/web-base-core --with-all-dependencies`。
3. 执行迁移、后台登录页和最小启动测试。
4. 提交 `composer.json` 与 `composer.lock`。
5. 创建 CMS 版本标签并推送。
6. 等待 Packagist 自动更新。

使用临时目录验证正式发布包：

```bash
composer create-project theday-seeyou/web-base-temple webbase-release-test "^0.3"
```

测试目录中不得存在自定义 `repositories` 或本地 `path` 配置，确保安装内容确实来自 Packagist。
