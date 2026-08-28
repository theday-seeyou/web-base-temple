# WebBase 架构

## 两个发布单元

### WebBase CMS

- Composer 包：`theday-seeyou/web-base-temple`
- 代码位置：仓库根目录
- 职责：企业站后台、内容模型、中英文站点、模板和最终网站交付

### WebBase Core

- Composer 包：`theday-seeyou/web-base-core`
- 代码位置：`packages/web-base-core`
- 职责：后台基础能力、RBAC、CRUD、存储、队列和通用服务

## 模板系统方向

模板包不使用 Composer plugin。模板安装由 CMS 命令读取清单并执行：

1. 校验模板兼容版本和文件哈希。
2. 列出将新增、冲突和可更新的文件。
3. 默认只新增，不覆盖。
4. 冲突时停止并输出报告。
5. 只有显式 `--force` 才覆盖，并在覆盖前生成备份。
6. 内容演示数据与文件资源分开导入。

建议模板结构：

```text
template-package/
├─ template.json
├─ views/
├─ assets/
├─ locales/
│  ├─ zh-cn/
│  └─ en-us/
└─ seed/
```

## 多语言方向

- 后台界面语言使用 ThinkPHP 语言包。
- 业务内容使用同一内容实体加语言版本表。
- URL、SEO 标题、描述、正文和导航均可独立翻译。
- 模板只定义展示结构，不直接保存业务内容。
