<?php
declare(strict_types=1);

return [
    'id' => '20260716_0002_session_timeouts',
    'description' => 'Add a safe default idle timeout and standardize session timeout values as seconds.',
    'run' => static function (\Core\Sys\Database $db): void {
        $existing = $db->query(
            "SELECT id FROM s_config WHERE s_config_handle = 'session_idle_timeout' AND s_config_origin = 'S' LIMIT 1"
        );
        if ($existing === []) {
            $db->query(
                "INSERT INTO s_config
                    (uid, livestatus, wf_status, space_id, updatestamp, s_config_handle, s_config_value, s_config_origin, s_description)
                 VALUES (UUID(), '1', '0', '0', NOW(), 'session_idle_timeout', '900', 'S', 'Session idle timeout in seconds.')"
            );
        }
    },
    'rollback' => null,
];
