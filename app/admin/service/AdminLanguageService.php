<?php

declare(strict_types=1);

namespace app\admin\service;

use InvalidArgumentException;
use WebBase\Core\Library;
use WebBase\Core\service\AdminService;
use WebBase\Core\service\SystemService;

/** One validated administration language context, shared by all applications. */
final class AdminLanguageService
{
    public static function catalog(): array
    {
        return Library::$sapp->config->get('admin_language.catalog', [
            'zh-cn' => '中文', 'en-us' => 'English',
        ]);
    }

    public static function validateSettings($enabled, $default, array $catalog): array
    {
        if (!is_array($enabled) || $enabled === [] || !is_string($default)) {
            throw new InvalidArgumentException('请选择至少一种语言，并设置默认语言。');
        }
        foreach ($enabled as $locale) {
            if (!is_string($locale) || !array_key_exists($locale, $catalog)) {
                throw new InvalidArgumentException('包含不支持的语言。');
            }
        }
        $enabled = array_values(array_unique($enabled));
        if (!in_array($default, $enabled, true)) {
            throw new InvalidArgumentException('默认语言必须处于启用状态。');
        }
        return ['enabled' => $enabled, 'default' => $default];
    }

    public static function settings(): array
    {
        if (is_array($cached = sysvar('webbase.admin.languages'))) {
            return $cached;
        }
        $fallback = self::validateSettings(
            Library::$sapp->config->get('admin_language.enabled', array_keys(self::catalog())),
            Library::$sapp->config->get('admin_language.default', 'zh-cn'), self::catalog()
        );
        $stored = SystemService::getData('AdminLanguageSettings', $fallback);
        try {
            $settings = self::validateSettings($stored['enabled'] ?? null, $stored['default'] ?? null, self::catalog());
        } catch (InvalidArgumentException $exception) {
            $settings = $fallback;
        }
        return sysvar('webbase.admin.languages', $settings);
    }

    public static function resolveLocale($preferred, array $settings): string
    {
        return is_string($preferred) && in_array($preferred, $settings['enabled'], true)
            ? $preferred : $settings['default'];
    }

    public static function current(): string
    {
        if (is_string($cached = sysvar('webbase.admin.locale'))) {
            return $cached;
        }
        $preferred = AdminService::isLogin() ? AdminService::getUserData('admin_locale') : null;
        return sysvar('webbase.admin.locale', self::resolveLocale($preferred, self::settings()));
    }

    public static function select($locale): string
    {
        if (!AdminService::isLogin()) {
            throw new InvalidArgumentException('请重新登录！');
        }
        if (!is_string($locale) || !in_array($locale, self::settings()['enabled'], true)) {
            throw new InvalidArgumentException('所选语言未启用。');
        }
        if (!AdminService::setUserData(['admin_locale' => $locale])) {
            throw new \RuntimeException('语言保存失败，请稍后重试。');
        }
        sysvar('webbase.admin.locale', $locale);
        return $locale;
    }

    public static function saveSettings($enabled, $default): void
    {
        if (!AdminService::isSuper()) {
            throw new InvalidArgumentException('请使用超管账号操作！');
        }
        $settings = self::validateSettings($enabled, $default, self::catalog());
        if (!SystemService::setData('AdminLanguageSettings', $settings)) {
            throw new \RuntimeException('语言保存失败，请稍后重试。');
        }
        sysvar('webbase.admin.languages', $settings);
        sysvar('webbase.admin.locale', false);
    }
}
