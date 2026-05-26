<?php
namespace Core\App;

/**
 * Role service for application developers.
 * Provides lookup and creation helpers for platform and workspace scopes.
 */
class Role {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get a role by id or uid.
     *
     * @param int|string $idOrUid Identifier
     * @return array|null Role row or null
     */
    public function get($idOrUid) {
        $field = is_numeric($idOrUid) ? 'id' : 'uid';
        $rows = $this->db->select('s_role', [$field => $idOrUid], true);
        return $rows[0] ?? null;
    }

    /**
     * List roles filtered by scope.
     *
     * @param array $scopes e.g. ['platform'], ['workspace'], or ['ms']
     * @return array Role rows
     */
    public function list(array $scopes = []): array {
        $where = [];
        if ($scopes) {
            // simple IN filter
            $placeholders = [];
            $params = [];
            foreach (array_values($scopes) as $i => $scope) {
                $key = ':scope' . $i;
                $placeholders[] = $key;
                $params[$key] = $scope;
            }
            $rows = $this->db->query(
                "SELECT * FROM s_role WHERE s_scope IN (" . implode(',', $placeholders) . ") AND livestatus='1' ORDER BY s_role_name",
                $params
            );
            return $rows;
        }
        return $this->db->select('s_role', ['livestatus' => '1'], true, ['s_role_name' => 'ASC']);
    }

    /**
     * Create a platform, workspace, or microservice role.
     *
     * @param array $data keys: s_role_name (required), s_scope (platform/workspace/ms), s_default_route_id (optional)
     * @return int Inserted id
     *
     * @throws \InvalidArgumentException on validation errors
     */
    public function create(array $data) {
        $name = trim($data['s_role_name'] ?? '');
        $scope = $data['s_scope'] ?? 'platform';
        if ($name === '') {
            throw new \InvalidArgumentException('Role name is required');
        }
        if (!in_array($scope, ['platform','workspace','ms'], true)) {
            throw new \InvalidArgumentException('Invalid scope');
        }
        $payload = [
            's_role_name' => $name,
            's_scope' => $scope,
            's_default_route_id' => $data['s_default_route_id'] ?? null,
        ];
        return $this->db->insert('s_role', $payload);
    }
}
