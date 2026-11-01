<?php

declare(strict_types=1);

namespace app\admin\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;
use think\exception\HttpResponseException;
use app\admin\service\AdminLanguageService;

/** Register after session initialization, before the route/RBAC middleware. */
final class AdminLanguage
{
    public function __construct(private App $app)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $locale = AdminLanguageService::current();
        $this->app->lang->switchLangSet($locale);
        $requested = $request->header('X-Admin-Language');
        if ($requested !== null && $requested !== $locale) {
            throw new HttpResponseException(json([
                'code' => 0, 'info' => lang('后台语言已在其他页面更新，请刷新后再操作。'),
            ], 409));
        }
        $headers = ['Content-Language' => $locale, 'Cache-Control' => 'private, no-store'];
        try {
            return $next($request)->header($headers);
        } catch (HttpResponseException $exception) {
            $exception->getResponse()->header($headers);
            throw $exception;
        }
    }
}
