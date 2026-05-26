<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;

class Permissionbindings {
    private $runData = [];
    private $db;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
    }

    public function view() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $canManage = $priv->can('idm_manage');

        $request = $this->runData['request'];
        $allowedTypes = [
            'ms' => 'Microservicelet',
            'route' => 'Route',
        ];
        $objectType = $request->get['object_type'] ?? 'ms';
        if (!isset($allowedTypes[$objectType])) {
            $objectType = 'ms';
        }
        $objectId = (int)($request->get['object_id'] ?? 0);

        if ($request->method === 'POST' && $canManage) {
            $this->handlePost($objectType, $objectId);
            $redirect = $this->runData['route']['rad_admin_url'] . '/permissionbindings/view?object_type=' . urlencode($objectType) . '&object_id=' . (int)$objectId;
            header('Location: ' . $redirect);
            exit;
        }

        $objects = [];
        if (isset($allowedTypes['ms'])) {
            $objects['ms'] = $this->filterRestrictedMs($this->db->select('s_ms', [], true, ['id' => 'ASC']));
        }
        if (isset($allowedTypes['route'])) {
            $objects['route'] = $this->filterRoutes();
        }

        $bindings = [];
        $routePublicRestricted = false;
        $selectedRouteScope = '';
        $msGlobalRestricted = false;
        $routesById = [];
        $msById = [];
        $selectedObject = null;
        $allowedRoleScopes = [];
        $validBindings = [];
        $invalidBindings = [];
        $childRoutes = [];
        if ($objectId > 0 && isset($objects[$objectType])) {
            $selectedObject = $this->findObject($objects[$objectType], $objectId);
            if (!$selectedObject) {
                $this->runData['request']->setAlert('Selected object is unavailable.', 'warning');
                $objectId = 0;
            }
        }
        if ($objectId > 0 && $selectedObject) {
            $bindings = $this->db->select('s_permission_binding', [
                's_object_type' => $objectType,
                's_object_id' => $objectId,
            ], true);
            $allowedRoleScopes = $this->allowedRoleScopesForObject($objectType, $selectedObject);
            if ($objectType === 'route') {
                $route = $selectedObject;
                if ($route) {
                    $selectedRouteScope = strtolower($route['s_scope'] ?? '') === 'global' ? 'public' : 'private';
                    $routePublicRestricted = $selectedRouteScope === 'public';
                }
            } elseif ($objectType === 'ms') {
                $ms = $selectedObject;
                if ($ms) {
                    $msGlobalRestricted = strtolower($ms['s_scope'] ?? '') === 'global';
                    $childRoutes = $this->buildChildRouteMeta((int)($ms['id'] ?? 0));
                }
            }
        }

        $roles = $this->db->select('s_role', [], true, ['s_role_name' => 'ASC']);
        $rolesById = [];
        foreach ($roles as $role) {
            $rolesById[(int)$role['id']] = $role;
        }
        foreach ($objects['ms'] ?? [] as $ms) {
            $msById[(int)$ms['id']] = $ms;
        }
        foreach ($objects['route'] ?? [] as $route) {
            $routesById[(int)$route['id']] = $route;
        }
        if (!empty($bindings)) {
            foreach ($bindings as $binding) {
                $roleScope = strtolower((string)($rolesById[(int)($binding['s_role_id'] ?? 0)]['s_scope'] ?? ''));
                if (!empty($allowedRoleScopes) && in_array($roleScope, array_map('strtolower', $allowedRoleScopes), true)) {
                    $validBindings[] = $binding;
                } else {
                    $invalidBindings[] = $binding;
                }
            }
        }

        $this->runData['route']['h1'] = 'Permission Bindings';
        $this->runData['route']['meta_title'] = 'Permission Bindings';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Permission Bindings' => '',
        ];

        $this->runData['data']['object_type'] = $objectType;
        $this->runData['data']['object_id'] = $objectId;
        $this->runData['data']['bindings'] = $bindings;
        $filteredRoles = $objectId > 0 && !empty($allowedRoleScopes)
            ? $this->filterRolesByScopes($roles, $allowedRoleScopes)
            : array_values(array_filter($roles, function ($role) {
                return strtolower((string)($role['s_scope'] ?? '')) !== 'ms';
            }));
        $this->runData['data']['roles'] = $filteredRoles;
        $this->runData['data']['objects'] = $objects;
        $this->runData['data']['object_types'] = $allowedTypes;
        $this->runData['data']['can_idm_manage'] = $canManage;
        $this->runData['data']['route_public_restricted'] = $routePublicRestricted;
        $this->runData['data']['selected_route_scope'] = $selectedRouteScope;
        $this->runData['data']['ms_global_restricted'] = $msGlobalRestricted;
        $this->runData['data']['roles_by_id'] = $rolesById;
        $this->runData['data']['routes_by_id'] = $routesById;
        $this->runData['data']['ms_by_id'] = $msById;
        $this->runData['data']['allowed_role_scopes'] = $allowedRoleScopes;
        $this->runData['data']['selected_object'] = $selectedObject;
        $this->runData['data']['valid_bindings'] = $validBindings;
        $this->runData['data']['invalid_bindings'] = $invalidBindings;
        $this->runData['data']['child_routes'] = $childRoutes;
        return $this->runData;
    }

    private function handlePost(string $objectType, int $objectId): void {
        $request = $this->runData['request'];
        $target = $this->resolveAllowedTargetObject($objectType, $objectId);
        if (!$target) {
            $this->runData['request']->setAlert('Selected object is unavailable for permission binding changes.', 'warning');
            return;
        }
        $allowedRoleScopes = $this->allowedRoleScopesForObject($objectType, $target);
        if (empty($allowedRoleScopes)) {
            $this->runData['request']->setAlert('This object does not support permission bindings.', 'warning');
            return;
        }
        if (!empty($request->post['binding_id'])) {
            $bindingId = (int)$request->post['binding_id'];
            $bindingRows = $this->db->select('s_permission_binding', [
                'id' => $bindingId,
                's_object_type' => $objectType,
                's_object_id' => $objectId,
            ], true);
            if (!empty($bindingRows)) {
                $this->db->delete('s_permission_binding', ['id' => $bindingId]);
                $this->logBindingEvent('remove', $objectType, $objectId, [
                    'binding_id' => $bindingId,
                ]);
            } else {
                $this->runData['request']->setAlert('Binding was not found for the selected object.', 'warning');
            }
            return;
        }
        if (!empty($request->post['cleanup_invalid_bindings'])) {
            $removed = $this->removeInvalidBindings($objectType, $objectId, $allowedRoleScopes);
            $level = $removed > 0 ? 'success' : 'info';
            $this->runData['request']->setAlert(
                $removed > 0 ? sprintf('Removed %d incompatible binding(s).', $removed) : 'No incompatible bindings were found.',
                $level
            );
            return;
        }
        $roleIds = $request->post['role_ids'] ?? null;
        if ($roleIds !== null && is_array($roleIds)) {
            $this->handleBulkAdd($objectType, $objectId, $roleIds, $allowedRoleScopes);
            return;
        }
        $roleId = (int)($request->post['role_id'] ?? 0);
        if ($objectId <= 0 || $roleId <= 0) {
            return;
        }
        // Enforce allowed object types
        if (!in_array($objectType, ['ms', 'route'], true)) {
            return;
        }
        if (!$this->isRoleAllowedForScopes($roleId, $allowedRoleScopes)) {
            $this->runData['request']->setAlert('Selected role scope is not valid for this object.', 'warning');
            return;
        }

        $existing = $this->db->select('s_permission_binding', [
            's_object_type' => $objectType,
            's_object_id' => $objectId,
            's_role_id' => $roleId,
        ], true);
        if (empty($existing)) {
            $this->db->insert('s_permission_binding', [
                's_object_type' => $objectType,
                's_object_id' => $objectId,
                's_role_id' => $roleId,
            ]);
            $this->logBindingEvent('add', $objectType, $objectId, [
                'role_id' => $roleId,
            ]);
        }
        $message = empty($existing) ? 'Binding added.' : 'Binding already exists.';
        $propagationSummary = $this->handleRouteInheritancePropagation($objectType, $target);
        if ($propagationSummary !== '') {
            $message .= ' ' . $propagationSummary;
        }
        $this->runData['request']->setAlert($message, 'success');
    }

    private function handleBulkAdd(string $objectType, int $objectId, array $roleIds, array $allowedRoleScopes): void {
        if ($objectId <= 0) {
            return;
        }
        if (!in_array($objectType, ['ms', 'route'], true)) {
            return;
        }
        $roleIds = array_values(array_filter(array_map('intval', $roleIds)));
        if (empty($roleIds)) {
            return;
        }
        $existing = $this->db->select('s_permission_binding', [
            's_object_type' => $objectType,
            's_object_id' => $objectId,
        ], true);
        $existingMap = [];
        foreach ($existing as $row) {
            $existingMap[(int)$row['s_role_id']] = true;
        }

        $added = 0;
        $skipped = 0;
        foreach ($roleIds as $roleId) {
            if (!$this->isRoleAllowedForScopes($roleId, $allowedRoleScopes)) {
                $skipped++;
                continue;
            }
            if (isset($existingMap[$roleId])) {
                $skipped++;
                continue;
            }
            $this->db->insert('s_permission_binding', [
                's_object_type' => $objectType,
                's_object_id' => $objectId,
                's_role_id' => $roleId,
            ]);
            $this->logBindingEvent('add', $objectType, $objectId, [
                'role_id' => $roleId,
                'bulk' => true,
            ]);
            $added++;
        }
        $message = sprintf('Bulk binding complete: %d added, %d skipped.', $added, $skipped);
        $propagationSummary = $this->handleRouteInheritancePropagation($objectType, $this->resolveAllowedTargetObject($objectType, $objectId));
        if ($propagationSummary !== '') {
            $message .= ' ' . $propagationSummary;
        }
        $this->runData['request']->setAlert($message, 'success');
    }

    private function handleRouteInheritancePropagation(string $objectType, ?array $target): string {
        if ($objectType !== 'ms' || !$target) {
            return '';
        }

        $mode = strtolower(trim((string)($this->runData['request']->post['route_binding_propagation'] ?? 'none')));
        if (!in_array($mode, ['inherit_all', 'inherit_selected'], true)) {
            return '';
        }

        $msId = (int)($target['id'] ?? 0);
        if ($msId <= 0) {
            return '';
        }

        $childRoutes = $this->buildChildRouteMeta($msId);
        if (empty($childRoutes)) {
            return 'No child routes were available for propagation.';
        }

        $eligibleRouteIds = [];
        foreach ($childRoutes as $route) {
            $eligibleRouteIds[(int)$route['id']] = true;
        }

        if ($mode === 'inherit_all') {
            $routeIds = array_keys($eligibleRouteIds);
        } else {
            $requestedRouteIds = $this->runData['request']->post['route_ids'] ?? [];
            if (!is_array($requestedRouteIds)) {
                $requestedRouteIds = [];
            }
            $routeIds = [];
            foreach ($requestedRouteIds as $routeId) {
                $routeId = (int)$routeId;
                if ($routeId > 0 && isset($eligibleRouteIds[$routeId])) {
                    $routeIds[$routeId] = $routeId;
                }
            }
            $routeIds = array_values($routeIds);
        }

        if (empty($routeIds)) {
            return $mode === 'inherit_selected'
                ? 'No valid child routes were selected for propagation.'
                : 'No child routes were available for propagation.';
        }

        $removed = 0;
        foreach ($routeIds as $routeId) {
            $countRows = $this->db->query(
                "SELECT COUNT(*) AS total
                 FROM s_permission_binding
                 WHERE s_object_type = 'route'
                   AND s_object_id = :rid
                   AND livestatus != '0'",
                [':rid' => $routeId]
            );
            $removed += (int)($countRows[0]['total'] ?? 0);
            $this->db->delete('s_permission_binding', [
                's_object_type' => 'route',
                's_object_id' => $routeId,
            ]);
        }

        $this->logBindingEvent('propagate_inherit', 'ms', $msId, [
            'propagation_mode' => $mode,
            'route_count' => count($routeIds),
            'removed_route_bindings' => $removed,
            'route_ids' => $routeIds,
        ]);

        return sprintf(
            '%d child route(s) were reset to inherit the parent microservicelet bindings; %d explicit route binding(s) removed.',
            count($routeIds),
            $removed
        );
    }

    private function filterRoutes(): array {
        $routes = $this->db->query('SELECT r.*, m.s_name AS ms_name, m.s_scope, m.id as s_ms_id FROM s_msroute r LEFT JOIN s_ms m ON m.id = r.s_ms_id ORDER BY r.id ASC');
        $msAllowed = $this->buildAllowedMsIds();
        return array_values(array_filter($routes, function ($route) use ($msAllowed) {
            $msId = (int)($route['s_ms_id'] ?? 0);
            $scope = strtolower((string)($route['s_scope'] ?? 'platform'));
            if ($scope === 'global') {
                return false;
            }
            return $msId === 0 || isset($msAllowed[$msId]);
        }));
    }

    private function filterRestrictedMs(array $msList): array {
        $role = (new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []))->role();
        $msList = array_values(array_filter($msList, function ($ms) {
            return strtolower((string)($ms['s_scope'] ?? 'platform')) !== 'global';
        }));
        if ($role === 'system_admin') {
            return $msList;
        }
        return array_values(array_filter($msList, function ($ms) {
            $msId = (int)($ms['id'] ?? 0);
            return $msId > 0 && !VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        }));
    }

    private function buildAllowedMsIds(): array {
        $allowed = $this->filterRestrictedMs($this->db->select('s_ms', [], true));
        $map = [];
        foreach ($allowed as $ms) {
            $map[(int)$ms['id']] = true;
        }
        return $map;
    }

    private function resolveAllowedTargetObject(string $objectType, int $objectId): ?array {
        if ($objectId <= 0) {
            return null;
        }
        if ($objectType === 'ms') {
            $objects = $this->filterRestrictedMs($this->db->select('s_ms', [], true, ['id' => 'ASC']));
            return $this->findObject($objects, $objectId);
        }
        if ($objectType === 'route') {
            return $this->findObject($this->filterRoutes(), $objectId);
        }
        return null;
    }

    private function allowedRoleScopesForObject(string $objectType, array $object): array {
        if ($objectType === 'ms') {
            $scope = strtolower((string)($object['s_scope'] ?? 'platform'));
        } else {
            $scope = strtolower((string)($object['s_scope'] ?? 'platform'));
        }
        if ($scope === 'global') {
            return [];
        }
        if ($scope === 'workspace') {
            return ['workspace'];
        }
        return ['platform', 'global'];
    }

    private function filterRolesByScopes(array $roles, array $allowedScopes): array {
        $allowedMap = array_fill_keys(array_map('strtolower', $allowedScopes), true);
        return array_values(array_filter($roles, function ($role) use ($allowedMap) {
            $scope = strtolower((string)($role['s_scope'] ?? ''));
            return isset($allowedMap[$scope]);
        }));
    }

    private function isRoleAllowedForScopes(int $roleId, array $allowedScopes): bool {
        if ($roleId <= 0 || empty($allowedScopes)) {
            return false;
        }
        $rows = $this->db->select('s_role', ['id' => $roleId], true);
        if (empty($rows)) {
            return false;
        }
        $scope = strtolower((string)($rows[0]['s_scope'] ?? ''));
        return in_array($scope, array_map('strtolower', $allowedScopes), true);
    }

    private function removeInvalidBindings(string $objectType, int $objectId, array $allowedScopes): int {
        $bindings = $this->db->select('s_permission_binding', [
            's_object_type' => $objectType,
            's_object_id' => $objectId,
        ], true);
        if (empty($bindings)) {
            return 0;
        }
        $allowedMap = array_fill_keys(array_map('strtolower', $allowedScopes), true);
        $removed = 0;
        foreach ($bindings as $binding) {
            $roleId = (int)($binding['s_role_id'] ?? 0);
            if ($roleId <= 0) {
                continue;
            }
            $roleRows = $this->db->select('s_role', ['id' => $roleId], true);
            $scope = strtolower((string)($roleRows[0]['s_scope'] ?? ''));
            if (isset($allowedMap[$scope])) {
                continue;
            }
            $this->db->delete('s_permission_binding', ['id' => (int)$binding['id']]);
            $this->logBindingEvent('remove', $objectType, $objectId, [
                'binding_id' => (int)$binding['id'],
                'cleanup' => true,
                'role_id' => $roleId,
            ]);
            $removed++;
        }
        return $removed;
    }

    private function findObject(array $list, int $id) {
        foreach ($list as $item) {
            if ((int)($item['id'] ?? 0) === $id) {
                return $item;
            }
        }
        return null;
    }

    private function buildChildRouteMeta(int $msId): array {
        if ($msId <= 0) {
            return [];
        }
        $routes = array_values(array_filter($this->filterRoutes(), function ($route) use ($msId) {
            return (int)($route['s_ms_id'] ?? 0) === $msId;
        }));
        if (empty($routes)) {
            return [];
        }

        $msHasBindings = !empty($this->db->query(
            "SELECT COUNT(*) AS total
             FROM s_permission_binding
             WHERE s_object_type = 'ms'
               AND s_object_id = :msid
               AND livestatus != '0'",
            [':msid' => $msId]
        )[0]['total'] ?? 0);

        $result = [];
        foreach ($routes as $route) {
            $routeId = (int)($route['id'] ?? 0);
            if ($routeId <= 0) {
                continue;
            }
            $routeHasBindings = !empty($this->db->query(
                "SELECT COUNT(*) AS total
                 FROM s_permission_binding
                 WHERE s_object_type = 'route'
                   AND s_object_id = :rid
                   AND livestatus != '0'",
                [':rid' => $routeId]
            )[0]['total'] ?? 0);

            $bindingState = $routeHasBindings ? 'route' : ($msHasBindings ? 'inherits' : 'none');
            $result[] = [
                'id' => $routeId,
                'uid' => $route['uid'] ?? '',
                's_name' => $route['s_name'] ?? '',
                's_href' => $route['s_href'] ?? '',
                'binding_state' => $bindingState,
            ];
        }

        return $result;
    }

    private function logBindingEvent(string $action, string $objectType, int $objectId, array $extra = []): void {
        try {
            $activitySvc = new \Core\Sys\ActivityService($this->db);
            $activitySvc->log([
                's_actor_id' => (int)($this->runData['entity']['id'] ?? 0) ?: null,
                's_object_type' => 'binding_' . $objectType,
                's_object_id' => $objectId,
                's_action' => $action,
                's_message' => sprintf('Binding %s: %s #%d', $action, $objectType, $objectId),
                's_payload' => array_merge([
                    'object_type' => $objectType,
                    'object_id' => $objectId,
                ], $extra),
            ]);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }
}
