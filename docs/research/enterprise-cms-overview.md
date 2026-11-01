# 企业网站 CMS 公开选型调研

调研日期：2026-11-01。

本文整理第三方产品的公开能力、差异和选型依据，不定义 WebBase 私有产品的功能清单、
模型协议、模板协议、数据结构或开发计划。相关边界见 [公开架构说明](../architecture.md)。

## 1. 企业网站所说的基础 CMS 是什么

CMS（Content Management System，内容管理系统）让编辑人员通过后台维护网站内容，
再由网站页面或 API 将已发布的内容交给访问者。企业官网常见内容包括公司介绍、新闻、
产品、案例、服务和招聘，具体组合由项目决定。

讨论选型时，需要区分以下对象：

| 对象 | 解决的问题 | 典型产物 |
| --- | --- | --- |
| 后台开发框架 | 登录、权限、CRUD、上传、日志、数据库和运行时能力 | 开发者可扩展的后台底座 |
| 传统或混合 CMS | 内容建模、编辑、分类、发布、媒体和页面呈现 | 可以由运营人员持续维护的网站 |
| Headless CMS | 结构化内容、编辑后台、权限及内容 API | 给独立网站、App 等使用的内容服务 |
| 可视化建站平台 | 模板选择、页面编辑、内容绑定及托管发布 | 一体化的网站制作与发布体验 |

Headless CMS 通常仍需要配套的前端页面和部署方案；传统 CMS 也可能提供内容 API。
“支持模板”“可以自托管”“可以用 Composer 安装”是三个独立条件。

## 2. 代表性产品比较

以下是有代表性的研究样本，**不是市场占有率或安全性排名**。
“值得研究”表示公开设计和使用体验可供分析，不表示建议直接采用其源码。

| 产品 | 类型与技术方向 | 官方资料可确认的能力 | 选型时需要考虑 |
| --- | --- | --- | --- |
| WordPress | PHP，内容系统与主题/插件生态 | 自定义内容类型可生成独立管理入口；内容类型建议由插件注册，以便换主题后保留内容 [S1] | 双语内容目前依赖插件等方案，不能把后台语言设置当成内容翻译；核心及衍生作品有 GPL 条件 [S2][S3] |
| Drupal | PHP，结构化内容平台 | 内容实体和字段；界面、配置、内容三种翻译对象分别管理 [S4][S5] | 需要规划内容结构和编辑流程；实施复杂度应通过具体项目验证 |
| PbootCMS | PHP，面向企业建站 | 官方列出内容模型、自定义字段、语言/区域、表单、幻灯片、模板标签、自定义 URL [S6] | 官网的免费商用声明包含域名授权条件；不能由“免费”推导成可任意改名分发 |
| 迅睿 CMS | PHP，企业建站与应用扩展 | 官网列出栏目、内容、模板、权限、API、应用市场，并声明框架 MIT 商用 [S7] | 框架、插件、模板、服务须分别检查授权和版本，不能把市场插件都视为内置免费功能 |
| Statamic | Laravel/PHP，内容建模与定制网站 | Collections、Blueprints、导航、分类、多站点/本地化和 Starter Kits [S8–S11] | Core 与 Pro 分开授权；Starter Kit 通常用于新站起步，官方明确说明多数面向空站 [S12][S13] |
| Craft CMS | PHP，定制内容与编辑体验 | 自定义字段、关系、资源、预览、Twig、GraphQL、多站点和字段翻译 [S14][S15] | 有不同授权版本；Composer 创建的是完整 Craft 应用 [S16] |
| October CMS | Laravel/PHP，主题与内容蓝图 | Tailor 通过 Blueprint 定义内容并生成管理界面；有 Entry、Global、Mixin 和多站点能力 [S17][S18] | 本次核验的是 3.x 文档；安装文档要求许可证，不能按历史版本的授权印象选型 [S19] |
| Strapi | Node.js，Headless CMS | 内容类型/字段本地化；各语言独立编辑、发布；Draft & Publish 区分草稿与线上内容 [S20][S21] | 需要独立前端和 Node.js 运维；特定功能与商用版本范围需按部署版本核对 |
| Directus | 数据库驱动的内容/数据管理平台 | 关系建模、翻译关联、内容版本和修订历史 [S22][S23] | 当前官网许可证含竞争用途等限制，历史版本条款不同；不应笼统认作 MIT 产品 [S24] |
| Payload | TypeScript，代码配置驱动的 CMS | 字段级内容本地化，明确区分数据翻译与界面 I18n [S25] | 偏开发者配置与独立前端集成；与 PHP 应用属于不同技术运行栈 |
| Framer | 可视化建站平台 | CMS 集合、模板市场、语言本地化、本地化页面路径 [S26–S29] | 适合研究模板及编辑体验；模板使用、平台服务与自托管 PHP 源码交付分别核验 |

Statamic、Craft、October 都能进入 PHP/Composer 开发生态，但各自有完整的应用框架和
运行约定。Composer 安装方式不代表它们可以直接作为一个 ThinkPHP CMS 插件使用。

## 3. 调研中最有参考价值的区别

### 内容与主题的关系

WordPress 官方建议把自定义内容类型放在插件里，让内容可以随主题更换继续使用 [S1]。
这是内容独立于表现层的一个具体例子。

Statamic 官方则明确说明，Starter Kit 是可以修改和继续开发的建站起点，可能携带自己
独有的内容结构；它与可以互换的 WordPress 主题有不同定位 [S11][S13]。
因此，比较“安装模板”时，应分别核验：新站初始化、已有站换外观、内容导入和版本更新。

### 界面翻译与内容翻译

Drupal 将界面、配置、内容翻译分开说明 [S5]；Payload 也明确区分界面 I18n 与内容数据
Localization [S25]。WordPress 的官方多语言页面说明，完整双语内容并非核心开箱即用 [S2]。

选型演示至少应确认：文章是否有独立翻译，当前语言是否可以单独发布，语言切换能否到达
同一内容的对应译文，以及 URL、页面标题和 SEO 是否随语言变化。Framer 的本地化路径
文档展示了页面身份保持关联、路径按语言变化及路径冲突提示等行为 [S29]。

### 草稿、线上版本与修订历史

Strapi 的 Draft & Publish 用于区分草稿与线上条目 [S21]；Directus 的内容版本可以保存
修改而不影响当前发布内容，Revision 则记录更细的修改历史 [S23]。
后台表单中的普通保存、公开发布和历史版本恢复，应分别验证其效果。

### 多语言、多站点与多租户

Statamic 官方多站点文档明确将其定位于同一网站的不同语言、地区、分区或域名变体，
并说明它不面向完全独立网站的多租户应用 [S10]。Craft 也将多站点定位于同一发布团队
管理的站点 [S15]。不能由“支持多个语言/域名”推导为有租户隔离能力。

## 4. 按项目条件选择研究重点

| 项目条件 | 优先评估的方向 | 演示中重点验证 |
| --- | --- | --- |
| 要用成熟主题/插件快速交付 | WordPress、国内企业 CMS | 主题与内容绑定、多语言依赖、升级后已有内容与定制保留情况 |
| PHP 团队做高度定制企业官网 | Statamic、Craft、October | 内容模型、运营表单、多语言、预览及 Composer 项目生命周期 |
| 多内容团队、复杂内容组织 | Drupal 等结构化内容平台 | 角色、翻译分工、内容结构和实际编辑流程 |
| 网站/App 共用内容服务 | Strapi、Directus、Payload | 内容 API、数据权限、版本与前端预览集成 |
| 首先关注设计和模板使用体验 | Framer | 模板预览、内容绑定、语言切换、响应式和发布流程 |

此表是研究路线，不是性能或成本结论。正式选型仍需用相同的内容样例完成一次实际建站，
核对运维、编辑体验、许可、升级和导出需求。

## 5. 与当前公开 Starter 的关系

当前 Starter 的公开范围是通用后台、示例模块、公开首页和语言切换演示。
完整 CMS 还涉及内容与发布管理。演示首页的语言 Cookie 不能代表所有业务内容已经具备
翻译管理，数据库模型版本也不能代表编辑内容的历史修订已经实现。

本次仅做公开资料调研；未安装或运行这些 CMS、未引入第三方主题/插件代码，也未修改
现有项目依赖、数据库或部署配置。产品实现的内部分析另行保存在私有工作区。

## 6. 授权与资料时效

- WordPress 官网声明 GPLv2 或更高版本，并说明其对衍生作品的立场 [S3]。
- Statamic 区分免费开源 Core 和商业 Pro，也有独立的付费扩展/Starter Kit 授权 [S12][S13]。
- Craft 的功能与授权版本有关；October 本次核验版本的安装流程需要 License Key [S14][S19]。
- PbootCMS 官网的授权声明、迅睿框架 MIT 声明，不自动覆盖第三方模板及插件 [S6][S7]。
- 调研当日 Directus 官网许可证页面显示 **MSCL-1.0-GPL**，含竞争用途、许可证保护和
  再分发条款，并有四年后的 GPL-3.0 授权安排；历史版本应检查各自附带的许可证 [S24]。

本文不锁定商用价格，不对项目作法律结论。实际采用、复制、改造或分发前，需核对目标
版本附带的 LICENSE、第三方声明及素材授权。研究功能和交互无需复制对方实现。

## 7. 已核验的官方来源

以下正文已在调研时读取；HTML 文档以官方最终页面为准。部分产品有版本化文档，
本文不把某个版本的行为推广到所有历史或未来版本。

- **[S1]** [WordPress：Registering Custom Post Types](https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/)
- **[S2]** [WordPress：Multilingual WordPress](https://developer.wordpress.org/advanced-administration/wordpress/multilingual/)
- **[S3]** [WordPress：License](https://wordpress.org/about/license/)
- **[S4]** [Drupal：Content Entities and Fields](https://www.drupal.org/docs/user_guide/en/planning-data-types.html)
- **[S5]** [Drupal：User Interface, Configuration, and Content Translation](https://www.drupal.org/docs/user_guide/en/language-concept.html)
- **[S6]** [PbootCMS 官网：功能与授权声明](https://www.pbootcms.com/)
- **[S7]** [迅睿 CMS 官网：产品、扩展与框架授权声明](https://www.xunruicms.com/)
- **[S8]** [Statamic 6：Collections](https://statamic.dev/content-modeling/collections)
- **[S9]** [Statamic 6：Blueprints](https://statamic.dev/content-modeling/blueprints)
- **[S10]** [Statamic 6：Multi-Site](https://statamic.dev/control-panel/multi-site)
- **[S11]** [Statamic 6：Starter Kits Overview](https://statamic.dev/starter-kits/overview)
- **[S12]** [Statamic 6：Licensing](https://statamic.dev/getting-started/licensing)
- **[S13]** [Statamic 6：Installing a Starter Kit](https://statamic.dev/starter-kits/installing-a-starter-kit)
- **[S14]** [Craft CMS：Features](https://craftcms.com/features)
- **[S15]** [Craft CMS 5：Sites](https://craftcms.com/docs/5.x/system/sites.html)
- **[S16]** [Craft CMS 5：Installation](https://craftcms.com/docs/5.x/install)
- **[S17]** [October CMS 3.x：Tailor Introduction](https://docs.octobercms.com/3.x/cms/tailor/introduction.html)
- **[S18]** [October CMS 3.x：Multisite](https://docs.octobercms.com/3.x/cms/resources/multisite.html)
- **[S19]** [October CMS 3.x：Installation](https://docs.octobercms.com/3.x/setup/installation.html)
- **[S20]** [Strapi：Internationalization](https://docs.strapi.io/cms/features/internationalization)
- **[S21]** [Strapi：Draft & Publish](https://docs.strapi.io/cms/features/draft-and-publish)
- **[S22]** [Directus：Relationships / Translations](https://directus.io/docs/guides/data-model/relationships)
- **[S23]** [Directus：Content Versioning](https://directus.com/docs/guides/content/content-versioning)
- **[S24]** [Directus：License](https://directus.com/license)
- **[S25]** [Payload：Localization](https://payloadcms.com/docs/configuration/localization)
- **[S26]** [Framer：CMS](https://www.framer.com/cms/)
- **[S27]** [Framer：Templates](https://www.framer.com/marketplace/templates/)
- **[S28]** [Framer：Localization](https://www.framer.com/features/localization/)
- **[S29]** [Framer：Localized Page Paths](https://www.framer.com/help/articles/localized-page-paths/)
