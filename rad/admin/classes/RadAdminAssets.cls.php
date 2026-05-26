<?php
namespace RadAdmin;

class RadAdminAssets
{
    public static function isUifEnabled(array $runData): bool
    {
        $envValue = getenv('RAD_ADMIN_UIF_ENABLED');
        if ($envValue !== false && trim((string)$envValue) !== '') {
            return self::isTruthy($envValue);
        }

        return self::isTruthy($runData['config']['sys']['rad_admin_uif_enabled'] ?? 'N');
    }

    public static function renderUifHead(array $runData): string
    {
        if (!self::isUifEnabled($runData)) {
            return '';
        }

        $assetUrl = self::radAssetsUrl($runData);
        if ($assetUrl === '') {
            return '';
        }

        return '<link href="' . self::escape($assetUrl . '/uif/uif.css') . '" rel="stylesheet">' . "\n";
    }

    public static function renderUifBody(array $runData): string
    {
        if (!self::isUifEnabled($runData)) {
            return '';
        }

        $assetUrl = self::radAssetsUrl($runData);
        if ($assetUrl === '') {
            return '';
        }

        return '<script src="' . self::escape($assetUrl . '/uif/uif.iife.js') . '"></script>' . "\n"
            . '<script>if (window.BatoiUIF && typeof window.BatoiUIF.autoStart === "function") { window.BatoiUIF.autoStart(); }</script>' . "\n";
    }

    public static function monacoBaseUrl(array $runData): string
    {
        $configuredUrl = trim((string)($runData['config']['sys']['rad_admin_monaco_base_url'] ?? ''));
        if ($configuredUrl !== '') {
            return $configuredUrl;
        }

        $assetUrl = self::radAssetsUrl($runData);
        if ($assetUrl !== '') {
            return $assetUrl . '/monaco/min/vs';
        }

        return '';
    }

    public static function renderMonacoLoaderConfig(array $runData): string
    {
        $baseUrl = self::monacoBaseUrl($runData);
        if ($baseUrl === '') {
            return '';
        }

        return '<script>window.RAD_ADMIN_MONACO_BASE_URL = "' . self::escapeJs($baseUrl) . '";</script>' . "\n";
    }

    private static function radAssetsUrl(array $runData): string
    {
        $routeAssetUrl = trim((string)($runData['route']['rad_assets_url'] ?? ''));
        if ($routeAssetUrl !== '') {
            return rtrim($routeAssetUrl, '/');
        }

        $baseUrl = trim((string)($runData['config']['sys']['base_url'] ?? ''));
        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/') . '/rad-admin/assets';
        }

        return '';
    }

    private static function isTruthy($value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['1', 'y', 'yes', 'true', 'on', 'enabled'], true);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function escapeJs(string $value): string
    {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value);
    }
}
