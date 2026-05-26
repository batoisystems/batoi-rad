<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;

class Iam {
    private $runData = [];
    private $db;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
    }

    public function privilegematrix() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }

        $request = $this->runData['request'];
        $activeScope = strtolower((string)($request->get['scope'] ?? 'platform'));
        if (!in_array($activeScope, ['platform', 'workspace', 'ms'], true)) {
            $activeScope = 'platform';
        }
        $perMsLimit = (int)($request->get['per_ms_limit'] ?? 50);
        $allowedLimits = [0, 25, 50, 100, 200];
        if (!in_array($perMsLimit, $allowedLimits, true)) {
            $perMsLimit = 50;
        }

        $this->runData['route']['h1'] = 'Privilege Matrix';
        $this->runData['route']['meta_title'] = 'Privilege Matrix';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'IAM' => '',
            'Privilege Matrix' => '',
        ];

        $roles = $this->db->select('s_role', [], true, ['s_role_name' => 'ASC']);
        $rolesByScope = [
            'platform' => [],
            'workspace' => [],
            'ms' => [],
        ];
        foreach ($roles as $role) {
            $scope = strtolower((string)($role['s_scope'] ?? ''));
            if (!isset($rolesByScope[$scope])) {
                continue;
            }
            $rolesByScope[$scope][] = $role;
        }

        $routes = $this->db->query(
            'SELECT r.id, r.uid, r.s_name, r.s_ms_id, m.s_name AS ms_name, m.id AS ms_id, m.s_scope AS ms_scope
             FROM s_msroute r
             LEFT JOIN s_ms m ON m.id = r.s_ms_id
             ORDER BY m.s_name ASC, r.id ASC'
        );
        $routes = $this->filterRestrictedRoutes($routes, $priv);
        $msGroups = $this->groupRoutesByMs($routes, $perMsLimit);

        $bindings = $this->db->select('s_permission_binding', ['s_object_type' => 'route'], true);
        $bindingMap = [];
        foreach ($bindings as $binding) {
            $routeId = (int)($binding['s_object_id'] ?? 0);
            $roleId = (int)($binding['s_role_id'] ?? 0);
            if ($routeId > 0 && $roleId > 0) {
                $bindingMap[$routeId][$roleId] = true;
            }
        }

        $this->runData['data']['roles_by_scope'] = $rolesByScope;
        $this->runData['data']['ms_groups'] = $msGroups;
        $this->runData['data']['binding_map'] = $bindingMap;
        $this->runData['data']['active_scope'] = $activeScope;
        $this->runData['data']['per_ms_limit'] = $perMsLimit;
        return $this->runData;
    }

    private function groupRoutesByMs(array $routes, int $perMsLimit): array {
        $groups = [];
        foreach ($routes as $route) {
            $msId = (int)($route['s_ms_id'] ?? 0);
            $groups[$msId]['ms_id'] = $msId;
            $groups[$msId]['ms_name'] = $route['ms_name'] ?? 'Unknown';
            $groups[$msId]['ms_scope'] = $route['ms_scope'] ?? '';
            $groups[$msId]['routes'][] = $route;
        }
        $result = [];
        foreach ($groups as $group) {
            $routesList = $group['routes'] ?? [];
            $total = count($routesList);
            if ($perMsLimit > 0 && $total > $perMsLimit) {
                $group['routes_total'] = $total;
                $group['routes'] = array_slice($routesList, 0, $perMsLimit);
            } else {
                $group['routes_total'] = $total;
            }
            $result[] = $group;
        }
        return $result;
    }

    private function filterRestrictedRoutes(array $routes, PrivilegeService $priv): array {
        $role = $priv->role();
        if ($role === 'system_admin') {
            return $routes;
        }
        if (!class_exists('RadAdmin\\VisibilityHelper')) {
            return $routes;
        }
        return array_values(array_filter($routes, function ($route) {
            $msId = (int)($route['s_ms_id'] ?? 0);
            if ($msId <= 0) {
                return true;
            }
            return !VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        }));
    }
}
