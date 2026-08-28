# Security Policy

## 依赖策略

- 仅允许明确审核过的 Composer 依赖。
- 禁止自动覆盖项目文件的 Composer 插件。
- 锁定生产依赖并提交 `composer.lock`。
- 定期执行 `composer audit`。

## 报告问题

请通过 GitHub Security Advisory 私密报告安全问题，不要在公开 Issue 中提交密钥、数据库内容或服务器日志。
