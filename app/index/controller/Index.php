<?php

// +----------------------------------------------------------------------
// | WebBase CMS Configuration
// +----------------------------------------------------------------------
// | Copyright (c) 2026 WebBase contributors
// +----------------------------------------------------------------------
// | 官方网站: https://github.com/theday-seeyou/web-base-temple
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github 代码仓库：https://github.com/theday-seeyou/web-base-temple
// +----------------------------------------------------------------------

namespace app\index\controller;

use WebBase\Core\Controller;

class Index extends Controller
{
    public function index()
    {
        $locale = $this->app->lang->getLangSet();
        return view('index/index', [
            'locale' => $locale,
            'htmlLocale' => $locale === 'en-us' ? 'en-US' : 'zh-CN',
            'adminUrl' => sysuri('admin/index/index'),
            'year' => date('Y'),
        ]);
    }
}
