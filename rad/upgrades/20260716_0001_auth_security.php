<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20260716_0001_auth_security',
    'description' => 'Add privacy-preserving primary-login throttling and secure developer-tool defaults.',
    'run' => function (Database $db, Logger $logger): void {
        $db->query("CREATE TABLE IF NOT EXISTS `s_auth_attempt` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `bucket_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
            `identity_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
            `ip_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
            `failed_count` int NOT NULL DEFAULT 0,
            `window_started_at` datetime NOT NULL,
            `blocked_until` datetime DEFAULT NULL,
            `last_attempt_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_auth_attempt_bucket` (`bucket_hash`),
            KEY `idx_auth_attempt_blocked` (`blocked_until`),
            KEY `idx_auth_attempt_last` (`last_attempt_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            'ai_code_assist_enabled' => ['N', 'Enable AIF-backed code assistance for authorized developers.'],
            'developer_tools_enabled' => ['N', 'Enable privileged RAD source and read-only SQL developer tools.'],
            'login_max_attempts' => ['5', 'Failed primary login attempts allowed per identity and IP.'],
            'login_attempt_window_seconds' => ['900', 'Primary login failure counting window in seconds.'],
            'login_lockout_seconds' => ['900', 'Primary login lockout duration in seconds.'],
        ] as $handle => [$value, $description]) {
            $existing = $db->query(
                "SELECT id FROM s_config WHERE s_config_handle = :handle AND s_config_origin = 'S' LIMIT 1",
                [':handle' => $handle]
            );
            if ($existing === []) {
                $db->query(
                    "INSERT INTO s_config
                        (uid, livestatus, versioncode, wf_status, space_id, createdby, createstamp, updatedby, updatestamp,
                         s_config_handle, s_config_value, s_config_origin, s_description)
                     VALUES (:uid, '1', 1, 0, 0, 1, NOW(), 1, NOW(), :handle, :value, 'S', :description)",
                    [
                        ':uid' => $db->generateUuidV4(),
                        ':handle' => $handle,
                        ':value' => $value,
                        ':description' => $description,
                    ]
                );
            }
        }
        $logger->logSql('[auth security] Login throttle and safe developer defaults installed.');
    },
];
