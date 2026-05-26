<?php
namespace RadAdmin;

class Codex {
    private $runData = [];
    private $db;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'] ?? null;
    }

    public function view() {
        $this->runData['route']['h1'] = 'AI Code Studio';
        $this->runData['route']['meta_title'] = 'AI Code Studio';
        $this->runData['route']['breadcrumb'] = [
            'AI Code Studio' => null,
        ];

        $roots = $this->buildRoots();
        $activeRoot = isset($roots['ms']) ? 'ms' : array_key_first($roots);

        $this->runData['data']['codex'] = [
            'default_root' => $activeRoot && isset($roots[$activeRoot]['path']) ? $roots[$activeRoot]['path'] : '',
            'microservices' => $this->listMicroservices(),
            'roots' => $roots,
            'active_root' => $activeRoot ?: 'ms',
        ];

        return $this->runData;
    }

    private function listMicroservices(): array {
        $dir = $this->runData['config']['dir']['ms'] ?? '';
        if ($dir === '' || !is_dir($dir)) {
            return [];
        }
        $items = glob(rtrim($dir, '/') . '/*', GLOB_ONLYDIR) ?: [];
        $names = array_map('basename', $items);

        $role = (new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []))->role();
        if ($role === 'system_admin' || !$this->db) {
            return $names;
        }

        $allowed = $this->db->select('s_ms', [], true);
        $allowed = array_filter($allowed, function ($row) {
            $msId = (int)($row['id'] ?? 0);
            if ($msId === 0) {
                return false;
            }
            return !\RadAdmin\VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        });
        $allowedNames = [];
        foreach ($allowed as $row) {
            $allowedNames[strtolower($row['s_name'] ?? '')] = true;
        }

        return array_values(array_filter($names, function ($name) use ($allowedNames) {
            return isset($allowedNames[strtolower($name)]);
        }));
    }

    private function buildRoots(): array {
        $radDir = rtrim($this->runData['config']['dir']['rad'] ?? '', '/');
        $roots = [
            'ms' => [
                'label' => 'Microservicelets',
                'path' => $this->runData['config']['dir']['ms'] ?? '',
            ],
            'theme' => [
                'label' => 'Theme Templates',
                'path' => $this->runData['config']['dir']['theme'] ?? '',
            ],
            'upgrade' => [
                'label' => 'Upgrade Scripts',
                'path' => $radDir === '' ? '' : $radDir . '/upgrades',
            ],
        ];

        return array_filter($roots, function ($root) {
            return isset($root['path']) && $root['path'] !== '' && is_dir($root['path']);
        });
    }
}
