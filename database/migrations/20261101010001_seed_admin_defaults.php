<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | WebBase Admin Component
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
use app\admin\Service;
use WebBase\Core\extend\PhinxExtend;
use WebBase\Core\model\SystemConfig;
use think\migration\Migrator;

@set_time_limit(0);
@ini_set('memory_limit', '-1');

/**
 * 系统模块数据.
 */
final class SeedAdminDefaults extends Migrator
{
    /**
     * 创建数据库.
     */
    public function change()
    {
        $this->insertMenu();
        $this->insertConf();
    }

    /**
     * 初始化配置参数.
     */
    private function insertConf()
    {
        $modal = SystemConfig::mk()->whereRaw('1=1')->findOrEmpty();
        $modal->isEmpty() && $modal->insertAll([
            ['type' => 'base', 'name' => 'app_name', 'value' => 'WebBase Admin'],
            ['type' => 'base', 'name' => 'app_version', 'value' => '0.5'],
            ['type' => 'base', 'name' => 'editor', 'value' => 'wangEditor'],
            ['type' => 'base', 'name' => 'login_name', 'value' => '系统管理'],
            ['type' => 'base', 'name' => 'site_copy', 'value' => '©版权所有 2014-' . date('Y') . ' WebBase Admin'],
            ['type' => 'base', 'name' => 'site_icon', 'value' => ''],
            ['type' => 'base', 'name' => 'site_name', 'value' => 'WebBase Admin'],
            ['type' => 'base', 'name' => 'site_theme', 'value' => 'default'],
            ['type' => 'storage', 'name' => 'allow_exts', 'value' => 'pdf,doc,docx,gif,ico,jpg,jpeg,webp,mp3,mp4,png,zip,rar,xls,xlsx'],
            ['type' => 'storage', 'name' => 'type', 'value' => 'local'],
            ['type' => 'wechat', 'name' => 'type', 'value' => 'api'],
        ]);
    }

    /**
     * 初始化系统菜单.
     * @throws Exception
     */
    private function insertMenu()
    {
        // 初始化菜单数据
        PhinxExtend::write2menu([
            [
                'name' => '系统管理',
                'sort' => '100',
                'subs' => Service::menu(),
            ],
        ], [
            'url|node' => 'admin/config/index',
        ]);
    }
}
