<?php
namespace Core\Sys;

/**
 * Central service for logging and retrieving in-product notifications/activity.
 */
class NotificationService {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * RAD Admin visibility guard: only entity_id = 1 with role_id 1 can see admin notifications.
     */
    public function canSeeRadAdmin(array $entity): bool {
        $id = (int)($entity['id'] ?? $entity['entity_id'] ?? 0);
        if ($id !== 1) {
            return false;
        }
        $roles = $entity['role_id'] ?? [];
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        return in_array(1, array_map('intval', $roles), true);
    }

    /**
     * Generic logger that persists a notification row.
     */
    public function logEvent(string $message, array $context = []): ?int {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        $spaceId = isset($context['space_id']) ? (int)$context['space_id'] : 0;
        $userId = isset($context['user_id']) && (int)$context['user_id'] > 0 ? (int)$context['user_id'] : null;
        $createdBy = (int)($context['created_by'] ?? 0);
        $audience = $context['audience'] ?? $this->inferAudience($spaceId, $userId);
        $metadata = $context['metadata'] ?? [];
        $metadata['audience'] = $audience;
        if (!isset($metadata['event_type'])) {
            $metadata['event_type'] = $context['event_type'] ?? 'generic';
        }

        $payload = [
            'link' => $context['link'] ?? null,
            'metadata' => $metadata,
        ];

        try {
            $state = [
                'space_id' => $spaceId,
                'createdby' => $createdBy > 0 ? $createdBy : 1,
                'wf_status' => 0,
                'livestatus' => '1',
            ];
            return $this->db->insert('s_notification', [
                's_user_id' => $userId,
                's_message' => $message,
                's_definition' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                's_is_read' => 0,
            ], $state);
        } catch (\Throwable $e) {
            error_log('NotificationService.logEvent error: ' . $e->getMessage());
            return null;
        }
    }

    public function logUserEvent(string $message, int $userId, array $context = []): ?int {
        $context['user_id'] = $userId;
        $context['audience'] = 'user';
        return $this->logEvent($message, $context);
    }

    public function logWorkspaceEvent(string $message, int $spaceId, array $context = []): ?int {
        $context['space_id'] = $spaceId;
        $context['audience'] = 'workspace';
        return $this->logEvent($message, $context);
    }

    public function logGlobalEvent(string $message, array $context = []): ?int {
        $context['space_id'] = 0;
        $context['audience'] = 'global';
        return $this->logEvent($message, $context);
    }

    /**
     * Returns notifications visible to a principal.
     */
    public function fetchNotifications(int $userId, array $spaceIds = [], array $options = []): array {
        $limit = max(1, (int)($options['limit'] ?? 25));
        $offset = max(0, (int)($options['offset'] ?? 0));
        $includeWorkspace = (bool)($options['include_workspace'] ?? true);
        $includeGlobal = (bool)($options['include_global'] ?? true);
        $isSuperAdmin = (bool)($options['super_admin'] ?? false);
        $onlyUnread = (bool)($options['only_unread'] ?? false);

        $sql = "SELECT * FROM s_notification WHERE livestatus = '1'";
        $params = [];

        if ($isSuperAdmin) {
            if ($onlyUnread) {
                $sql .= " AND s_is_read = 0";
            }
            $sql .= sprintf(" ORDER BY createstamp DESC LIMIT %d OFFSET %d", $limit, $offset);
            $rows = $this->db->query($sql, $params);
            return $this->hydrateRows($rows);
        }

        $clauses = [];
        if ($userId > 0) {
            $clauses[] = 's_user_id = :aud_user';
            $params[':aud_user'] = $userId;
        }

        $spaceIds = array_values(array_unique(array_filter(array_map('intval', $spaceIds))));
        if ($includeWorkspace && !empty($spaceIds)) {
            $spacePlaceholders = [];
            foreach ($spaceIds as $index => $spaceId) {
                $placeholder = ':space' . $index;
                $spacePlaceholders[] = $placeholder;
                $params[$placeholder] = $spaceId;
            }
            $clauses[] = sprintf(
                "(space_id IN (%s) AND (s_user_id IS NULL OR s_user_id = 0))",
                implode(',', $spacePlaceholders)
            );
        }

        if ($includeGlobal) {
            $clauses[] = "(space_id = 0 AND (s_user_id IS NULL OR s_user_id = 0))";
        }

        if (!empty($clauses)) {
            $sql .= ' AND (' . implode(' OR ', $clauses) . ')';
        }

        if ($onlyUnread) {
            $sql .= " AND s_is_read = 0";
        }

        $sql .= sprintf(" ORDER BY createstamp DESC LIMIT %d OFFSET %d", $limit, $offset);
        $rows = $this->db->query($sql, $params);
        return $this->hydrateRows($rows);
    }

    /**
     * Shared activity feed accessor (for user/workspace/global scopes).
     */
    public function fetchActivity(array $options = []): array {
        $limit = max(1, (int)($options['limit'] ?? 50));
        $offset = max(0, (int)($options['offset'] ?? 0));
        $sql = "SELECT * FROM s_notification WHERE livestatus != '0'";
        $params = [];

        if (!empty($options['user_id'])) {
            $sql .= " AND s_user_id = :activity_user";
            $params[':activity_user'] = (int)$options['user_id'];
        }

        if (isset($options['space_id']) && (int)$options['space_id'] > 0) {
            $sql .= " AND space_id = :activity_space";
            $params[':activity_space'] = (int)$options['space_id'];
        }

        if (isset($options['is_global']) && $options['is_global'] === true) {
            $sql .= " AND space_id = 0";
        }

        if (!empty($options['from'])) {
            $sql .= " AND createstamp >= :activity_from";
            $params[':activity_from'] = $options['from'];
        }

        if (!empty($options['to'])) {
            $sql .= " AND createstamp <= :activity_to";
            $params[':activity_to'] = $options['to'];
        }

        if (!empty($options['event_type'])) {
            $sql .= " AND JSON_EXTRACT(COALESCE(s_definition, '{}'), '$.metadata.event_type') = :activity_event";
            $params[':activity_event'] = $options['event_type'];
        }

        $sql .= sprintf(" ORDER BY createstamp DESC LIMIT %d OFFSET %d", $limit, $offset);
        $rows = $this->db->query($sql, $params);
        return $this->hydrateRows($rows);
    }

    /**
     * Counts unread notifications for a given audience.
     */
    public function countUnread(int $userId, array $spaceIds = [], bool $isSuperAdmin = false): int {
        $sql = "SELECT COUNT(*) AS total FROM s_notification WHERE livestatus != '0' AND s_is_read = 0";
        $params = [];

        if ($isSuperAdmin) {
            $rows = $this->db->query($sql, $params);
            return isset($rows[0]['total']) ? (int)$rows[0]['total'] : 0;
        }

        $clauses = [];
        if ($userId > 0) {
            $clauses[] = 's_user_id = :count_user';
            $params[':count_user'] = $userId;
        }

        $spaceIds = array_values(array_unique(array_filter(array_map('intval', $spaceIds))));
        if (!empty($spaceIds)) {
            $spacePlaceholders = [];
            foreach ($spaceIds as $index => $spaceId) {
                $placeholder = ':count_space' . $index;
                $spacePlaceholders[] = $placeholder;
                $params[$placeholder] = $spaceId;
            }
            $clauses[] = sprintf(
                "(space_id IN (%s) AND (s_user_id IS NULL OR s_user_id = 0))",
                implode(',', $spacePlaceholders)
            );
        }

        $clauses[] = "(space_id = 0 AND (s_user_id IS NULL OR s_user_id = 0))";
        $sql .= ' AND (' . implode(' OR ', $clauses) . ')';

        $rows = $this->db->query($sql, $params);
        return isset($rows[0]['total']) ? (int)$rows[0]['total'] : 0;
    }

    /**
     * Marks notifications as read.
     */
    public function markRead(array $ids, ?int $actorId = null, bool $read = true): int {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':id' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $params[':actor'] = $actorId;

        $sql = sprintf(
            "UPDATE s_notification SET s_is_read = :read_state, updatedby = :actor, updatestamp = NOW() WHERE id IN (%s)",
            implode(',', $placeholders)
        );
        $params[':read_state'] = $read ? 1 : 0;
        $this->db->query($sql, $params);
        return count($ids);
    }

    public function archive(array $ids, ?int $actorId = null): int {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':id' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $params[':actor'] = $actorId;

        $sql = sprintf(
            "UPDATE s_notification SET livestatus = '2', updatedby = :actor, updatestamp = NOW() WHERE id IN (%s)",
            implode(',', $placeholders)
        );
        $this->db->query($sql, $params);
        return count($ids);
    }

    private function hydrateRows(array $rows): array {
        foreach ($rows as &$row) {
            $details = $this->decodeDefinition($row['s_definition'] ?? null);
            $row['link'] = $details['link'] ?? null;
            $row['metadata'] = $details['metadata'] ?? [];
            $row['scope'] = $this->inferAudience((int)$row['space_id'], $row['s_user_id'] !== null ? (int)$row['s_user_id'] : null);
        }
        unset($row);
        return $rows;
    }

    private function decodeDefinition(?string $definition): array {
        if ($definition === null || trim($definition) === '') {
            return [];
        }

        $decoded = json_decode($definition, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }
        return $decoded;
    }

    private function inferAudience(int $spaceId = 0, ?int $userId = null): string {
        if ($userId !== null && $userId > 0) {
            return 'user';
        }
        if ($spaceId > 0) {
            return 'workspace';
        }
        return 'global';
    }
}
