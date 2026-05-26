<?php
namespace Core\Sys;

class PrivilegeService {
    private array $config;
    private array $entity;
    private array $manifest;
    private array $overridePriv = [];
    private array $overrideManifest = [];

    public function __construct(array $config, array $entity = [], array $manifest = []) {
        $this->config = $config;
        $this->entity = $entity;
        // Privilege IDs and manifest should come from rad-vals.config.php merged into $config['rad']
        $this->overridePriv = $config['rad']['privileges'] ?? [];
        $this->overrideManifest = $config['rad']['privilege_manifest'] ?? [];

        if (!empty($manifest)) {
            $this->manifest = $manifest;
        } elseif (!empty($this->overrideManifest)) {
            $this->manifest = $this->overrideManifest;
        } else {
            $this->manifest = $this->defaultManifest();
        }
    }

    public function role(): string {
        $id = (int)($this->entity['id'] ?? $this->entity['entity_id'] ?? ($_SESSION['entity_id'] ?? 0));
        if ($id === 1) {
            return 'system_admin';
        }
        $accessAdmins = $this->pickPrivilegeBucket('access_admin');
        $devOverride = $this->overridePriv['developers'] ?? null;
        $analystOverride = $this->overridePriv['analysts'] ?? null;

        $devs = (is_array($devOverride) && count($devOverride) > 0)
            ? $devOverride
            : ($this->config['rad']['privileges']['developers'] ?? []);
        $analysts = (is_array($analystOverride) && count($analystOverride) > 0)
            ? $analystOverride
            : ($this->config['rad']['privileges']['analysts'] ?? []);
        if (in_array($id, $accessAdmins, true)) {
            return 'access_admin';
        }
        if (in_array($id, $devs, true)) {
            return 'developer';
        }
        if (in_array($id, $analysts, true)) {
            return 'analyst';
        }
        return 'user';
    }

    public function can(string $action): bool {
        $role = $this->role();
        if ($role === 'system_admin') {
            return true;
        }
        $allowedRoles = $this->manifest[$action] ?? ($this->overrideManifest[$action] ?? []);
        return in_array($role, $allowedRoles, true);
    }

    public function allowedActions(): array {
        $role = $this->role();
        if ($role === 'system_admin') {
            return array_keys($this->manifest);
        }
        $list = [];
        foreach ($this->manifest as $action => $roles) {
            if (in_array($role, $roles, true)) {
                $list[] = $action;
            }
        }
        return $list;
    }

    private function pickPrivilegeBucket(string $key): array {
        $override = $this->overridePriv[$key] ?? null;
        if (is_array($override) && count($override) > 0) {
            return $override;
        }
        return [];
    }

    private function defaultManifest(): array {
        return [
            'delete' => ['system_admin'],
            'destroy' => ['system_admin'],
            'rename' => ['system_admin', 'developer'],
            'upgrade' => ['system_admin'],
            'manage_tokens' => ['system_admin'],
            'manage_privileges' => ['system_admin'],
            'idm_manage' => ['system_admin', 'access_admin'],
            'idm_view' => ['system_admin', 'access_admin', 'developer', 'analyst'],
            'microservice_add' => ['system_admin', 'developer'],
            'microservice_edit' => ['system_admin', 'developer'],
            'controller_add' => ['system_admin', 'developer'],
            'controller_edit' => ['system_admin', 'developer'],
            'settings' => ['system_admin', 'developer'],
            'code_edit' => ['system_admin', 'developer', 'analyst'],
            'route_add' => ['system_admin', 'developer', 'analyst'],
            'route_edit' => ['system_admin', 'developer', 'analyst'],
            'view' => ['system_admin', 'access_admin', 'developer', 'analyst'],
        ];
    }
}
