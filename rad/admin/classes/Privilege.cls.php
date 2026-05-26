<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;
use RuntimeException;

class Privilege {
    private array $runData = [];
    private PrivilegeService $privilege;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->privilege = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function view() {
        if (!$this->privilege->can('privilege_view')) {
            throw new RuntimeException('Access denied.');
        }
        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
        ];
        // Migration trigger
        if ($this->runData['request']->method === 'POST' && isset($this->runData['request']->post['migrate_privileges'])) {
            $this->migrateFromRadConfig();
            $this->runData['request']->setAlert('Migration from rad.config.php completed.', 'success');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/privilege/view');
            exit;
        }
        if ($this->runData['request']->method === 'POST') {
            $this->save();
        }
        $roles = [
            'system_admin' => ['Full access'],
            'developer' => ['Allowed: most features', 'Blocked: delete/destroy/rename/upgrade'],
            'analyst' => ['Allowed: code edit, route add/edit', 'Blocked: destructive ops, upgrades, token/privilege management'],
            'access_admin' => ['Allowed: identity/data governance', 'Blocked: restricted microservicelets (RAD Admin)'],
        ];
        $vals = $this->loadValsConfig();
        $config = $this->sanitizeRoleConfig($vals['privilege_ids'] ?? []);
        $visibility = ['restricted_ms_ids' => $vals['restricted_ms_ids'] ?? []];
        $privManifest = $this->sanitizeManifest($vals['privilege_manifest'] ?? []);
        $microservices = $this->runData['db']->select('s_ms', [], true, ['s_name' => 'ASC']);
        if ($filters['q'] !== '') {
            $needle = strtolower($filters['q']);
            $entities = array_values(array_filter($entities, function ($row) use ($needle) {
                $blob = strtolower(($row['s_name'] ?? '') . ' ' . ($row['s_identity'] ?? '') . ' ' . ($row['uid'] ?? '') . ' ' . ($row['id'] ?? ''));
                return strpos($blob, $needle) !== false;
            }));
            $entityNames = $this->fetchEntityNames(['developers' => [], 'analysts' => [], 'access_admins' => []]); // reuse existing helper
        }
        $entityNames = $this->fetchEntityNames($config);
        $entities = array_values(array_filter(
            $this->runData['db']->select('s_entity', ['livestatus' => '1'], true, ['s_name' => 'ASC']),
            static function ($row) {
                return (int)($row['id'] ?? 0) !== 1;
            }
        ));
        $this->runData['data']['privilege_roles'] = $roles;
        $this->runData['data']['privilege_config'] = $config;
        $this->runData['data']['visibility_config'] = $visibility;
        $this->runData['data']['microservices'] = $microservices;
        $this->runData['data']['privilege_entities'] = $entityNames;
        $this->runData['data']['all_entities'] = $entities;
        $this->runData['data']['privilege_keys'] = $privManifest;
        $this->runData['data']['filters'] = $filters;
        $this->runData['route']['h1'] = 'RAD Admin Privileges';
        $this->runData['route']['meta_title'] = 'RAD Admin Privileges';
        $this->runData['route']['breadcrumb'] = ['RAD Admin Privileges' => ''];
        return $this->runData;
    }

    private function save(): void {
        $post = $this->runData['request']->post;
        $devs = $this->filterNonSystem($post['developers'] ?? '');
        $analysts = $this->filterNonSystem($post['analysts'] ?? '');
        $accessAdmins = $this->filterNonSystem($post['access_admin'] ?? '');
        $restrictedMs = $post['restricted_ms'] ?? [];
        if (!is_array($restrictedMs)) {
            $restrictedMs = [];
        }
        $restrictedMs = array_values(array_filter(array_map('intval', $restrictedMs)));
        $privMapPost = $post['privilege_map'] ?? [];
        $privilegeManifest = $this->mergePrivilegeManifest($privMapPost);

        $privData = [
            'privilege_ids' => [
                'developers' => $devs,
                'analysts' => $analysts,
                'access_admins' => $accessAdmins,
            ],
            'restricted_ms_ids' => $restrictedMs,
            'privilege_manifest' => $privilegeManifest,
        ];
        $this->persistToValsConfig($privData);

        $this->runData['request']->setAlert('Privileges updated.', 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/privilege/view');
        exit;
    }

    private function loadValsConfig(): array {
        $file = rtrim($this->runData['config']['dir']['admin'] ?? dirname(__DIR__), '/') . '/rad-vals.config.php';
        if (file_exists($file)) {
            $cfg = include $file;
            if (is_array($cfg)) {
                return $cfg;
            }
        }
        return [
            'privilege_ids' => [
                'developers' => [],
                'analysts' => [],
                'access_admins' => [],
            ],
            'restricted_ms_ids' => [],
            'privilege_manifest' => [],
        ];
    }

    private function persistToValsConfig(array $data): void {
        $file = rtrim($this->runData['config']['dir']['admin'] ?? dirname(__DIR__), '/') . '/rad-vals.config.php';
        $export = "<?php\nreturn " . var_export($data, true) . ";\n";
        if (@file_put_contents($file, $export) === false) {
            throw new RuntimeException('Unable to write rad-vals.config.php');
        }
    }

    private function fetchEntityNames(array $config): array {
        $ids = [];
        foreach (['developers', 'analysts', 'access_admins'] as $key) {
            foreach ($config[$key] ?? [] as $id) {
                $ids[] = (int)$id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach ($ids as $idx => $id) {
            $ph = ':id' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = $id;
        }
        $rows = $this->runData['db']->query(
            'SELECT id, s_name FROM s_entity WHERE id IN (' . implode(',', $placeholders) . ')',
            $params
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['id'] ?? 0)] = $row['s_name'] ?? '';
        }
        return $map;
    }

    private function filterNonSystem($csv): array {
        $ids = array_filter(array_map('intval', array_filter(array_map('trim', explode(',', (string)$csv)))));
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0 && $id !== 1;
        })));
        return $ids;
    }

    private function sanitizeRoleConfig(array $config): array {
        foreach (['developers', 'analysts', 'access_admins'] as $key) {
            $ids = $config[$key] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            $config[$key] = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
                return $id > 0 && $id !== 1;
            })));
        }
        return $config;
    }

    private function sanitizeManifest(array $manifest): array {
        // Ensure keys map to arrays of role strings
        $out = [];
        foreach ($manifest as $key => $roles) {
            if (!is_array($roles)) { continue; }
            $out[$key] = array_values(array_unique(array_filter(array_map('trim', $roles), static function ($r) {
                return $r !== '';
            })));
        }
        return $out;
    }

    private function mergePrivilegeManifest(array $posted): array {
        // Start from default manifest (rad.config.php) then apply posted values so toggles can add/remove
        $base = $this->runData['config']['rad']['privilege_manifest'] ?? [];
        $existingVals = $this->loadValsConfig()['privilege_manifest'] ?? [];
        $base = $this->sanitizeManifest(array_merge($base, $existingVals));
        $incoming = $this->sanitizeManifest($posted);

        foreach ($base as $key => $roles) {
            $set = $incoming[$key] ?? [];
            // System admin is implicitly allowed; keep it in the manifest for clarity
            if (!in_array('system_admin', $set, true)) {
                $set[] = 'system_admin';
            }
            $base[$key] = array_values(array_unique($set));
        }
        // Include any new keys from the post (not in base) while enforcing system_admin
        foreach ($incoming as $key => $roles) {
            if (!isset($base[$key])) {
                if (!in_array('system_admin', $roles, true)) {
                    $roles[] = 'system_admin';
                }
                $base[$key] = array_values(array_unique($roles));
            }
        }
        return $base;
    }

    private function filterIps($csv): array {
        $parts = array_filter(array_map('trim', explode(',', (string)$csv)));
        $ips = [];
        foreach ($parts as $p) {
            if (filter_var($p, FILTER_VALIDATE_IP) || preg_match('/^\\d{1,3}(\\.\\d{1,3}){3}\\/\\d{1,2}$/', $p)) {
                $ips[] = $p;
            }
        }
        return array_values(array_unique($ips));
    }

    private function migrateFromRadConfig(): void {
        $radFile = rtrim($this->runData['config']['dir']['admin'] ?? dirname(__DIR__), '/') . '/rad.config.php';
        $valsFile = rtrim($this->runData['config']['dir']['admin'] ?? dirname(__DIR__), '/') . '/rad-vals.config.php';
        $rad = [];
        if (file_exists($radFile)) {
            $rad = include $radFile;
        }
        $priv = $rad['rad']['privileges'] ?? [];
        // Merge with existing rad-vals if present
        $existingVals = [];
        if (file_exists($valsFile)) {
            $current = include $valsFile;
            if (is_array($current)) {
                $existingVals = $current;
            }
        }
        $existingRestricted = $existingVals['restricted_ms_ids'] ?? [];
        $restricted = $rad['rad']['visibility']['restricted_ms_ids'] ?? [];
        $payload = [
            'privilege_ids' => [
                'developers' => $priv['developers'] ?? [],
                'analysts' => $priv['analysts'] ?? [],
                'access_admins' => $priv['access_admins'] ?? ($priv['access_admin'] ?? []),
            ],
            'restricted_ms_ids' => array_values(array_unique(array_merge(
                is_array($restricted) ? $restricted : [],
                is_array($existingRestricted) ? $existingRestricted : []
            ))),
        ];
        $export = "<?php\nreturn " . var_export($payload, true) . ";\n";
        @file_put_contents($valsFile, $export);
    }
}
