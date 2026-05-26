<?php
namespace RadAdmin;

class WorkspaceMembershipHelper {
    private $db;
    private $actorId;
    private $legacyRoleMapAvailable;

    public function __construct($db, ?int $actorId = null) {
        $this->db = $db;
        $this->actorId = $actorId;
        $this->legacyRoleMapAvailable = null;
    }

    /**
     * Synchronize legacy s_roles_and_users JSON into normalized tables.
     */
    public function syncLegacyAssignments(array $space): void {
        if (!$this->hasLegacyRoleMap()) {
            return;
        }
        if (empty($space['id'])) {
            return;
        }
        $map = json_decode($space['s_roles_and_users'] ?? '[]', true);
        if (!is_array($map) || empty($map)) {
            return;
        }

        $spaceId = (int)$space['id'];
        foreach ($map as $roleId => $userIds) {
            if (!is_array($userIds)) {
                continue;
            }
            foreach ($userIds as $userId) {
                $this->assignUserRole($spaceId, (int)$userId, (int)$roleId, 'workspace', null, false);
            }
        }
        $this->rebuildLegacyMap($spaceId);
    }

    /**
     * Assign a role to a user within a workspace.
     * Returns true when a new assignment was created.
     */
    public function assignUserRole(
        int $spaceId,
        int $userId,
        int $roleId,
        string $scopeLevel = 'workspace',
        ?int $msId = null,
        bool $refreshLegacy = true
    ): bool {
        if ($spaceId <= 0 || $userId <= 0 || $roleId <= 0) {
            return false;
        }

        $scopeLevel = strtolower($scopeLevel);
        if (!in_array($scopeLevel, ['workspace', 'ms'], true)) {
            $scopeLevel = 'workspace';
        }
        if ($scopeLevel === 'ms' && ($msId === null || $msId <= 0)) {
            return false;
        }

        if ($scopeLevel === 'ms') {
            $existing = $this->findMembershipRow($spaceId, $userId, 'ms', $msId);
            if (!empty($existing)) {
                if ((int)($existing['s_role_id'] ?? 0) === $roleId && ($existing['livestatus'] ?? '') === '1') {
                    return false;
                }
                $this->db->update('s_space_membership', [
                    's_role_id' => $roleId,
                    's_scope_level' => 'ms',
                    's_ms_id' => (int)$msId,
                    'livestatus' => '1',
                ], ['id' => (int)$existing['id']], ['updatedby' => $this->actorId]);
                $added = true;
            } else {
                $id = $this->db->insert(
                    's_space_membership',
                    [
                        's_entity_id' => $userId,
                        's_role_id' => $roleId,
                        's_scope_level' => 'ms',
                        's_ms_id' => (int)$msId,
                    ],
                    [
                        'space_id' => $spaceId,
                        'createdby' => $this->actorId,
                        'livestatus' => '1',
                    ]
                );
                $added = (bool)$id;
            }
        } else {
            $membershipId = $this->ensureMembership($spaceId, $userId);
            if ($membershipId <= 0) {
                return false;
            }
            $added = $this->ensureMembershipRole($spaceId, $membershipId, $roleId, $scopeLevel, $msId);
        }
        if ($added && $refreshLegacy) {
            $this->rebuildLegacyMap($spaceId);
        }
        return $added;
    }

    /**
     * Remove a role assignment for a user.
     */
    public function removeUserRole(
        int $spaceId,
        int $userId,
        int $roleId,
        string $scopeLevel = 'workspace',
        ?int $msId = null,
        bool $refreshLegacy = true
    ): bool {
        if ($spaceId <= 0 || $userId <= 0 || $roleId <= 0) {
            return false;
        }

        $scopeLevel = strtolower($scopeLevel);
        if (!in_array($scopeLevel, ['workspace', 'ms'], true)) {
            $scopeLevel = 'workspace';
        }

        $membershipRow = $this->findMembershipRow($spaceId, $userId, $scopeLevel, $msId);
        if (empty($membershipRow)) {
            return false;
        }
        $membershipId = (int)$membershipRow['id'];

        if ((int)($membershipRow['s_role_id'] ?? 0) !== $roleId) {
            return false;
        }

        $this->db->update('s_space_membership', [
            'livestatus' => '2',
            's_role_id' => null,
            's_scope_level' => 'workspace',
            's_ms_id' => null,
            's_effective_from' => null,
            's_effective_to' => null,
        ], ['id' => $membershipId], ['updatedby' => $this->actorId]);

        if ($refreshLegacy) {
            $this->rebuildLegacyMap($spaceId);
        }
        return true;
    }

    /**
     * Fetch workspace assignments for display.
     */
    public function fetchAssignments(int $spaceId): array {
        if ($spaceId <= 0) {
            return [];
        }

        $sql = "SELECT m.id AS membership_id,
                       m.s_entity_id AS user_id,
                       e.s_name AS user_name,
                       e.s_identity AS user_identity,
                       m.s_role_id AS role_id,
                       r.s_role_name AS role_name
                FROM s_space_membership AS m
                LEFT JOIN s_role AS r ON r.id = m.s_role_id
                LEFT JOIN s_entity AS e ON e.id = m.s_entity_id
                WHERE m.space_id = :space
                  AND m.livestatus = '1'
                  AND m.s_scope_level = 'workspace'
                ORDER BY e.s_name, r.s_role_name";
        return $this->db->query($sql, [':space' => $spaceId]);
    }

    /**
     * Regenerate s_roles_and_users JSON from normalized tables.
     */
    public function rebuildLegacyMap(int $spaceId): void {
        if (!$this->hasLegacyRoleMap()) {
            return;
        }
        if ($spaceId <= 0) {
            return;
        }
        $sql = "SELECT m.s_role_id, m.s_entity_id
                FROM s_space_membership m
                WHERE m.livestatus = '1'
                  AND m.space_id = :space
                  AND m.s_scope_level = 'workspace'
                  AND m.s_role_id IS NOT NULL";
        $rows = $this->db->query($sql, [':space' => $spaceId]);

        $map = [];
        foreach ($rows as $row) {
            $roleId = (int)$row['s_role_id'];
            $userId = (int)$row['s_entity_id'];
            if ($roleId <= 0 || $userId <= 0) {
                continue;
            }
            if (!isset($map[$roleId])) {
                $map[$roleId] = [];
            }
            if (!in_array($userId, $map[$roleId], true)) {
                $map[$roleId][] = $userId;
            }
        }

        try {
            $this->db->update('s_space', [
                's_roles_and_users' => json_encode($map),
            ], ['id' => $spaceId]);
        } catch (\Throwable $e) {
            $this->legacyRoleMapAvailable = false;
        }
    }

    /**
     * Return list of user IDs already assigned to a workspace (any role).
     */
    public function getAssignedUserIds(int $spaceId): array {
        $rows = $this->db->query(
            "SELECT DISTINCT m.s_entity_id
             FROM s_space_membership m
             WHERE m.space_id = :space AND m.livestatus = '1'",
            [':space' => $spaceId]
        );
        return array_values(array_unique(array_map(static function ($row) {
            return (int)$row['s_entity_id'];
        }, $rows)));
    }

    private function ensureMembership(int $spaceId, int $userId): int {
        $rows = $this->db->select('s_space_membership', [
            'space_id' => $spaceId,
            's_entity_id' => $userId,
            's_scope_level' => 'workspace',
        ], true);
        if (!empty($rows)) {
            $membershipId = (int)$rows[0]['id'];
            if ($rows[0]['livestatus'] !== '1') {
                $this->db->update('s_space_membership', [
                    'livestatus' => '1',
                ], ['id' => $membershipId], ['updatedby' => $this->actorId]);
            }
            return $membershipId;
        }

        return $this->db->insert(
            's_space_membership',
            [
                's_entity_id' => $userId,
            ],
            [
                'space_id' => $spaceId,
                'createdby' => $this->actorId,
                'livestatus' => '1',
            ]
        );
    }

    private function ensureMembershipRole(
        int $spaceId,
        int $membershipId,
        int $roleId,
        string $scopeLevel,
        ?int $msId
    ): bool {
        $scopeLevel = strtolower($scopeLevel);
        if (!in_array($scopeLevel, ['workspace', 'ms'], true)) {
            $scopeLevel = 'workspace';
        }
        if ($scopeLevel === 'ms' && ($msId === null || $msId <= 0)) {
            return false;
        }

        $rows = $this->db->select('s_space_membership', ['id' => $membershipId], true);
        if (empty($rows)) {
            return false;
        }
        $existingRole = (int)($rows[0]['s_role_id'] ?? 0);
        if ($existingRole === $roleId && $rows[0]['livestatus'] === '1') {
            return false;
        }

        $this->db->update('s_space_membership', [
            's_role_id' => $roleId,
            's_scope_level' => $scopeLevel,
            's_ms_id' => $scopeLevel === 'ms' ? $msId : null,
            'livestatus' => '1',
        ], ['id' => $membershipId], ['updatedby' => $this->actorId]);
        return true;
    }

    private function findMembershipRow(int $spaceId, int $userId, string $scopeLevel, ?int $msId): ?array {
        $scopeLevel = strtolower($scopeLevel);
        if (!in_array($scopeLevel, ['workspace', 'ms'], true)) {
            $scopeLevel = 'workspace';
        }
        $criteria = [
            'space_id' => $spaceId,
            's_entity_id' => $userId,
            's_scope_level' => $scopeLevel,
        ];
        if ($scopeLevel === 'ms') {
            $criteria['s_ms_id'] = (int)$msId;
        }
        $rows = $this->db->select('s_space_membership', $criteria, true);
        if (empty($rows)) {
            return null;
        }
        return $rows[0];
    }

    private function hasLegacyRoleMap(): bool {
        if ($this->legacyRoleMapAvailable !== null) {
            return $this->legacyRoleMapAvailable;
        }
        try {
            $rows = $this->db->query("SHOW COLUMNS FROM s_space LIKE 's_roles_and_users'");
            $this->legacyRoleMapAvailable = !empty($rows);
        } catch (\Throwable $e) {
            $this->legacyRoleMapAvailable = false;
        }
        return $this->legacyRoleMapAvailable;
    }
}
