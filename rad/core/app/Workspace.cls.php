<?php
namespace Core\App;

use InvalidArgumentException;

/**
 * Workspace service for application developers.
 * Provides read helpers and member listings for SaaS workspaces.
 */
class Workspace {
    private $db;

    public function __construct($db) {
        // Accept either a DB handle or runData; normalize to DB handle.
        if (is_array($db) && isset($db['db'])) {
            $db = $db['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            throw new InvalidArgumentException('Workspace requires a database handle.');
        }
        $this->db = $db;
    }

    /**
     * Get a workspace by id or uid.
     *
     * @param int|string $idOrUid Identifier
     * @return array|null Workspace row or null
     */
    public function get($idOrUid): ?array {
        $field = is_numeric($idOrUid) ? 'id' : 'uid';
        $rows = $this->db->select('s_space', [$field => $idOrUid], true);
        return $rows[0] ?? null;
    }

    /**
     * List workspaces, optionally filtered by livestatus.
     *
     * @param array $opts Optional: livestatus
     * @return array Workspace rows
     */
    public function list(array $opts = []): array {
        $where = [];
        if (isset($opts['livestatus']) && $opts['livestatus'] !== '') {
            $where['livestatus'] = (string)$opts['livestatus'];
        }
        return $this->db->select('s_space', $where, true, ['s_name' => 'ASC']);
    }

    /**
     * Get members of a workspace with their SaaS roles.
     *
     * @param int|string $workspaceId Id or uid
     * @return array Membership rows with role info
     */
    public function members($workspaceId): array {
        $wid = $this->resolveWorkspaceId($workspaceId);
        if ($wid === null) {
            return [];
        }
        $sql = "SELECT m.id AS membership_id,
                       e.id AS user_id,
                       e.uid AS user_uid,
                       e.s_name AS user_name,
                       e.s_identity AS username,
                       r.id AS role_id,
                       r.s_role_name,
                       r.s_scope,
                       m.s_scope_level,
                       m.s_ms_id,
                       m.s_effective_from,
                       m.s_effective_to
                FROM s_space_membership m
                LEFT JOIN s_role r ON r.id = m.s_role_id
                                   AND r.livestatus = '1'
                INNER JOIN s_entity e ON e.id = m.s_entity_id
                                      AND e.s_type = 'U'
                                      AND e.livestatus = '1'
                WHERE m.space_id = :wid
                  AND m.livestatus = '1'
                ORDER BY e.s_name, r.s_role_name";
        return $this->db->query($sql, [':wid' => $wid]);
    }

    /**
     * Basic stats: member count and binding counts.
     *
     * @param int|string $workspaceId Id or uid
     * @return array ['members'=>int,'bindings_ms'=>int,'bindings_route'=>int]
     */
    public function stats($workspaceId): array {
        $wid = $this->resolveWorkspaceId($workspaceId);
        if ($wid === null) {
            return ['members' => 0, 'bindings_ms' => 0, 'bindings_route' => 0];
        }
        $members = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM s_space_membership
             WHERE space_id = :wid AND livestatus = '1'",
            [':wid' => $wid]
        );
        $msBindings = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM s_permission_binding b
             INNER JOIN s_ms m ON m.id = b.s_object_id
             WHERE b.s_object_type = 'ms' AND b.livestatus = '1' AND m.livestatus = '1' AND m.space_id = :wid",
            [':wid' => $wid]
        );
        $routeBindings = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM s_permission_binding b
             INNER JOIN s_msroute r ON r.id = b.s_object_id
             INNER JOIN s_ms m ON m.id = r.s_ms_id
             WHERE b.s_object_type = 'route'
               AND b.livestatus = '1'
               AND r.livestatus = '1'
               AND m.livestatus = '1'
               AND m.space_id = :wid",
            [':wid' => $wid]
        );
        return [
            'members' => (int)($members[0]['c'] ?? 0),
            'bindings_ms' => (int)($msBindings[0]['c'] ?? 0),
            'bindings_route' => (int)($routeBindings[0]['c'] ?? 0),
        ];
    }

    /**
     * Resolve workspace id from id or uid.
     */
    private function resolveWorkspaceId($idOrUid): ?int {
        if (is_numeric($idOrUid)) {
            $id = (int)$idOrUid;
            return $id > 0 ? $id : null;
        }
        $row = $this->get($idOrUid);
        return isset($row['id']) ? (int)$row['id'] : null;
    }
}
