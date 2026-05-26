<?php
namespace Core\App;

/**
 * Notification service for applications.
 * Supports fetching and creating simple notifications.
 *
 * Usage in a route (rad/ms/{ms}/route.{id}.php):
 * $notif = new \Core\App\Notification($db);
 * $notif->logRoute([
 *     'route_id' => 12,            // or 'route_uid' => '...'
 *     'action' => 'invoke',        // e.g., invoke/create/update
 *     'actor_id' => $entityId,     // optional
 *     'actor_name' => $entityName, // optional
 *     'description' => 'Called tasks list', // optional
 *     'user_id' => $entityId,      // optional target user
 *     'space_id' => $spaceId ?? 0, // optional
 * ]);
 */
class Notification {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get recent notifications for a user.
     *
     * @param int $userId s_entity.id of the user
     * @param int $limit number of records
     * @return array Notification rows (empty when no user)
     */
    public function recent(int $userId, int $limit = 20): array {
        if ($userId <= 0) {
            return [];
        }
        return $this->db->query(
            "SELECT * FROM s_notification
             WHERE s_user_id = :uid
             ORDER BY createstamp DESC
             LIMIT {$limit}",
            [':uid' => $userId]
        );
    }

    /**
     * Create a notification.
     *
     * @param int $userId s_entity.id of the user
     * @param string $title Title text
     * @param string $message Body text
     * @return int Inserted id
     *
     * @throws \InvalidArgumentException when userId/title missing
     */
    public function create(int $userId, string $title, string $message): int {
        if ($userId <= 0 || $title === '') {
            throw new \InvalidArgumentException('userId and title are required');
        }
        return (int)$this->db->insert('s_notification', [
            's_user_id' => $userId,
            's_message' => $message,
            'livestatus' => '1',
            'createstamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log a notification for a route, rendering the route's template when present.
     *
     * $data keys:
     * - route_id or route_uid (required)
     * - action (required)
     * - actor_id (optional), actor_name (optional)
     * - ms_id (optional)
     * - description (optional)
     * - space_id (optional)
     * - user_id (optional target user)
     */
    public function logRoute(array $data): ?int {
        $action = trim($data['action'] ?? '');
        if ($action === '') {
            return null;
        }

        $routeRow = $this->resolveRouteRow($data);
        if (!$routeRow) {
            return null;
        }
        $msRow = $this->resolveMsRow($data, (int)($routeRow['s_ms_id'] ?? 0));

        $actorId = isset($data['actor_id']) ? (int)$data['actor_id'] : null;
        $actorName = $data['actor_name'] ?? '';
        $timestamp = date('Y-m-d H:i:s T');

        $context = [
            '{action}' => $action,
            '{route_id}' => (string)($routeRow['id'] ?? ''),
            '{route_uid}' => $routeRow['uid'] ?? '',
            '{route_name}' => $routeRow['s_name'] ?? '',
            '{route_description}' => $data['description'] ?? ($routeRow['s_description'] ?? ''),
            '{ms_id}' => (string)($msRow['id'] ?? ''),
            '{ms_uid}' => $msRow['uid'] ?? '',
            '{ms_name}' => $msRow['s_name'] ?? '',
            '{actor}' => $actorName,
            '{timestamp}' => $timestamp,
        ];

        $template = $routeRow['s_notification_template'] ?? '';
        $message = $this->renderTemplate($template, $context, sprintf('Route %s: %s', $action, $context['{route_name}']));

        $spaceId = isset($data['space_id']) ? (int)$data['space_id'] : 0;
        $targetUserId = isset($data['user_id']) ? (int)$data['user_id'] : null;

        $payload = [
            'link' => $data['link'] ?? null,
            'metadata' => [
                'event_type' => 'route_' . $action,
                'route_id' => (int)$routeRow['id'],
                'route_uid' => $routeRow['uid'] ?? '',
                'route_name' => $context['{route_name}'],
                'ms_id' => $msRow['id'] ?? null,
                'ms_uid' => $msRow['uid'] ?? '',
                'ms_name' => $context['{ms_name}'],
                'actor' => $actorName,
                'timestamp' => $timestamp,
            ],
        ];

        try {
            return (int)$this->db->insert('s_notification', [
                'uid' => $this->db->generateUuidV4(),
                'livestatus' => '1',
                'versioncode' => 1,
                'wf_status' => 0,
                'space_id' => $spaceId,
                'createdby' => $actorId,
                'createstamp' => date('Y-m-d H:i:s'),
                'updatedby' => $actorId,
                's_user_id' => $targetUserId,
                's_message' => $message,
                's_definition' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                's_is_read' => 0,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function renderTemplate(string $template, array $context, string $fallback): string {
        $tpl = trim($template);
        if ($tpl !== '') {
            $rendered = strtr($tpl, $context);
            if ($rendered !== '') {
                return $rendered;
            }
        }
        return $fallback;
    }

    private function resolveRouteRow(array $data): ?array {
        $id = isset($data['route_id']) ? (int)$data['route_id'] : 0;
        $uid = $data['route_uid'] ?? '';
        if ($id > 0) {
            $rows = $this->db->select('s_msroute', ['id' => $id], true);
        } elseif ($uid !== '') {
            $rows = $this->db->select('s_msroute', ['uid' => $uid], true);
        } else {
            return null;
        }
        return $rows[0] ?? null;
    }

    private function resolveMsRow(array $data, int $fallbackMsId): array {
        $msId = isset($data['ms_id']) ? (int)$data['ms_id'] : $fallbackMsId;
        if ($msId <= 0) {
            return [];
        }
        $rows = $this->db->select('s_ms', ['id' => $msId], true);
        return $rows[0] ?? [];
    }
}
