<?php
namespace Core\Sys;

/**
 * Lightweight activity logger for user-facing/auditable events.
 */
class ActivityService {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Record an activity.
     * $data keys: s_actor_id (int, optional), s_object_type (string), s_object_id (int), s_action (string), s_message (string), s_payload (array|null), space_id (int, optional).
     */
    public function log(array $data): ?int {
        $objectType = trim($data['s_object_type'] ?? '');
        $action = trim($data['s_action'] ?? '');
        if ($objectType === '' || $action === '') {
            return null;
        }

        $payload = $data['s_payload'] ?? null;
        $meta = [
            'activity_label' => $data['s_message'] ?? ($objectType . ':' . $action),
            'space_id' => (int)($data['space_id'] ?? 0),
        ];
        if (is_array($payload)) {
            if (isset($payload['activity_notify'])) {
                $meta['activity_notify'] = (bool)$payload['activity_notify'];
            }
            if (isset($payload['activity_severity'])) {
                $meta['activity_severity'] = (string)$payload['activity_severity'];
            }
        }

        if (class_exists('\\Core\\Sys\\ActivityContext')) {
            \Core\Sys\ActivityContext::set($meta);
        }

        return null;
    }
}
