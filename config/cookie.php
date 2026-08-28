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

return [
    // cookie 保存时间
    'expire'    => 0,
    // cookie 保存路径
    'path'      => '/',
    // cookie 有效域名
    'domain'    => '',
    // httponly 访问设置
    'httponly'  => true,
    // 是否使用 setcookie
    'setcookie' => true,
    // cookie 安全传输，只支持 https 协议
    'secure'    => request()->isSsl(),
    // samesite 安全设置，支持 'strict' 'lax' 'none'
    'samesite'  => request()->isSsl() ? 'none' : 'lax',
];
