<?php
namespace Core\App;

use InvalidArgumentException;

/**
 * User service for application developers.
 *
 * Provides CRUD helpers for s_entity users plus basic membership lookups.
 * All methods return data/booleans or throw exceptions; no RAD Admin alerts.
 */
class User {
    private $db;
    private $config;
    private $actorId;

    public function __construct(array $runData) {
        if (empty($runData['db'])) {
            throw new InvalidArgumentException('Database handle is required for User service.');
        }
        $this->db = $runData['db'];
        $this->config = $runData['config'] ?? [];
        $this->actorId = (int)($runData['entity']['id'] ?? 1);
    }

    /**
     * List users with optional livestatus filter.
     *
     * @param array $opts Optional filters: livestatus ('0','1','2','3')
     * @return array List of user rows from s_entity
     */
    public function list(array $opts = []): array {
        $where = ['s_type' => 'U'];
        if (isset($opts['livestatus']) && $opts['livestatus'] !== '') {
            $where['livestatus'] = (string)$opts['livestatus'];
        }
        return $this->db->select('s_entity', $where, true);
    }

    /**
     * Fetch a user by id or uid.
     *
     * @param int|string $idOrUid Numeric id or UID
     * @return array|null User row or null when not found
     */
    public function get($idOrUid): ?array {
        $field = is_numeric($idOrUid) ? 'id' : 'uid';
        $rows = $this->db->select('s_entity', [$field => $idOrUid, 's_type' => 'U'], true);
        return $rows[0] ?? null;
    }

    /**
     * Create a user.
     *
     * Usage notes:
     * - Non-SaaS role: provide `nonsaas_role_id` (or legacy `role_id` when no workspace membership is requested).
     * - Workspace membership: provide `space_id` + `workspace_role_id` (role scope must be `workspace` or `ms`).
     * - Legacy shortcut: if `space_id` is set and `workspace_role_id` is omitted, `role_id` is treated as the workspace role.
     * - For `ms`-scoped workspace roles, `ms_id` is required. For `workspace` scope, `ms_id` must be omitted.
     * - If membership insert fails, the newly created user is rolled back.
     *
     * Parameters summary:
     * - s_identity (string, required): username/login.
     * - s_name (string, required): display name.
     * - password (string, required): raw password; hashed internally.
     * - nonsaas_role_id (int, optional): non-SaaS role id.
     * - role_id (int, optional): legacy alias for nonsaas_role_id unless `space_id` is provided; then it maps to workspace_role_id.
     * - space_id (int, optional): workspace id for membership creation.
     * - workspace_role_id (int, optional): workspace role id (scope workspace/ms).
     * - ms_id (int, optional): required if workspace role scope is `ms`.
     *
     * Example (non-SaaS only):
     *   $userId = $user->create([
     *     's_identity' => 'jane.doe',
     *     's_name' => 'Jane Doe',
     *     'password' => 'secret',
     *     'nonsaas_role_id' => 3,
     *   ]);
     *
     * Example (workspace membership):
     *   $userId = $user->create([
     *     's_identity' => 'jane.doe',
     *     's_name' => 'Jane Doe',
     *     'password' => 'secret',
     *     'space_id' => 12,
     *     'workspace_role_id' => 8,
     *   ]);
     *
     * Example (workspace membership with ms scope):
     *   $userId = $user->create([
     *     's_identity' => 'jane.doe',
     *     's_name' => 'Jane Doe',
     *     'password' => 'secret',
     *     'space_id' => 12,
     *     'workspace_role_id' => 22,
     *     'ms_id' => 5,
     *   ]);
     *
     * @param array $data Required keys: s_identity (username), s_name, password.
     *                   Optional: email, mobile, enable_mfa, role_id (non-SaaS), nonsaas_role_id,
     *                   access_ips, login_mode, agreement_signed, s_definition,
     *                   space_id, workspace_role_id, ms_id.
     * @return int Inserted user id
     *
     * @throws InvalidArgumentException on missing required fields or duplicate username.
     * @throws \RuntimeException if the insert fails to return an id.
     */
    public function create(array $data): int {
        $username = trim($data['s_identity'] ?? '');
        $name = trim($data['s_name'] ?? '');
        $password = $data['password'] ?? '';
        if ($username === '' || $name === '' || $password === '') {
            throw new InvalidArgumentException('s_identity, s_name and password are required');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('password must be at least 8 characters long');
        }
        $existing = $this->db->select('s_entity', ['s_identity' => $username, 's_type' => 'U'], true);
        if ($existing) {
            throw new InvalidArgumentException('Username already exists');
        }
        $spaceId = isset($data['space_id']) ? (int)$data['space_id'] : 0;
        $workspaceRoleId = isset($data['workspace_role_id']) ? (int)$data['workspace_role_id'] : null;
        $msId = isset($data['ms_id']) ? (int)$data['ms_id'] : null;
        $nonSaasRoleId = $data['nonsaas_role_id'] ?? ($data['role_id'] ?? null);

        if ($spaceId > 0 && $workspaceRoleId === null && array_key_exists('role_id', $data) && !array_key_exists('nonsaas_role_id', $data)) {
            $workspaceRoleId = (int)$data['role_id'];
            $nonSaasRoleId = null;
        }
        if ($spaceId > 0 && $workspaceRoleId === null) {
            throw new InvalidArgumentException('workspace_role_id is required when space_id is provided');
        }
        if ($spaceId === 0 && $workspaceRoleId !== null) {
            throw new InvalidArgumentException('space_id is required when workspace_role_id is provided');
        }

        $workspaceRole = null;
        if ($spaceId > 0) {
            $spaceRows = $this->db->select('s_space', ['id' => $spaceId, 'livestatus' => '1'], true);
            if (!$spaceRows) {
                throw new InvalidArgumentException('Workspace not found');
            }

            $roleRows = $this->db->select('s_role', ['id' => $workspaceRoleId, 'livestatus' => '1'], true);
            $workspaceRole = $roleRows[0] ?? null;
            if (!$workspaceRole || !in_array($workspaceRole['s_scope'], ['workspace', 'ms'], true)) {
                throw new InvalidArgumentException('Workspace role must be scope workspace or ms');
            }
            if ($workspaceRole['s_scope'] === 'ms' && !$msId) {
                throw new InvalidArgumentException('ms_id is required for ms-scoped workspace role');
            }
            if ($workspaceRole['s_scope'] === 'workspace' && $msId) {
                throw new InvalidArgumentException('ms_id is only valid for ms-scoped workspace roles');
            }
        }

        $payload = [
            's_type' => 'U',
            's_name' => $name,
            's_identity' => $username,
            's_identity_secret' => password_hash($password, PASSWORD_DEFAULT),
            's_nonsaas_role_id' => $nonSaasRoleId,
            's_email' => $data['email'] ?? null,
            's_mobile' => $data['mobile'] ?? null,
            's_login_mode' => $data['login_mode'] ?? 'SE',
            's_enable_mfa' => $data['enable_mfa'] ?? 'N',
            's_access_ips' => $data['access_ips'] ?? '',
            's_agreement_signed' => $data['agreement_signed'] ?? null,
            's_definition' => $data['s_definition'] ?? null,
        ];
        $state = [
            'createdby' => $this->actorId,
            'space_id' => 0,
            'livestatus' => '1',
        ];
        $id = $this->db->insert('s_entity', $payload, $state);
        if (!$id) {
            throw new \RuntimeException('User creation failed (no insert id returned).');
        }

        if ($spaceId > 0 && $workspaceRoleId) {
            $membershipPayload = [
                's_entity_id' => (int)$id,
                's_role_id' => $workspaceRoleId,
                's_scope_level' => $workspaceRole['s_scope'],
                's_ms_id' => $workspaceRole['s_scope'] === 'ms' ? $msId : null,
            ];
            $membershipState = [
                // Database::insert auto-adds default fields like space_id from state_data.
                'space_id' => $spaceId,
                'createdby' => $this->actorId,
                'livestatus' => '1',
            ];
            try {
                $membershipId = $this->db->insert('s_space_membership', $membershipPayload, $membershipState);
            } catch (\Throwable $e) {
                $this->db->delete('s_entity', ['id' => (int)$id, 's_type' => 'U']);
                throw $e;
            }
            if (!$membershipId) {
                $this->db->delete('s_entity', ['id' => (int)$id, 's_type' => 'U']);
                throw new \RuntimeException('User created but membership insert failed; user rolled back.');
            }
        }
        return (int)$id;
    }

    /**
     * Update a user (by uid).
     * $data may contain: s_name, s_identity, password, email, mobile, enable_mfa, role_id, access_ips, login_mode, agreement_signed
     *
     * @param string $uid User UID
     * @param array $data Fields to update (see above)
     * @return bool True if updated
     *
     * @throws InvalidArgumentException if user not found
     */
    public function update(string $uid, array $data): bool {
        $user = $this->get($uid);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }
        $updates = [];
        if (isset($data['s_name'])) {
            $updates['s_name'] = $data['s_name'];
        }
        if (isset($data['s_identity'])) {
            $updates['s_identity'] = $data['s_identity'];
        }
        if (!empty($data['password'])) {
            if (strlen((string)$data['password']) < 8) {
                throw new InvalidArgumentException('password must be at least 8 characters long');
            }
            $updates['s_identity_secret'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $fieldMap = [
            'email' => 's_email',
            'mobile' => 's_mobile',
            'enable_mfa' => 's_enable_mfa',
            'role_id' => 's_nonsaas_role_id',
            'access_ips' => 's_access_ips',
            'login_mode' => 's_login_mode',
            'agreement_signed' => 's_agreement_signed',
        ];
        foreach ($fieldMap as $inputKey => $column) {
            if (array_key_exists($inputKey, $data)) {
                $updates[$column] = $data[$inputKey];
            }
        }

        $state = ['updatedby' => $this->actorId];
        return (bool)$this->db->update('s_entity', $updates, ['uid' => $uid, 's_type' => 'U'], $state);
    }

    /**
     * Set or clear the non-SaaS role for a user.
     *
     * Example:
     *   $user->setNonSaasRole('user-uid', 3);
     *
     * @param string $uid User UID
     * @param int|null $roleId Role id or null to clear
     * @return bool True if updated
     */
    public function setNonSaasRole(string $uid, ?int $roleId): bool {
        $user = $this->get($uid);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }
        $state = ['updatedby' => $this->actorId];
        return (bool)$this->db->update(
            's_entity',
            ['s_nonsaas_role_id' => $roleId],
            ['uid' => $uid, 's_type' => 'U'],
            $state
        );
    }

    /**
     * Update a user's workspace role for a specific workspace.
     *
     * Behavior:
     * - Updates existing active membership when present.
     * - Revives the latest archived membership for the workspace if found.
     * - Creates a new membership if none exist.
     *
     * Example (workspace scope):
     *   $user->setWorkspaceRole('user-uid', 12, 8);
     *
     * Example (ms scope):
     *   $user->setWorkspaceRole('user-uid', 12, 22, 5);
     *
     * @param string $uid User UID
     * @param int $spaceId Workspace id
     * @param int $roleId Workspace role id (scope workspace/ms)
     * @param int|null $msId Microservicelet id for ms-scoped roles
     * @return bool True if updated
     */
    public function setWorkspaceRole(string $uid, int $spaceId, int $roleId, ?int $msId = null): bool {
        $user = $this->get($uid);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }
        $spaceRows = $this->db->select('s_space', ['id' => $spaceId, 'livestatus' => '1'], true);
        if (!$spaceRows) {
            throw new InvalidArgumentException('Workspace not found');
        }
        $roleRows = $this->db->select('s_role', ['id' => $roleId, 'livestatus' => '1'], true);
        $role = $roleRows[0] ?? null;
        if (!$role || !in_array($role['s_scope'], ['workspace', 'ms'], true)) {
            throw new InvalidArgumentException('Workspace role must be scope workspace or ms');
        }
        if ($role['s_scope'] === 'ms' && !$msId) {
            throw new InvalidArgumentException('ms_id is required for ms-scoped workspace roles');
        }
        if ($role['s_scope'] === 'workspace' && $msId) {
            throw new InvalidArgumentException('ms_id is only valid for ms-scoped workspace roles');
        }

        $updates = [
            's_role_id' => $roleId,
            's_scope_level' => $role['s_scope'],
            's_ms_id' => $role['s_scope'] === 'ms' ? $msId : null,
        ];
        $where = [
            'space_id' => $spaceId,
            's_entity_id' => (int)$user['id'],
            'livestatus' => '1',
            's_scope_level' => $role['s_scope'],
        ];
        if ($role['s_scope'] === 'ms') {
            $where['s_ms_id'] = (int)$msId;
        }
        $active = $this->db->select('s_space_membership', $where, true);
        if ($active) {
            $current = $active[0];
            $sameRole = (int)$current['s_role_id'] === $roleId;
            $sameScope = ($current['s_scope_level'] ?? '') === $role['s_scope'];
            $currentMsId = isset($current['s_ms_id']) ? (int)$current['s_ms_id'] : null;
            $sameMs = $role['s_scope'] === 'ms' ? ($currentMsId === (int)$msId) : ($currentMsId === 0 || $currentMsId === null);
            if ($sameRole && $sameScope && $sameMs) {
                return true;
            }
            $state = ['updatedby' => $this->actorId];
            $updated = $this->db->update('s_space_membership', $updates, $where, $state);
            if ($updated) {
                return true;
            }
        }

        $existing = $this->db->query(
            "SELECT id, livestatus
             FROM s_space_membership
             WHERE space_id = :space AND s_entity_id = :entity AND s_scope_level = :scope" .
             ($role['s_scope'] === 'ms' ? " AND s_ms_id = :ms" : "") . "
             ORDER BY id DESC
             LIMIT 1",
            array_filter([
                ':space' => $spaceId,
                ':entity' => (int)$user['id'],
                ':scope' => $role['s_scope'],
                ':ms' => $role['s_scope'] === 'ms' ? (int)$msId : null,
            ], static function ($value) {
                return $value !== null;
            })
        );
        if ($existing && ($existing[0]['livestatus'] ?? '') === '0') {
            $reviveUpdates = $updates;
            $reviveUpdates['livestatus'] = '1';
            $this->db->update('s_space_membership', $reviveUpdates, ['id' => (int)$existing[0]['id']], $state);
            return true;
        }

        $this->addWorkspaceMembership($uid, $spaceId, $roleId, $msId);
        return true;
    }

    /**
     * Add a workspace membership for a user.
     *
     * Example:
     *   $membershipId = $user->addWorkspaceMembership('user-uid', 12, 8);
     *
     * @param string $uid User UID
     * @param int $spaceId Workspace id
     * @param int $roleId Workspace role id (scope workspace/ms)
     * @param int|null $msId Microservicelet id for ms-scoped roles
     * @return int Inserted membership id
     */
    public function addWorkspaceMembership(string $uid, int $spaceId, int $roleId, ?int $msId = null): int {
        $user = $this->get($uid);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }
        $spaceRows = $this->db->select('s_space', ['id' => $spaceId, 'livestatus' => '1'], true);
        if (!$spaceRows) {
            throw new InvalidArgumentException('Workspace not found');
        }
        $roleRows = $this->db->select('s_role', ['id' => $roleId, 'livestatus' => '1'], true);
        $role = $roleRows[0] ?? null;
        if (!$role || !in_array($role['s_scope'], ['workspace', 'ms'], true)) {
            throw new InvalidArgumentException('Workspace role must be scope workspace or ms');
        }
        if ($role['s_scope'] === 'ms' && !$msId) {
            throw new InvalidArgumentException('ms_id is required for ms-scoped workspace roles');
        }
        if ($role['s_scope'] === 'workspace' && $msId) {
            throw new InvalidArgumentException('ms_id is only valid for ms-scoped workspace roles');
        }

        $existing = $this->db->select(
            's_space_membership',
            array_filter([
                'space_id' => $spaceId,
                's_entity_id' => (int)$user['id'],
                'livestatus' => '1',
                's_scope_level' => $role['s_scope'],
                's_ms_id' => $role['s_scope'] === 'ms' ? (int)$msId : null,
            ], static function ($value) {
                return $value !== null;
            }),
            true
        );
        if ($existing) {
            if ($role['s_scope'] === 'ms') {
                throw new InvalidArgumentException('User already has an active membership for this microservice');
            }
            throw new InvalidArgumentException('User already has an active membership for this workspace');
        }

        $payload = [
            's_entity_id' => (int)$user['id'],
            's_role_id' => $roleId,
            's_scope_level' => $role['s_scope'],
            's_ms_id' => $role['s_scope'] === 'ms' ? $msId : null,
        ];
        $state = [
            // Database::insert auto-adds default fields like space_id from state_data.
            'space_id' => $spaceId,
            'createdby' => $this->actorId,
            'livestatus' => '1',
        ];
        $id = $this->db->insert('s_space_membership', $payload, $state);
        if (!$id) {
            throw new \RuntimeException('Membership creation failed (no insert id returned).');
        }
        return (int)$id;
    }

    /**
     * Remove (archive) a workspace membership for a user.
     *
     * Example:
     *   $user->removeWorkspaceMembership('user-uid', 12);
     *
     * @param string $uid User UID
     * @param int $spaceId Workspace id
     * @return bool True if updated
     */
    public function removeWorkspaceMembership(string $uid, int $spaceId): bool {
        $user = $this->get($uid);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }
        $state = ['updatedby' => $this->actorId];
        $where = [
            'space_id' => $spaceId,
            's_entity_id' => (int)$user['id'],
            'livestatus' => '1',
        ];
        return (bool)$this->db->update('s_space_membership', ['livestatus' => '0'], $where, $state);
    }

    /**
     * Archive (deactivate) a user.
     *
     * @param string $uid User UID
     * @return bool True if updated
     */
    public function archive(string $uid): bool {
        $state = ['updatedby' => $this->actorId];
        return (bool)$this->db->update('s_entity', ['livestatus' => '0'], ['uid' => $uid, 's_type' => 'U'], $state);
    }

    /**
     * Return roles assigned to the user (non-SaaS + workspace roles).
     *
     * @param string $uid User UID
     * @return array Array of role IDs (ints)
     */
    public function roles(string $uid): array {
        $user = $this->get($uid);
        if (!$user) {
            return [];
        }
        $roles = [];
        if (!empty($user['s_nonsaas_role_id'])) {
            $roles[] = (int)$user['s_nonsaas_role_id'];
        }

        $membershipRows = $this->db->query(
            "SELECT s_role_id FROM s_space_membership WHERE livestatus != '0' AND s_entity_id = :entity AND s_role_id IS NOT NULL",
            [':entity' => (int)$user['id']]
        );
        foreach ($membershipRows as $row) {
            $roles[] = (int)$row['s_role_id'];
        }

        return array_values(array_unique(array_filter($roles, static function ($roleId) {
            return $roleId > 0;
        })));
    }

    /**
     * Get memberships for a user with roles (workspace SaaS roles).
     *
     * @param string $uid User UID
     * @return array Membership rows with role info
     */
    public function memberships(string $uid): array {
        $user = $this->get($uid);
        if (!$user) {
            return [];
        }
        $sql = "SELECT m.id,
                       m.space_id,
                       s.s_name AS space_name,
                       r.id AS role_id,
                       r.s_role_name,
                       r.s_scope,
                       m.s_scope_level,
                       m.s_ms_id
                FROM s_space_membership m
                LEFT JOIN s_role r ON r.id = m.s_role_id
                LEFT JOIN s_space s ON s.id = m.space_id
                WHERE m.s_entity_id = :uid
                  AND m.livestatus = '1'
                ORDER BY s.s_name, r.s_role_name";
        return $this->db->query($sql, [':uid' => (int)$user['id']]);
    }

    /**
     * Get workspaces for a user (unique workspaces only).
     *
     * Usage examples:
     *   $user = new \Core\App\User($runData);
     *   $workspaces = $user->workspaces('user-uid');
     *
     *   // Include workspace roles for each membership
     *   $workspaces = $user->workspaces('user-uid', ['include_roles' => true]);
     *
     *   // Include membership count per workspace
     *   $workspaces = $user->workspaces('user-uid', ['include_counts' => true]);
     *
     * Options:
     *   include_roles (bool) - include role info (role_id, role_name, scope).
     *   include_counts (bool) - include membership_count per workspace.
     *
     * @param string $uid User UID
     * @param array $opts Optional flags
     * @return array Workspace list
     */
    public function workspaces(string $uid, array $opts = []): array {
        $user = $this->get($uid);
        if (!$user) {
            return [];
        }
        $includeRoles = !empty($opts['include_roles']);
        $includeCounts = !empty($opts['include_counts']);

        $select = [
            's.id AS space_id',
            's.uid AS space_uid',
            's.s_name AS space_name',
            's.s_slug AS space_slug',
        ];
        if ($includeRoles) {
            $select[] = 'r.id AS role_id';
            $select[] = 'r.s_role_name AS role_name';
            $select[] = 'r.s_scope AS role_scope';
        }
        if ($includeCounts) {
            $select[] = 'COUNT(m.id) AS membership_count';
        }

        $sql = "SELECT " . implode(', ', $select) . "
                FROM s_space_membership m
                INNER JOIN s_space s ON s.id = m.space_id
                LEFT JOIN s_role r ON r.id = m.s_role_id
                WHERE m.s_entity_id = :uid
                  AND m.livestatus = '1'
                  AND s.livestatus = '1'
                GROUP BY s.id" . ($includeRoles ? ', r.id' : '') . "
                ORDER BY s.s_name";

        $rows = $this->db->query($sql, [':uid' => (int)$user['id']]);
        $workspaces = [];
        foreach ($rows as $row) {
            $spaceId = (int)($row['space_id'] ?? 0);
            if (!isset($workspaces[$spaceId])) {
                $workspaces[$spaceId] = [
                    'space_id' => $row['space_id'],
                    'space_uid' => $row['space_uid'],
                    'space_name' => $row['space_name'],
                    'space_slug' => $row['space_slug'],
                    'roles' => [],
                ];
                if ($includeCounts) {
                    $workspaces[$spaceId]['membership_count'] = (int)($row['membership_count'] ?? 0);
                }
            }
            if ($includeRoles && !empty($row['role_id'])) {
                $workspaces[$spaceId]['roles'][] = [
                    'role_id' => (int)$row['role_id'],
                    'role_name' => $row['role_name'],
                    'role_scope' => $row['role_scope'],
                ];
            }
        }
        $list = array_values($workspaces);
        if (!$includeRoles) {
            foreach ($list as &$entry) {
                unset($entry['roles']);
            }
        }
        return $list;
    }

    /**
     * Get workspaces where the user is the owner (s_space.s_owner_entity_id).
     *
     * Usage examples:
     *   $user = new \Core\App\User($runData);
     *   $owned = $user->ownedWorkspaces('user-uid');
     *
     *   // Include membership count per workspace
     *   $owned = $user->ownedWorkspaces('user-uid', ['include_counts' => true]);
     *
     * @param string $uid User UID
     * @param array $opts Optional flags (include_counts)
     * @return array Workspace list
     */
    public function ownedWorkspaces(string $uid, array $opts = []): array {
        $user = $this->get($uid);
        if (!$user) {
            return [];
        }
        $includeCounts = !empty($opts['include_counts']);

        $select = [
            's.id AS space_id',
            's.uid AS space_uid',
            's.s_name AS space_name',
            's.s_slug AS space_slug',
        ];
        if ($includeCounts) {
            $select[] = 'COUNT(m.id) AS membership_count';
        }

        $sql = "SELECT " . implode(', ', $select) . "
                FROM s_space s
                LEFT JOIN s_space_membership m
                  ON m.space_id = s.id
                 AND m.livestatus = '1'
                WHERE s.s_owner_entity_id = :uid
                  AND s.livestatus = '1'
                GROUP BY s.id
                ORDER BY s.s_name";

        return $this->db->query($sql, [':uid' => (int)$user['id']]);
    }

}
