CREATE TABLE `a_project` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `a_name` VARCHAR(50) NOT NULL,
  `a_description` TEXT,
  INDEX (`uid`),
  INDEX (`livestatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `a_task` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `a_name` VARCHAR(255) NOT NULL,
  `a_description` TEXT,
  `a_project_id` BIGINT(20),
  `a_type` ENUM('todo', 'task') NOT NULL,
  `a_work_file` VARCHAR(255),
  `a_reviewed` ENUM('Y', 'N') NOT NULL DEFAULT 'N',
  `a_approved` ENUM('Y', 'N') NOT NULL DEFAULT 'N',
  INDEX (`uid`),
  INDEX (`livestatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `a_task_reviewer` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `a_task_id` bigint(20) NOT NULL,
  `a_user_id` bigint(20) NOT NULL,
  INDEX (`uid`),
  INDEX (`livestatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `a_task_approver` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `a_task_id` bigint(20) NOT NULL,
  `a_user_id` bigint(20) NOT NULL,
  INDEX (`uid`),
  INDEX (`livestatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `a_task_feed` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `a_task_id` BIGINT(20),
  `a_user_id` BIGINT(20),
  `a_content` TEXT NOT NULL,
  `a_type` ENUM('annotation', 'comment') NOT NULL,
  INDEX (`uid`),
  INDEX (`livestatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
