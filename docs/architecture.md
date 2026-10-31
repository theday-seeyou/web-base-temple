# WebBase 公开架构边界

## 公开发布单元

### WebBase Starter

- Composer 包：`theday-seeyou/web-base-temple`
- 代码位置：仓库根目录
- 职责：提供可通过 `composer create-project` 创建的最小后台应用
- 包含：登录、用户、权限、菜单、配置、文件、日志、队列和静态示例模块

### WebBase Core

- Composer 包：`theday-seeyou/web-base-core`
- 代码仓库：<https://github.com/theday-seeyou/web-base-core>
- 本地开发建议位置：与 Starter 平级的 `web-base-core/` 目录
- 职责：后台基础能力、RBAC、CRUD、软删除、存储、队列和通用服务

Starter 只依赖 Packagist 发布的核心版本。两个仓库独立提交、独立打标签，
安装和更新过程不得删除或覆盖项目源码。

## 私有扩展边界

公开仓库只声明支持普通 Composer 扩展，不记录任何产品级扩展的协议、
数据结构、功能清单或实现细节。

私有扩展必须遵守与公开包相同的安全规则：Composer 安装阶段不得写入
`app/`、`config/`、`database/` 或 `public/`。需要发布资源或执行数据库
变更时，必须提供由开发者显式运行的命令。

## 扩展注册

扩展优先使用 ThinkPHP 的 Composer 服务发现能力注册服务和命令。
公开 Core 只提供通用扩展接口，不包含产品级专用实现。

任何内部设计文档和产品协议都必须保存在私有仓库中，
不得提交到本公开仓库。
