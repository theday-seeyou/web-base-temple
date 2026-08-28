# WebBase CMS

WebBase CMS 是面向中小企业官网的中英文内容管理系统，基于 ThinkPHP 8 和自有 Composer 核心包构建。

## 仓库结构

```text
web-base-temple/
├─ app/                         CMS 应用与后台模块
├─ config/                      项目配置
├─ database/migrations/         数据库迁移
├─ public/                      Web 入口与静态资源
├─ packages/web-base-core/      可独立发布的 Composer 核心包
└─ composer.json                CMS 项目定义
```

CMS 包名为 `theday-seeyou/web-base-temple`，核心包名为 `theday-seeyou/web-base-core`。

## 安全原则

- 不启用任何第三方 Composer 插件。
- `composer install` 和 `composer update` 不复制、不删除、不覆盖 `app/`、`config/`、`public/` 或数据库迁移。
- 核心包不包含来源不透明的可执行文件。
- 发布资源必须显式运行 `php think webbase:publish`。
- 发布命令默认保留已有文件；只有显式传入 `--force` 才允许覆盖。
- `composer.lock` 纳入版本控制，生产环境使用 `composer install --no-dev`。

## 本地安装

```bash
git clone https://github.com/theday-seeyou/web-base-temple.git
cd web-base-temple
composer install
php think migrate:run
php think run --host 127.0.0.1
```

访问地址：

- 网站：`http://127.0.0.1:8000`
- 后台：`http://127.0.0.1:8000/admin`

## Composer Create Project

项目发布到 Packagist 后，可以直接创建站点：

```bash
composer create-project theday-seeyou/web-base-temple my-company-site
```

核心包发布后可以独立安装：

```bash
composer require theday-seeyou/web-base-core
```

当前仓库使用 Composer `path` 仓库联调 `packages/web-base-core`。核心包拆分到独立 GitHub 仓库并发布到 Packagist 后，CMS 将改用正式版本号。

## 当前范围

- 后台登录、权限、菜单、系统配置、文件与日志管理。
- 内容栏目基础模型。
- ThinkPHP 多语言能力。
- 后续建设页面、文章、导航、表单、SEO、模板安装与中英文内容工作流。

架构与发布流程见：

- [架构说明](docs/architecture.md)
- [Composer 发布说明](docs/publishing.md)
- [第三方声明](THIRD_PARTY_NOTICES.md)

## 开源协议

项目自身代码使用 MIT License。由第三方开源项目改造的部分遵循其原始许可证，详见 `THIRD_PARTY_NOTICES.md`。
