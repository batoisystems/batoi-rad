<?php
namespace Core\App;

use InvalidArgumentException;

/**
 * Membership service for workspace SaaS role assignments.
 * Provides read helpers and a safe assign/remove API (one SaaS role per workspace).
 */
class Membership {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * List memberships (optionally filter by space_id or user_id).
     *
     * @param array $opts Optional keys: space_id, user_id
     * @return array Membership rows
     */
    public function list(array $opts = []): array {
        $sql = "SELECT m.*, s.s_name AS space_name
                FROM s_space_membership m
                LEFT JOIN s_space s ON s.id = m.space_id
                WHERE m.livestatus = '1'";
        $params = [];
        if (!empty($opts['space_id'])) {
            $sql .= " AND m.space_id = :sid";
            $params[':sid'] = (int)$opts['space_id'];
        }
        if (!empty($opts['user_id'])) {
            $sql .= " AND m.s_entity_id = :uid";
            $params[':uid'] = (int)$opts['user_id'];
        }
        $sql .= " ORDER BY m.createstamp DESC";
        return $this->db->query($sql, $params);
    }

    /**
     * Assign a SaaS role to a membership (one SaaS role per workspace).
     *
     * @param int $membershipId Membership id
     * @param int $roleId SaaS role id
     * @param string $scopeLevel workspace|ms
     * @param int|null $msId Required when scopeLevel=ms
     * @return bool True when assigned
     *
     * @throws InvalidArgumentException on validation errors or duplicates
     */
    public function assignRole(int $membershipId, int $roleId, string $scopeLevel = 'workspace', ?int $msId = null): bool {
        if ($membershipId <= 0 || $roleId <= 0) {
            throw new InvalidArgumentException('membershipId and roleId are required');
        }
        $roleRow = $this->db->select('s_role', ['id' => $roleId], true);
        if (!$roleRow) {
            throw new InvalidArgumentException('Role not found');
        }
        $scope = $roleRow[0]['s_scope'] ?? 'platform';
        $isSaasRole = in_array($scope, ['workspace','ms'], true);
        if (!$isSaasRole) {
            throw new InvalidArgumentException('Role must be SaaS-scoped');
        }
        if ($scopeLevel === 'ms' && ($msId === null || $msId <= 0)) {
            throw new InvalidArgumentException('Microservice-scoped role requires microservice id');
        }
        $membershipRows = $this->db->select('s_space_membership', ['id' => $membershipId, 'livestatus' => '1'], true);
        if (!$membershipRows) {
            throw new InvalidArgumentException('Membership not found');
        }
        $this->db->update('s_space_membership', [
            's_role_id' => $roleId,
            's_scope_level' => $scopeLevel,
            's_ms_id' => $scopeLevel === 'ms' ? $msId : null,
        ], ['id' => $membershipId]);
        return true;
    }

    /**
     * Clear a role from a membership.
     */
    public function removeRole(int $membershipId): bool {
        if ($membershipId <= 0) {
            throw new InvalidArgumentException('membershipId required');
        }
        return (bool)$this->db->update('s_space_membership', [
            's_role_id' => null,
            's_scope_level' => 'workspace',
            's_ms_id' => null,
            's_effective_from' => null,
            's_effective_to' => null,
        ], ['id' => $membershipId]);
    }
}
