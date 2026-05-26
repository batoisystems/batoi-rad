<?php
namespace RadAdmin;

class VisibilityHelper {
    public static function load(array $config): array {
        $restricted = [];
        if (!empty($config['dir']['admin'])) {
            $valsFile = rtrim($config['dir']['admin'], '/') . '/rad-vals.config.php';
            if (file_exists($valsFile)) {
                $vals = include $valsFile;
                if (is_array($vals) && isset($vals['restricted_ms_ids']) && is_array($vals['restricted_ms_ids'])) {
                    $restricted = $vals['restricted_ms_ids'];
                }
            }
        }
        return ['restricted_ms_ids' => $restricted];
    }

    public static function isRestrictedMs(int $msId, array $config, array $entity = []): bool {
        $role = self::resolveRole($config, $entity);
        if ($role === 'system_admin') {
            return false;
        }
        $data = self::load($config);
        $restricted = $data['restricted_ms_ids'] ?? [];
        return in_array($msId, $restricted, true);
    }

    private static function resolveRole(array $config, array $entity): string {
        $priv = new \Core\Sys\PrivilegeService($config, $entity);
        return $priv->role();
    }
}
