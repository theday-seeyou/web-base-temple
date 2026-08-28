# WebBase 开发约定

## 项目边界

- 仓库根目录是 CMS 项目。
- `packages/web-base-core` 是可独立发布的 Composer 核心包。
- CMS 业务代码使用 `app\\` 命名空间。
- 核心包使用 `WebBase\\Core\\` 命名空间。

## Composer 安全规则

- 禁止新增会在安装阶段修改项目源码的 Composer plugin。
- Composer 脚本只能生成 `vendor/` 内的自动加载或服务发现文件。
- 任何向 `app/`、`config/`、`database/`、`public/` 写入的命令必须由开发者显式调用。
- 覆盖已有文件必须要求显式 `--force`，并在实现中列出冲突文件。
- 生产发布必须提交 `composer.lock`。

## 发布规则

- 核心包使用语义化版本，例如 `v0.1.0`。
- CMS 只依赖已发布的稳定核心版本；本仓库联调阶段可使用 `path` 仓库。
- 发布前执行 Composer 校验、PHP 语法检查和最小启动测试。
- 第三方许可证与版权声明不得删除。
