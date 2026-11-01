# 后台全局语言

后台语言属于公共布局。选择器与全局设置入口位于顶部，不作为某个模块的子菜单。
顶部基于 Layui 按钮组显示连体的“中文 / EN”分段控件，两项共用浅灰底、外框和圆角。
当前语言使用深青色实心高亮、白字和勾选标记，另一项为灰底文字，避免仅凭颜色区分。
每段等宽 60px，外框高 36px，垂直点击区域扩展至 44px；切换前后控件宽度保持一致。
点击另一分段、Enter 或空格键直接切换；原生按钮通过 `aria-pressed` 表达当前选择。
仅显示已启用的语言；仅启用一种语言时保留当前高亮分段并禁用切换。
提交期间锁定控件；取消未保存提示或保存失败时保持原语言及高亮状态。
本轮实现兼容已发布的 Core 0.5.1，应用代码与 UI 放在 Starter，Composer 安装不会发布或覆盖文件。

## 配置与持久化

`config/admin_language.php` 定义受支持语言包、初始启用语言和初始默认语言。
顶部设置页只允许超级管理员访问，复用 `SystemService` 将设置保存为 `AdminLanguageSettings`。
普通管理员可以选择已启用语言，个人偏好通过 `AdminService::setUserData()` 存入 `admin_locale`，
与主题等现有用户扩展数据合并，刷新、重新登录、进入其他模块后仍保留。

个人选择不改变默认语言或其他账号的偏好。未选择语言或偏好已停用时回退到全局默认语言。
至少启用一种语言，默认值必须属于启用集合。停用语言不删除内容或个人偏好；重新启用时
原偏好可以继续使用。这里配置的是后台语言，不修改前台 `config/site.php` 与 `site_lang`。

## 请求上下文

`app/admin/middleware.php` 注册 `app\admin\middleware\AdminLanguage`，在会话初始化后、
权限和控制器执行前加载当前语言。语言包继续使用框架的 `lang()`；扩展控制器可通过
`app()->lang->getLangSet()` 读取经过验证的当前语言，不自行读取 URL 或 Cookie 决定编辑语言。
其他后台应用可注册同一个中间件，复用公共后台布局。

响应包含 `Content-Language` 和 `Cache-Control: private, no-store`。公共脚本向同源异步请求
附加 `X-Admin-Language`；它与服务端当前偏好不一致时返回 409，提示刷新。该请求不会继续
执行控制器，防止多个页面之间的语言变化导致旧表单写错语言。

`POST /admin/index/locale` 切换个人语言，只允许已登录账号；
`/admin/index/languages` 提供全局设置表单，POST 保存且只允许超级管理员。
写入均经过既有 CSRF 验证，GET 不改变配置；不支持或未启用的语言返回 422。

## 表单与翻译

`public/static/plugs/admin/language.js` 跟踪 `form[data-auto]` 的初始值，搜索表单不参与。
切换时有修改则提示“继续编辑”或“放弃修改并切换”，不会自动发布或代替用户提交。
继续编辑后正常保存，再切换语言；异步保存后仍保留的自定义表单可以调用
`WebBaseAdminLanguage.markSaved(form)` 更新比较基准。富文本编辑器需要持续将内容同步到
表单字段；已有 wangEditor 的 change 同步可直接参与检查。

公共顶部菜单、语言设置与分类示例已接入英文文案。未接入 `lang()` 的旧系统页面及第三方
组件提示仍可能显示中文；这项基础能力不包含数据库内容机器翻译或完整后台翻译验收。

## 验证

遵循测试目录的隔离要求，使用独立项目与 `webbase_verify_*` 或 `webbase_test_*` 数据库。

```powershell
$env:WEBBASE_TEST_ISOLATED='1'
$env:WEBBASE_TEST_URL='http://127.0.0.1:18431'
python tests/integration.py
python tests/backend.py
python tests/website.py
python tests/admin_language.py
```

语言用例覆盖登录/CSRF/POST、非法与数组输入、跨模块/刷新/重新登录、不同账号隔离、
普通管理员的全局设置拒绝、默认语言校验、停用回退、新账号默认语言、旧页面请求拦截、
前后台语言隔离，以及分类页面渲染后的 JavaScript 语法。
