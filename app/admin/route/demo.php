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
use WebBase\Core\Library;
use WebBase\Core\service\RuntimeService;

/* ! 演示环境禁止操作路由绑定 */
if (RuntimeService::check('demo')) {
    Library::$sapp->route->post('index/pass', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止修改用户密码！')]);
    });
    Library::$sapp->route->post('config/system', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止修改系统配置！')]);
    });
    Library::$sapp->route->post('config/storage', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止修改系统配置！')]);
    });
    Library::$sapp->route->post('menu', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止给菜单排序！')]);
    });
    Library::$sapp->route->post('menu/index', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止给菜单排序！')]);
    });
    Library::$sapp->route->post('menu/add', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止添加菜单！')]);
    });
    Library::$sapp->route->post('menu/edit', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止编辑菜单！')]);
    });
    Library::$sapp->route->post('menu/state', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止禁用菜单！')]);
    });
    Library::$sapp->route->post('menu/remove', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止删除菜单！')]);
    });
    Library::$sapp->route->post('user/pass', static function () {
        return json(['code' => 0, 'info' => lang('演示环境禁止修改密码！')]);
    });
}
