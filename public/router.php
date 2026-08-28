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

if (is_file($_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'])) {
    return false;
} else {
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
    require $_SERVER['SCRIPT_FILENAME'];
}