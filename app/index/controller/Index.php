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
        $this->redirect(sysuri('admin/login/index'));
    }
}
