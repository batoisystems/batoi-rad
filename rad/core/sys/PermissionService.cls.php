<?php
namespace Core\Sys;

/**
 * Resolves workspace roles for a principal and evaluates permission bindings.
 */
class PermissionService {
    private $db;
    private $roleCache = [];
    private $bindingCache = [];
    private $bindingPresence = [];
    private $entityRoleCache = [];
    private $objectScopeCache = [];
    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Returns true when effective bindings exist for an object.
     */
    public function hasBindings(string $objectType, int $objectId): bool {
        $key = $objectType . ':' . $objectId;
        if (!array_key_exists($key, $this->bindingPresence)) {
            try {
                $this->bindingPresence[$key] = !empty($this->getBindingsForObject($objectType, $objectId));
            } catch (\Throwable $e) {
                error_log('PermissionService.hasBindings error: ' . $e->getMessage());
                $this->bindingPresence[$key] = false;
            }
        }

        return $this->bindingPresence[$key];
    }

    /**
     * Evaluates if the current entity has the required access level for an object.
     */
    public function canAccess(?int $entityId, string $objectType, int $objectId, string $requiredLevel = 'use', ?int $spaceId = null, ?int $msId = null): bool {
        if (!$this->hasBindings($objectType, $objectId)) {
            return false;
        }

        if ($entityId === null) {
            return false;
        }

        $roleSet = $this->resolveRoleSet($entityId, $spaceId, $msId);
        $roles = $this->resolveApplicableRolesForObject($roleSet, $objectType, $objectId);
        if (empty($roles)) {
            return false;
        }

        $bindings = $this->getBindingsForObject($objectType, $objectId);
        if (empty($bindings)) {
            return false;
        }

        foreach ($bindings as $binding) {
            if (in_array((int)$binding['s_role_id'], $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve effective role IDs for the entity based on memberships.
     */
    public function resolveRoles(int $entityId, ?int $spaceId = null, ?int $msId = null): array {
        $roleSet = $this->resolveRoleSet($entityId, $spaceId, $msId);
        return $roleSet['roles'];
    }

    /**
     * Resolve effective role IDs and expose workspace/global role ids.
     *
     * Returns:
     * - workspace_role_id (int|null)
     * - global_role_id (int|null)
     * - roles (array<int>)
     */
    public function resolveRoleSet(int $entityId, ?int $spaceId = null, ?int $msId = null): array {
        $cacheKey = implode(':', [$entityId, $spaceId ?? 'global', $msId ?? 'none']);
        if (isset($this->roleCache[$cacheKey])) {
            $cached = $this->roleCache[$cacheKey];
            if (is_array($cached) && array_key_exists('roles', $cached)) {
                return $cached;
            }
            return [
                'workspace_role_id' => null,
                'global_role_id' => null,
                'roles' => is_array($cached) ? $cached : [],
            ];
        }

        $roleSet = [
            'workspace_role_id' => null,
            'global_role_id' => null,
            'roles' => [],
        ];
        $params = [':entity' => $entityId];
        $roleSql = "SELECT s_role_id, s_scope_level, s_ms_id, s_effective_from, s_effective_to
                           , createstamp, updatestamp
                    FROM s_space_membership
                    WHERE livestatus != '0' AND s_entity_id = :entity";
        if ($spaceId !== null) {
            $roleSql .= " AND space_id = :space_id";
            $params[':space_id'] = $spaceId;
        }
        $roleSql .= " ORDER BY COALESCE(updatestamp, createstamp) DESC, id DESC";

        try {
            $rows = $this->db->query($roleSql, $params);
        } catch (\Throwable $e) {
            error_log('PermissionService.resolveRoles error: ' . $e->getMessage());
            $rows = [];
        }

        $now = new \DateTimeImmutable('now');
        foreach ($rows as $row) {
            if (empty($row['s_role_id'])) {
                continue;
            }
            if (!$this->isWithinEffectiveWindow($row, $now)) {
                continue;
            }

            $scopeLevel = strtolower(trim((string)($row['s_scope_level'] ?? 'workspace')));
            if ($roleSet['workspace_role_id'] === null && ($scopeLevel === '' || $scopeLevel === 'workspace')) {
                $roleSet['workspace_role_id'] = (int)$row['s_role_id'];
            }
        }

        $globalRoles = $this->fetchGlobalRoles($entityId);
        if (!empty($globalRoles)) {
            $roleSet['global_role_id'] = (int)$globalRoles[0];
        }

        $roles = [];
        if (!empty($roleSet['workspace_role_id'])) {
            $roles[] = (int)$roleSet['workspace_role_id'];
        }
        if (!empty($roleSet['global_role_id'])) {
            $roles[] = (int)$roleSet['global_role_id'];
        }
        $roleSet['roles'] = array_values(array_unique($roles));

        return $this->roleCache[$cacheKey] = $roleSet;
    }

    private function isWithinEffectiveWindow(array $row, \DateTimeImmutable $now): bool {
        $from = $row['s_effective_from'] ?? null;
        if ($from && $from !== '0000-00-00 00:00:00') {
            $fromDate = new \DateTimeImmutable($from);
            if ($now < $fromDate) {
                return false;
            }
        }

        $to = $row['s_effective_to'] ?? null;
        if ($to && $to !== '0000-00-00 00:00:00') {
            $toDate = new \DateTimeImmutable($to);
            if ($now > $toDate) {
                return false;
            }
        }

        return true;
    }

    private function getBindingsForObject(string $objectType, int $objectId): array {
        $key = $objectType . ':' . $objectId;
        if (!isset($this->bindingCache[$key])) {
            try {
                $allowedScopes = $this->allowedRoleScopesForObject($objectType, $objectId);
                if (empty($allowedScopes)) {
                    $this->bindingCache[$key] = [];
                    return $this->bindingCache[$key];
                }
                $scopePlaceholders = [];
                $params = [
                    ':otype' => $objectType,
                    ':oid' => $objectId,
                ];
                foreach (array_values($allowedScopes) as $index => $scope) {
                    $placeholder = ':scope_' . $index;
                    $scopePlaceholders[] = $placeholder;
                    $params[$placeholder] = $scope;
                }
                $this->bindingCache[$key] = $this->db->query(
                    "SELECT b.s_role_id
                     FROM s_permission_binding b
                     INNER JOIN s_role r ON r.id = b.s_role_id
                     WHERE b.s_object_type = :otype
                       AND b.s_object_id = :oid
                       AND b.livestatus != '0'
                       AND r.livestatus = '1'
                       AND LOWER(r.s_scope) IN (" . implode(', ', $scopePlaceholders) . ")",
                    [
                        ...$params,
                    ]
                );
            } catch (\Throwable $e) {
                error_log('PermissionService.getBindingsForObject error: ' . $e->getMessage());
                $this->bindingCache[$key] = [];
            }
        }

        return $this->bindingCache[$key];
    }

    private function resolveApplicableRolesForObject(array $roleSet, string $objectType, int $objectId): array {
        $scope = $this->resolveObjectScope($objectType, $objectId);
        if ($scope === 'workspace') {
            return !empty($roleSet['workspace_role_id']) ? [(int)$roleSet['workspace_role_id']] : [];
        }
        return !empty($roleSet['global_role_id']) ? [(int)$roleSet['global_role_id']] : [];
    }

    private function allowedRoleScopesForObject(string $objectType, int $objectId): array {
        $scope = $this->resolveObjectScope($objectType, $objectId);
        if ($scope === 'global') {
            return [];
        }
        if ($scope === 'workspace') {
            return ['workspace'];
        }
        return ['platform', 'global'];
    }

    private function resolveObjectScope(string $objectType, int $objectId): string {
        $cacheKey = $objectType . ':' . $objectId;
        if (isset($this->objectScopeCache[$cacheKey])) {
            return $this->objectScopeCache[$cacheKey];
        }

        $scope = 'platform';
        try {
            if ($objectType === 'ms') {
                $rows = $this->db->select('s_ms', ['id' => $objectId], true);
                $scope = strtolower((string)($rows[0]['s_scope'] ?? 'platform'));
            } elseif ($objectType === 'route') {
                $rows = $this->db->query(
                    "SELECT m.s_scope
                     FROM s_msroute r
                     INNER JOIN s_ms m ON m.id = r.s_ms_id
                     WHERE r.id = :route
                     LIMIT 1",
                    [':route' => $objectId]
                );
                $scope = strtolower((string)($rows[0]['s_scope'] ?? 'platform'));
            }
        } catch (\Throwable $e) {
            error_log('PermissionService.resolveObjectScope error: ' . $e->getMessage());
        }

        return $this->objectScopeCache[$cacheKey] = $scope;
    }

    private function fetchGlobalRoles(int $entityId): array {
        if (isset($this->entityRoleCache[$entityId])) {
            return $this->entityRoleCache[$entityId];
        }

        try {
            $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        } catch (\Throwable $e) {
            error_log('PermissionService.fetchGlobalRoles error: ' . $e->getMessage());
            return $this->entityRoleCache[$entityId] = [];
        }

        if (empty($rows)) {
            return $this->entityRoleCache[$entityId] = [];
        }

        $roleId = (int)($rows[0]['s_nonsaas_role_id'] ?? 0);
        if ($roleId > 0) {
            return $this->entityRoleCache[$entityId] = [$roleId];
        }

        return $this->entityRoleCache[$entityId] = [];
    }
}
