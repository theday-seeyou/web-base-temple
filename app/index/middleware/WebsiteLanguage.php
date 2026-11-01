<?php

declare(strict_types=1);

namespace app\index\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;

/** Website language preferences are independent of administration language. */
final class WebsiteLanguage
{
    public function __construct(private App $app)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $allowed = $this->app->config->get('site.locales', ['zh-cn', 'en-us']);
        $default = $this->app->config->get('site.default_locale', 'zh-cn');
        $cookie = $this->app->config->get('site.locale_cookie', 'site_lang');
        $candidate = $request->get('lang', $request->cookie($cookie, $default));
        $locale = is_string($candidate) && in_array(strtolower($candidate), $allowed, true)
            ? strtolower($candidate) : $default;
        $this->app->lang->switchLangSet($locale);
        $this->app->cookie->set($cookie, $locale, [
            'expire' => 30 * 86400, 'path' => '/', 'httponly' => true,
            'secure' => $request->isSsl(), 'samesite' => 'lax',
        ]);

        return $next($request)->header([
            'Content-Language' => $locale,
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Cookie',
        ]);
    }
}
