CREATE TABLE `a_sample` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tenantid` bigint(20) DEFAULT 0,
  INDEX (`uid`),
  INDEX (`livestatus`),
  INDEX (`tenantid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `a_locale` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_locale_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_locale_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_script_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `a_timezone` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_timezone_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_timezone_code` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_gmt_offset` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
