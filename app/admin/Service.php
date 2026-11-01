<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | WebBase CMS Component
 * +----------------------------------------------------------------------
 * | Copyright (c) 2026 WebBase contributors
 * +----------------------------------------------------------------------
 * | 官方网站: https://github.com/theday-seeyou/web-base-temple
 * +----------------------------------------------------------------------
 * | 开源协议 ( https://mit-license.org )
 * +----------------------------------------------------------------------
 * | github 代码仓库：https://github.com/theday-seeyou/web-base-temple
 * +----------------------------------------------------------------------
 */

namespace app\admin;

use WebBase\Core\Plugin;

/**
 * 插件服务注册.
 * @class Service
 */
class Service extends Plugin
{
    public function boot(): void
    {
        // Bind after all services boot, so ThinkPHP's migration service cannot replace it.
        \think\Console::starting(function (): void {
            $this->app->bind('migration.creator', \WebBase\Core\migration\Creator::class);
        });
        $this->commands([\app\admin\command\InitAdmin::class]);
    }
    /**
     * 定义插件名称.
     * @var string
     */
    protected $appName = '系统管理';

    /**
     * 定义安装包名.
     * @var string
     */
    protected $package = 'theday-seeyou/web-base-temple';

    /**
     * 定义插件中心菜单.
     */
    public static function menu(): array
    {
        return [
            [
                'name' => '示例模块',
                'subs' => [
                    ['name' => '树形分类示例', 'icon' => 'layui-icon layui-icon-app', 'node' => 'admin/category/index'],
                ],
            ],
            [
                'name' => '系统配置',
                'subs' => [
                    ['name' => '系统参数配置', 'icon' => 'layui-icon layui-icon-set', 'node' => 'admin/config/index'],
                    ['name' => '系统任务管理', 'icon' => 'layui-icon layui-icon-log', 'node' => 'admin/queue/index'],
                    ['name' => '系统日志管理', 'icon' => 'layui-icon layui-icon-form', 'node' => 'admin/oplog/index'],
                    ['name' => '数据字典管理', 'icon' => 'layui-icon layui-icon-code-circle', 'node' => 'admin/base/index'],
                    ['name' => '系统文件管理', 'icon' => 'layui-icon layui-icon-carousel', 'node' => 'admin/file/index'],
                    ['name' => '系统菜单管理', 'icon' => 'layui-icon layui-icon-layouts', 'node' => 'admin/menu/index'],
                ],
            ],
            [
                'name' => '权限管理',
                'subs' => [
                    ['name' => '系统权限管理', 'icon' => 'layui-icon layui-icon-vercode', 'node' => 'admin/auth/index'],
                    ['name' => '系统用户管理', 'icon' => 'layui-icon layui-icon-username', 'node' => 'admin/user/index'],
                ],
            ],
        ];
    }
}
