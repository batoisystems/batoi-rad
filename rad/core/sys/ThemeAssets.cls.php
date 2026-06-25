<?php
namespace Core\Sys;

class ThemeAssets
{
    public static function isUifEnabled(array $runData): bool
    {
        $envValue = getenv('PUBLIC_THEME_UIF_ENABLED');
        if ($envValue !== false && trim((string)$envValue) !== '') {
            return self::isTruthy($envValue);
        }

        return self::isTruthy($runData['config']['sys']['public_theme_uif_enabled'] ?? 'Y');
    }

    public static function renderHead(array $runData, array $options = []): string
    {
        $assetUrl = self::assetUrl($runData);
        if ($assetUrl === '') {
            return '';
        }

        $loadAppCss = $options['app'] ?? true;

        $html = '';
        if (!empty($options['apex'])) {
            $html .= '<link href="' . self::escape($assetUrl . '/css/apex.css') . '" rel="stylesheet">' . "\n";
        }
        if ($loadAppCss) {
            $html .= '<link href="' . self::escape($assetUrl . '/css/app.css') . '" rel="stylesheet">' . "\n";
        }
        $html .= '<link href="' . self::escape($assetUrl . '/css/rad-theme.css') . '" rel="stylesheet">' . "\n";

        if (self::isUifEnabled($runData)) {
            $html .= '<link href="' . self::escape($assetUrl . '/uif/uif.css') . '" rel="stylesheet">' . "\n";
            $html .= '<script src="' . self::escape($assetUrl . '/uif/uif.iife.js') . '"></script>' . "\n";
        }

        return $html;
    }

    public static function renderBody(array $runData): string
    {
        if (!self::isUifEnabled($runData)) {
            return '';
        }

        return '<script>if (window.BatoiUIF && typeof window.BatoiUIF.autoStart === "function") { window.BatoiUIF.autoStart(); }</script>' . "\n";
    }

    private static function assetUrl(array $runData): string
    {
        return rtrim((string)($runData['route']['assets_url'] ?? ''), '/');
    }

    private static function isTruthy($value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['1', 'y', 'yes', 'true', 'on', 'enabled'], true);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
