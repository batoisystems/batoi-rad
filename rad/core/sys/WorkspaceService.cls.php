<?php
namespace Core\Sys;

class WorkspaceService {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Returns workspace summaries enriched with membership and binding stats.
     */
    public function listSummaries(bool $includeInactive = false): array {
        $where = $includeInactive ? "1=1" : "livestatus != '0'";
        $rows = $this->db->query(
            "SELECT id, uid, s_name, s_slug, s_description, s_owner_entity_id, livestatus, createstamp, updatestamp
             FROM s_space
             WHERE {$where}
             ORDER BY updatestamp DESC, createstamp DESC"
        );

        if (empty($rows)) {
            return [];
        }

        $spaceIds = array_map(static function ($row) {
            return (int)$row['id'];
        }, $rows);

        $membershipStats = $this->fetchMembershipStats($spaceIds);
        $bindingStats = $this->fetchBindingStats($spaceIds);

        foreach ($rows as &$row) {
            $spaceId = (int)$row['id'];
            $members = $membershipStats[$spaceId] ?? ['count' => 0, 'last_activity' => null];
            $bindings = $bindingStats[$spaceId] ?? ['count' => 0];
            $row['member_count'] = (int)$members['count'];
            $row['last_member_activity'] = $members['last_activity'];
            $row['binding_count'] = (int)$bindings['count'];
            $row['status_label'] = $this->resolveStatusLabel((int)$row['livestatus']);
        }
        unset($row);

        return $rows;
    }

    private function fetchMembershipStats(array $spaceIds): array {
        if (empty($spaceIds)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($spaceIds as $index => $spaceId) {
            $placeholder = ':space' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $spaceId;
        }

        $sql = sprintf(
            "SELECT space_id, COUNT(*) AS member_total, MAX(updatestamp) AS last_activity
             FROM s_space_membership
             WHERE space_id IN (%s) AND livestatus != '0'
             GROUP BY space_id",
            implode(',', $placeholders)
        );
        $rows = $this->db->query($sql, $params);

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int)$row['space_id']] = [
                'count' => (int)$row['member_total'],
                'last_activity' => $row['last_activity'] ?? null,
            ];
        }
        return $stats;
    }

    private function fetchBindingStats(array $spaceIds): array {
        if (empty($spaceIds)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($spaceIds as $index => $spaceId) {
            $placeholder = ':bind' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $spaceId;
        }

        $sql = sprintf(
            "SELECT space_id, COUNT(*) AS binding_total
             FROM s_permission_binding
             WHERE space_id IN (%s) AND livestatus != '0'
             GROUP BY space_id",
            implode(',', $placeholders)
        );
        $rows = $this->db->query($sql, $params);

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int)$row['space_id']] = [
                'count' => (int)$row['binding_total'],
            ];
        }
        return $stats;
    }

    private function resolveStatusLabel(int $livestatus): string {
        switch ($livestatus) {
            case 1:
                return 'Active';
            case 2:
                return 'Archived';
            case 3:
                return 'Suspended';
            default:
                return 'Unknown';
        }
    }
}
