<?php
namespace RadAdmin;

class RadAdminCommunity
{
    private const HELD_MODULES = [
        'aiconfig' => true,
        'aiassist' => true,
        'codex' => true,
        'codexapi' => true,
        'devsecops' => true,
        'observability' => true,
        'sca' => true,
        'telemetry' => true,
    ];

    private const HELD_PATHS = [
        '/microservice/aiwizard' => true,
    ];

    public static function isEnabled(array $config): bool
    {
        $value = $config['sys']['rad_admin_community_edition'] ?? 'Y';
        return !in_array(strtolower(trim((string)$value)), ['0', 'n', 'no', 'false', 'off', 'disabled'], true);
    }

    public static function moduleAllowed(string $moduleName, array $config): bool
    {
        if (!self::isEnabled($config)) {
            return true;
        }

        $moduleName = strtolower(trim($moduleName));
        return $moduleName === '' || !isset(self::HELD_MODULES[$moduleName]);
    }

    public static function pathAllowed(string $path, array $config): bool
    {
        if (!self::isEnabled($config)) {
            return true;
        }

        $path = '/' . trim($path, '/');
        if (isset(self::HELD_PATHS[$path])) {
            return false;
        }

        $moduleName = strtolower(strtok(trim($path, '/'), '/') ?: '');
        return self::moduleAllowed($moduleName, $config);
    }

    public static function filterNavSections(array $sections, array $config): array
    {
        if (!self::isEnabled($config)) {
            return $sections;
        }

        foreach ($sections as $section => $items) {
            $sections[$section] = array_values(array_filter($items, function ($item) use ($config) {
                $path = is_array($item) ? (string)($item['path'] ?? $item[2] ?? '') : '';
                return self::pathAllowed($path, $config);
            }));

            if (empty($sections[$section])) {
                unset($sections[$section]);
            }
        }

        return $sections;
    }
}
