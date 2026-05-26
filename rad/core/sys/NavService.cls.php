<?php
namespace Core\Sys;

use RuntimeException;

class NavService {
    private Database $db;
    private ErrorHandler $errorHandler;

    private array $allowedDevices = ['all', 'desktop', 'mobile'];

    public function __construct(Database $db, ErrorHandler $errorHandler) {
        $this->db = $db;
        $this->errorHandler = $errorHandler;
    }

    public function listNavSets(array $filters = []): array {
        $where = [];
        if (!empty($filters['livestatus'])) {
            $where['livestatus'] = (string)$filters['livestatus'];
        }
        $navsets = $this->db->select('s_navset', $where, true, ['s_sort_order' => 'ASC', 's_name' => 'ASC']);

        $roleMap = $this->navsetRoleMap(array_column($navsets, 'id'));

        // Optional role-based filtering driven by s_navset_role
        if (!empty($filters['roles']) && is_array($filters['roles'])) {
            $roleIds = array_values(array_filter(array_map('intval', $filters['roles']), static fn($r) => $r > 0));
            if (!empty($roleIds) && !empty($navsets)) {
                $navsets = array_values(array_filter($navsets, static function ($set) use ($roleMap, $roleIds) {
                    $setId = (int)($set['id'] ?? 0);
                    $allowedRoles = $roleMap[$setId]['roles'] ?? [];
                    // If no roles are attached, treat as public; otherwise require intersection
                    return empty($allowedRoles) || (bool)array_intersect($allowedRoles, $roleIds);
                }));
            }
        }

        // Enrich with attached roles and sane defaults for UI/runtime
        foreach ($navsets as &$set) {
            $setId = (int)($set['id'] ?? 0);
            $setRoles = $roleMap[$setId]['roles'] ?? [];
            $set['access_roles'] = $setRoles;
            $set['s_ms_id'] = $roleMap[$setId]['s_ms_id'] ?? 0;
            $set['s_location'] = $set['s_location'] ?? '';
            $set['s_scope'] = $set['s_scope'] ?? '';
        }
        unset($set);

        return $navsets;
    }

    public function getNavSet(int $navsetId): array {
        $rows = $this->db->select('s_navset', ['id' => $navsetId], true);
        if (count($rows) !== 1) {
            throw new RuntimeException('Nav set not found.');
        }
        return $rows[0];
    }

    public function saveNavSet(array $payload, int $entityId = 0): array {
        $navsetId = (int)($payload['id'] ?? $payload['navset_id'] ?? 0);
        $name = trim($payload['s_name'] ?? $payload['name'] ?? '');
        if ($name === '') {
            throw new RuntimeException('Nav set name is required.');
        }
        $description = trim($payload['s_description'] ?? $payload['description'] ?? '');
        $msId = (int)($payload['s_ms_id'] ?? $payload['ms_id'] ?? 0);

        $roles = $this->normalizeRoles($payload['roles'] ?? []);

        $data = [
            's_name' => $name,
            's_description' => $description,
        ];

        if ($navsetId > 0) {
            $data['updatedby'] = $entityId ?: null;
            $this->db->update('s_navset', $data, ['id' => $navsetId], ['updatedby' => $entityId ?: 1]);
        } else {
            $data['s_sort_order'] = $this->nextNavSetSortOrder();
            $state = [
                'createdby' => $entityId ?: 1,
                'livestatus' => '1',
                'space_id' => 0,
            ];
            $navsetId = $this->db->insert('s_navset', $data, $state);
        }

        $this->syncNavsetRoles($navsetId, $roles, $entityId, $msId);

        $navset = $this->getNavSet($navsetId);
        $navset['access_roles'] = $roles;
        $navset['s_ms_id'] = $msId > 0 ? $msId : 0;
        return $navset;
    }

    public function archiveNavSet(int $navsetId, int $entityId = 0, string $status = '2'): void {
        if ($navsetId <= 0) {
            throw new RuntimeException('Invalid nav set reference.');
        }
        $this->db->update('s_navset', [
            'livestatus' => $status,
            'updatedby' => $entityId ?: null,
        ], ['id' => $navsetId]);
    }

    public function listNavItems(int $navsetId): array {
        if ($navsetId <= 0) {
            return [];
        }
        $items = $this->db->select('s_nav', ['s_navset_id' => $navsetId], true, ['s_sort_order' => 'ASC', 's_name' => 'ASC']);
        foreach ($items as &$item) {
            $item['s_meta_array'] = $item['s_meta'] ? json_decode($item['s_meta'], true) ?: [] : [];
            // Normalize parent id regardless of legacy column name
            if (!isset($item['s_parent_nav_id']) && isset($item['s_parent_id'])) {
                $item['s_parent_nav_id'] = $item['s_parent_id'];
            }
            if (!isset($item['s_parent_nav_id'])) {
                $item['s_parent_nav_id'] = 0;
            }
        }
        unset($item);
        return $items;
    }

    public function getNavItem(int $itemId): array {
        $rows = $this->db->select('s_nav', ['id' => $itemId], true);
        if (count($rows) !== 1) {
            throw new RuntimeException('Nav item not found.');
        }
        $row = $rows[0];
        $row['s_meta_array'] = $row['s_meta'] ? json_decode($row['s_meta'], true) ?: [] : [];
        return $row;
    }

    public function saveNavItem(array $payload, int $entityId = 0): array {
        $itemId = (int)($payload['id'] ?? $payload['item_id'] ?? 0);
        $name = trim($payload['s_name'] ?? $payload['name'] ?? '');
        if ($name === '') {
            throw new RuntimeException('Nav item name is required.');
        }

        $navsetId = (int)($payload['s_navset_id'] ?? $payload['navset_id'] ?? 0);
        if ($navsetId <= 0) {
            throw new RuntimeException('Navset reference is required.');
        }
        $href = trim($payload['s_href'] ?? $payload['href'] ?? '#');
        $icon = trim($payload['s_icon'] ?? $payload['icon'] ?? '');
        $target = trim($payload['s_target'] ?? $payload['target'] ?? '_self');
        $badge = trim($payload['s_badge'] ?? $payload['badge'] ?? '');
        $device = $this->normalizeDevice($payload['s_device'] ?? $payload['device'] ?? 'all');
        $meta = $this->normalizeMeta($payload['s_meta'] ?? $payload['meta'] ?? null);
        $condition = trim($payload['s_condition'] ?? $payload['condition'] ?? '');

        $data = [
            's_navset_id' => $navsetId,
            's_name' => $name,
            's_href' => $href,
            's_icon' => $icon,
            's_target' => $target,
            's_badge' => $badge,
            's_condition' => $condition,
            's_device' => $device,
            's_meta' => $meta,
        ];

        if ($itemId > 0) {
            $data['updatedby'] = $entityId ?: null;
            $this->db->update('s_nav', $data, ['id' => $itemId], ['updatedby' => $entityId ?: 1]);
        } else {
            $data['s_sort_order'] = $this->nextNavItemSortOrder($navsetId);
            $state = [
                'createdby' => $entityId ?: 1,
                'livestatus' => '1',
                'space_id' => 0,
            ];
            $itemId = $this->db->insert('s_nav', $data, $state);
        }

        return $this->getNavItem($itemId);
    }

    public function archiveNavItem(int $itemId, int $entityId = 0, string $status = '2'): void {
        if ($itemId <= 0) {
            throw new RuntimeException('Invalid nav item reference.');
        }
        $this->db->update('s_nav', [
            'livestatus' => $status,
            'updatedby' => $entityId ?: null,
        ], ['id' => $itemId], ['updatedby' => $entityId ?: 1]);
    }

    private function normalizeDevice(string $value): string {
        $val = strtolower($value);
        return in_array($val, $this->allowedDevices, true) ? $val : 'all';
    }

    private function normalizeRoles($roles): array {
        if ($roles === null || $roles === '') {
            return [];
        }
        if (is_string($roles)) {
            $roles = explode(',', $roles);
        }
        $roles = array_filter(array_map('intval', (array)$roles), static function ($r) {
            return $r > 0;
        });
        return array_values(array_unique($roles));
    }

    private function normalizeMeta($meta): ?string {
        if (empty($meta)) {
            return null;
        }
        if (is_array($meta)) {
            return json_encode($meta);
        }
        return (string)$meta;
    }

    private function nextNavSetSortOrder(): int {
        $rows = $this->db->query("SELECT COALESCE(MAX(s_sort_order), 0) AS max_order FROM s_navset");
        return ((int)($rows[0]['max_order'] ?? 0)) + 1;
    }

    public function sortNavSets(array $orderedIds): void {
        $weight = 10;
        foreach ($orderedIds as $id) {
            $idInt = (int)$id;
            if ($idInt <= 0) {
                continue;
            }
            $this->db->update('s_navset', ['s_sort_order' => $weight], ['id' => $idInt]);
            $weight += 10;
        }
    }

    private function nextNavItemSortOrder(int $navsetId): int {
        $rows = $this->db->query("SELECT COALESCE(MAX(s_sort_order), 0) AS max_order FROM s_nav WHERE s_navset_id = :id", [':id' => $navsetId]);
        return ((int)($rows[0]['max_order'] ?? 0)) + 1;
    }

    private function navsetRoleMap(array $navsetIds): array {
        $navsetIds = array_values(array_filter(array_map('intval', $navsetIds), static fn($id) => $id > 0));
        if (empty($navsetIds)) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach ($navsetIds as $idx => $id) {
            $key = ':n' . $idx;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        if (empty($placeholders)) {
            return [];
        }
        $rows = $this->db->query(
            "SELECT s_navset_id, s_role_id, s_ms_id FROM s_navset_role WHERE livestatus != '0' AND s_navset_id IN (" . implode(',', $placeholders) . ")",
            $params
        );
        $map = [];
        foreach ($rows as $row) {
            $nsId = (int)($row['s_navset_id'] ?? 0);
            $rId = (int)($row['s_role_id'] ?? 0);
            $msId = (int)($row['s_ms_id'] ?? 0);
            if ($nsId > 0 && $rId > 0) {
                $map[$nsId]['roles'][] = $rId;
            }
            if ($nsId > 0 && $msId > 0 && !isset($map[$nsId]['s_ms_id'])) {
                $map[$nsId]['s_ms_id'] = $msId;
            }
        }
        foreach ($map as $id => $entry) {
            $map[$id]['roles'] = array_values(array_unique($entry['roles'] ?? []));
            $map[$id]['s_ms_id'] = (int)($entry['s_ms_id'] ?? 0);
        }
        return $map;
    }

    private function syncNavsetRoles(int $navsetId, array $roles, int $entityId = 0, int $msId = 0): void {
        if ($navsetId <= 0) {
            return;
        }
        $this->db->delete('s_navset_role', ['s_navset_id' => $navsetId]);
        if (empty($roles)) {
            return;
        }
        $state = [
            'createdby' => $entityId ?: 1,
            'space_id' => 0,
            'livestatus' => '1',
            'wf_status' => 0,
        ];
        foreach ($roles as $rid) {
            $this->db->insert(
                's_navset_role',
                [
                    's_navset_id' => $navsetId,
                    's_role_id' => $rid,
                    's_ms_id' => $msId,
                ],
                $state
            );
        }
    }
}
