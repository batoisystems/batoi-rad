<?php
namespace Core\Sys;

/**
 * Bridge activity metadata to notifications (realtime + ingest).
 */
class NotificationBridge {
    private Database $db;
    private NotificationService $notificationService;
    private array $config;

    public function __construct(Database $db, array $config = []) {
        $this->db = $db;
        $this->notificationService = new NotificationService($db);
        $this->config = $config;
    }

    /**
     * Create notifications based on activity context.
     *
     * @param array $activity Activity metadata (activity_label, activity_notify, activity_severity, space_id, etc.)
     * @param array $context  Context overrides (entity_id, actor_id, space_id, link, event_type, audience)
     * @param string $source  realtime|ingest
     * @return int Count of notifications created
     */
    public function notifyFromActivity(array $activity, array $context = [], string $source = 'realtime'): int {
        if (!$this->isEnabled()) {
            return 0;
        }
        if (!$this->shouldProcessSource($source)) {
            return 0;
        }

        $notifyFlag = $this->toBool($activity['activity_notify'] ?? $activity['notify'] ?? $context['activity_notify'] ?? null);
        if (!$notifyFlag) {
            return 0;
        }

        if ($source === 'ingest' && $this->toBool($activity['activity_notified'] ?? null)) {
            return 0;
        }

        $severity = strtolower((string)($activity['activity_severity'] ?? $activity['severity'] ?? $context['activity_severity'] ?? 'info'));
        if (!$this->passesSeverityThreshold($severity)) {
            return 0;
        }

        $label = trim((string)($activity['activity_label'] ?? $activity['label'] ?? $context['activity_label'] ?? ''));
        $eventType = (string)($activity['event_type'] ?? $activity['activity_event'] ?? $context['event_type'] ?? '');
        if ($label === '') {
            $label = $eventType !== '' ? $eventType : 'Activity';
        }

        $spaceId = isset($activity['space_id']) ? (int)$activity['space_id'] : (int)($context['space_id'] ?? 0);
        $actorId = (int)($context['actor_id'] ?? $activity['actor_id'] ?? $context['entity_id'] ?? 0);
        $targetUserId = isset($activity['user_id']) ? (int)$activity['user_id'] : (int)($context['user_id'] ?? $context['entity_id'] ?? 0);
        $audience = strtolower((string)($activity['audience'] ?? $context['audience'] ?? ''));

        $link = (string)($activity['link'] ?? $context['link'] ?? '');
        if ($link === '' && !empty($activity['host']) && !empty($activity['uri'])) {
            $link = 'https://' . $activity['host'] . $activity['uri'];
        }

        $metadata = [
            'event_type' => $eventType,
            'activity_severity' => $severity,
            'activity_profile' => $activity['activity_profile'] ?? $context['activity_profile'] ?? null,
            'activity_profiles' => $activity['activity_profiles'] ?? $context['activity_profiles'] ?? null,
        ];
        foreach (['ms_id', 'ms_name', 'route_id', 'route_uid', 'route_slug', 'route_name'] as $key) {
            if (array_key_exists($key, $activity) && $activity[$key] !== null && $activity[$key] !== '') {
                $metadata[$key] = $activity[$key];
            }
        }

        $created = 0;
        $userContext = [
            'space_id' => $spaceId,
            'user_id' => $targetUserId > 0 ? $targetUserId : null,
            'created_by' => $actorId > 0 ? $actorId : null,
            'event_type' => $eventType,
            'link' => $link !== '' ? $link : null,
            'metadata' => $metadata,
        ];

        if ($audience === 'global') {
            $userContext['space_id'] = 0;
            $userContext['user_id'] = null;
            $created += $this->notificationService->logEvent($label, $userContext) ? 1 : 0;
            return $created;
        }

        if ($spaceId > 0) {
            if ($audience === 'workspace' || $audience === 'both' || $audience === '') {
                $workspaceContext = $userContext;
                $workspaceContext['user_id'] = null;
                $created += $this->notificationService->logEvent($label, $workspaceContext) ? 1 : 0;
            }
            if ($audience === 'user' || $audience === 'both' || $audience === '') {
                if ($targetUserId > 0) {
                    $created += $this->notificationService->logEvent($label, $userContext) ? 1 : 0;
                }
            }
            return $created;
        }

        if ($audience === 'workspace') {
            return 0;
        }

        if ($targetUserId > 0) {
            $created += $this->notificationService->logEvent($label, $userContext) ? 1 : 0;
        }
        return $created;
    }

    private function isEnabled(): bool {
        $enabled = strtoupper((string)$this->getConfigValue('notifications_enabled', 'Y'));
        return $enabled !== 'N';
    }

    private function shouldProcessSource(string $source): bool {
        $mode = strtolower((string)$this->getConfigValue('notifications_mode', 'both'));
        if ($mode === 'both') {
            return true;
        }
        return $mode === strtolower($source);
    }

    private function passesSeverityThreshold(string $severity): bool {
        $min = strtolower((string)$this->getConfigValue('notifications_min_severity', 'info'));
        $rank = ['info' => 1, 'warn' => 2, 'warning' => 2, 'critical' => 3];
        $sevRank = $rank[$severity] ?? 1;
        $minRank = $rank[$min] ?? 1;
        return $sevRank >= $minRank;
    }

    private function toBool($value): bool {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower((string)$value);
        return in_array($value, ['1', 'y', 'yes', 'true'], true);
    }

    private function getConfigValue(string $handle, ?string $fallback = null): ?string {
        static $cache = [];
        if (array_key_exists($handle, $cache)) {
            return $cache[$handle];
        }
        $rows = $this->db->select('s_config', ['s_config_handle' => $handle], true);
        if (!empty($rows)) {
            $cache[$handle] = $rows[0]['s_config_value'] ?? $fallback;
            return $cache[$handle];
        }
        $cache[$handle] = $fallback;
        return $fallback;
    }

}
