# Composer 发布流程

## GitHub 仓库

计划维护两个仓库：

- `theday-seeyou/web-base-temple`
- `theday-seeyou/web-base-core`

## 核心包发布

1. 将 `packages/web-base-core` 拆分到独立仓库。
2. 执行 `composer validate --strict`。
3. 执行 PHP 语法检查和测试。
4. 创建语义化版本标签，例如 `v0.1.0`。
5. 在 Packagist 提交 GitHub 仓库 URL。
6. 配置 GitHub/Packagist 自动更新。

发布后安装：

```bash
composer require theday-seeyou/web-base-core:^0.1
```

## CMS 发布

核心包发布后，从 CMS 的 `composer.json` 删除本地 `path` repository，并保留正式依赖：

```json
"theday-seeyou/web-base-core": "^0.1"
```

然后提交 `composer.lock`、创建 CMS 版本标签，并在 Packagist 注册。

发布后安装：

```bash
composer create-project theday-seeyou/web-base-temple my-site
```
