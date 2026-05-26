<?php
namespace Core\App;

use InvalidArgumentException;

/**
 * Team service for application developers (non–RAD Admin).
 *
 * Provides CRUD helpers for s_team and s_team_member along with member lookups.
 * All methods return data/booleans/ids or throw exceptions; no privilege gating.
 */
class Team {
    private $db;
    private int $actorId;

    public function __construct(array $runData) {
        if (empty($runData['db'])) {
            throw new InvalidArgumentException('Database handle is required for Team service.');
        }
        $this->db = $runData['db'];
        $this->actorId = (int)($runData['entity']['id'] ?? 1);
    }

    /**
     * List teams with optional filters.
     *
     * @param array $filters Optional: livestatus ('0','1','2'), name (partial match)
     * @return array List of team rows (with member_count/manager_count)
     */
    public function list(array $filters = []): array {
        $where = "WHERE t.livestatus != '0'";
        $params = [];
        if (isset($filters['livestatus']) && $filters['livestatus'] !== '') {
            $where = "WHERE t.livestatus = :ls";
            $params[':ls'] = (string)$filters['livestatus'];
        }
        if (!empty($filters['name'])) {
            $where .= (strpos($where, 'WHERE') === false ? ' WHERE' : ' AND') . " t.s_name LIKE :name";
            $params[':name'] = '%' . $filters['name'] . '%';
        }
        $sql = "SELECT
                t.id,
                t.uid,
                t.s_name,
                t.s_color,
                t.s_icon,
                t.s_description,
                t.livestatus,
                COUNT(tm.id) AS member_count,
                COALESCE(SUM(tm.s_is_manager), 0) AS manager_count
            FROM s_team t
            LEFT JOIN s_team_member tm ON tm.s_team_id = t.id
            $where
            GROUP BY t.id, t.uid, t.s_name, t.s_color, t.s_icon, t.s_description, t.livestatus
            ORDER BY t.s_name ASC";
        return $this->db->query($sql, $params);
    }

    /**
     * Fetch a team by id or uid.
     *
     * @param int|string $idOrUid Numeric id or UID
     * @param bool $withMembers When true, attach member list
     * @return array|null Team row (with members when requested) or null if missing
     */
    public function get($idOrUid, bool $withMembers = true): ?array {
        $field = is_numeric($idOrUid) ? 'id' : 'uid';
        $rows = $this->db->select('s_team', [$field => $idOrUid], true);
        $team = $rows[0] ?? null;
        if (!$team) {
            return null;
        }
        if ($withMembers) {
            $team['members'] = $this->listMembers((int)$team['id']);
        }
        return $team;
    }

    /**
     * Create a team.
     *
     * @param array $data Required: s_name. Optional: s_color, s_icon, s_description, livestatus.
     * @return int Inserted team id
     *
     * @throws InvalidArgumentException on missing name or duplicate name.
     * @throws \RuntimeException when insert id is not returned.
     */
    public function create(array $data): int {
        $name = trim($data['s_name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('s_name is required');
        }
        $existing = $this->db->select('s_team', ['s_name' => $name], true);
        if (!empty($existing)) {
            throw new InvalidArgumentException('Team name already exists');
        }
        $payload = [
            's_name' => $name,
            's_color' => trim($data['s_color'] ?? ''),
            's_icon' => trim($data['s_icon'] ?? ''),
            's_description' => $data['s_description'] ?? '',
            'livestatus' => $data['livestatus'] ?? '1',
        ];
        $state = [
            'createdby' => $this->actorId,
            'space_id' => 0,
            'livestatus' => '1',
            'wf_status' => 0,
        ];
        $id = $this->db->insert('s_team', $payload, $state);
        if (!$id) {
            throw new \RuntimeException('Team creation failed (no insert id returned).');
        }
        return (int)$id;
    }

    /**
     * Update a team.
     *
     * @param int $id Team id
     * @param array $data Fields to update: s_name, s_color, s_icon, s_description, livestatus
     * @return bool True if updated
     *
     * @throws InvalidArgumentException when team not found or name duplicate.
     */
    public function update(int $id, array $data): bool {
        $team = $this->get($id, false);
        if (!$team) {
            throw new InvalidArgumentException('Team not found');
        }
        $updates = [];
        if (isset($data['s_name'])) {
            $name = trim((string)$data['s_name']);
            if ($name === '') {
                throw new InvalidArgumentException('s_name cannot be empty');
            }
            $dupe = $this->db->select('s_team', ['s_name' => $name], true);
            if (!empty($dupe) && (int)$dupe[0]['id'] !== (int)$team['id']) {
                throw new InvalidArgumentException('Team name already exists');
            }
            $updates['s_name'] = $name;
        }
        foreach (['s_color','s_icon','s_description','livestatus'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }
        if (empty($updates)) {
            return true;
        }
        $state = ['updatedby' => $this->actorId];
        return (bool)$this->db->update('s_team', $updates, ['id' => (int)$team['id']], $state);
    }

    /**
     * Archive (deactivate) a team by setting livestatus to 0.
     *
     * @param int $id Team id
     * @return bool True if updated
     */
    public function archive(int $id): bool {
        $state = ['updatedby' => $this->actorId];
        return (bool)$this->db->update('s_team', ['livestatus' => '0'], ['id' => $id], $state);
    }

    /**
     * List members of a team.
     *
     * @param int $teamId Team id
     * @return array Member rows with entity name/identity and manager flag
     */
    public function listMembers(int $teamId): array {
        if ($teamId <= 0) {
            return [];
        }
        $sql = "SELECT tm.id, tm.s_team_id, tm.s_entity_id, tm.s_is_manager,
                       e.s_name, e.s_identity
                FROM s_team_member tm
                LEFT JOIN s_entity e ON e.id = tm.s_entity_id
                WHERE tm.s_team_id = :team
                ORDER BY e.s_name ASC";
        return $this->db->query($sql, [':team' => $teamId]);
    }

    /**
     * Add a member to a team.
     *
     * @param int $teamId Team id
     * @param int $entityId User entity id
     * @param bool $isManager Whether the member is a manager
     * @return int Inserted membership id
     *
     * @throws InvalidArgumentException for missing references or duplicates.
     * @throws \RuntimeException when insert id is not returned.
     */
    public function addMember(int $teamId, int $entityId, bool $isManager = false): int {
        if ($teamId <= 0 || $entityId <= 0) {
            throw new InvalidArgumentException('teamId and entityId are required');
        }
        $team = $this->get($teamId, false);
        if (!$team) {
            throw new InvalidArgumentException('Team not found');
        }
        $existing = $this->db->select('s_team_member', ['s_team_id' => $teamId, 's_entity_id' => $entityId], true);
        if (!empty($existing)) {
            throw new InvalidArgumentException('Member already in team');
        }
        $state = [
            'createdby' => $this->actorId,
            'space_id' => 0,
            'livestatus' => '1',
            'wf_status' => 0,
        ];
        $id = $this->db->insert('s_team_member', [
            's_team_id' => $teamId,
            's_entity_id' => $entityId,
            's_is_manager' => $isManager ? 1 : 0,
        ], $state);
        if (!$id) {
            throw new \RuntimeException('Team member insert failed (no insert id returned).');
        }
        return (int)$id;
    }

    /**
     * Remove a member by membership id.
     *
     * @param int $membershipId Membership id
     * @return bool True if deleted
     */
    public function removeMemberById(int $membershipId): bool {
        if ($membershipId <= 0) {
            return false;
        }
        return (bool)$this->db->delete('s_team_member', ['id' => $membershipId]);
    }

    /**
     * Remove a member by team and entity ids.
     *
     * @param int $teamId Team id
     * @param int $entityId User entity id
     * @return bool True if deleted
     */
    public function removeMember(int $teamId, int $entityId): bool {
        if ($teamId <= 0 || $entityId <= 0) {
            return false;
        }
        return (bool)$this->db->delete('s_team_member', [
            's_team_id' => $teamId,
            's_entity_id' => $entityId,
        ]);
    }

    /**
     * Set or clear manager flag on a membership.
     *
     * @param int $membershipId Membership id
     * @param bool $isManager Manager flag
     * @return bool True if updated
     */
    public function setManager(int $membershipId, bool $isManager): bool {
        if ($membershipId <= 0) {
            return false;
        }
        $state = ['updatedby' => $this->actorId];
        return (bool)$this->db->update('s_team_member', ['s_is_manager' => $isManager ? 1 : 0], ['id' => $membershipId], $state);
    }

    /**
     * Search entities for member suggestions by name or identity.
     *
     * @param string $query Search term (min 2 chars recommended)
     * @param int $limit Max rows to return
     * @return array Matching entities (id, s_name, s_identity)
     */
    public function searchEntities(string $query, int $limit = 20): array {
        $term = trim($query);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $limit = $limit > 0 ? $limit : 20;
        $sql = "SELECT id, s_name, s_identity 
                FROM s_entity 
                WHERE s_type = 'U' AND livestatus != '0'
                  AND (s_name LIKE :q OR s_identity LIKE :q)
                ORDER BY s_name ASC 
                LIMIT $limit";
        return $this->db->query($sql, [':q' => '%' . $term . '%']);
    }
}
