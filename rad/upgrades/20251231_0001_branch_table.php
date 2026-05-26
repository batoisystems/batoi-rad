<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20251231_0001_branch_table',
    'description' => 'Create s_branch table for beta/live branch tracking.',
    'run' => function (Database $db, Logger $logger): void {
        $db->query(
            "CREATE TABLE IF NOT EXISTS `s_branch` (
              `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '1' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
              `versioncode` int(11) DEFAULT NULL,
              `wf_status` int(11) NOT NULL DEFAULT '0',
              `space_id` bigint(20) NOT NULL DEFAULT '0',
              `createdby` bigint(20) DEFAULT NULL,
              `createstamp` datetime DEFAULT NULL,
              `updatedby` bigint(20) DEFAULT NULL,
              `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

              `s_object_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
              `s_object_id` bigint(20) NOT NULL,
              `s_branch` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'beta',
              `s_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
              `s_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `s_payload` json DEFAULT NULL,

              PRIMARY KEY (`id`),
              UNIQUE KEY `uid` (`uid`),
              KEY `idx_object` (`s_object_type`, `s_object_id`),
              KEY `idx_branch` (`s_branch`),
              KEY `idx_status` (`s_status`),
              KEY `idx_created` (`createstamp`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $logger->logSql('[branch table] s_branch ready.');
    },
];
