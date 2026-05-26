CREATE TABLE s_activity (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_actor_id`     BIGINT(20) DEFAULT NULL,
  `s_object_type` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'e.g., ms, route, data, asset',
  `s_object_id`  BIGINT(20) DEFAULT NULL,
  `s_action`     VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'e.g., create, update, invoke',
  `s_message`    VARCHAR(512) DEFAULT NULL,
  `s_payload`    JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  KEY `idx_actor` (`s_actor_id`),
  KEY `idx_space` (`space_id`),
  KEY `idx_object` (`s_object_type`, `s_object_id`),
  KEY idx_created (`createstamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_api_endpoint` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `uid` char(36) NOT NULL,
    `livestatus` enum('0','1','2','3') DEFAULT '1',
    `s_name` varchar(255) NOT NULL,
    `s_slug` varchar(120) NOT NULL,
    `s_type` enum('system_table','system_service','utility','vendor','ai') NOT NULL,
    `s_target` varchar(255) NOT NULL,
    `s_definition` json NULL,
    `s_description` text,
    `s_access_role_ids` varchar(255) DEFAULT NULL,
    `s_rate_limit` json NULL,
    `createdby` bigint(20) DEFAULT NULL,
    `createstamp` datetime DEFAULT NULL,
    `updatedby` bigint(20) DEFAULT NULL,
    `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `s_slug` (`s_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `s_config` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_config_handle` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_config_value` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_config_origin` enum('S','A') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'S = Sys, A = App',
  `s_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `s_config_handle` (`s_config_handle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `s_config` (`id`, `uid`, `livestatus`, `versioncode`, `wf_status`, `space_id`, `createdby`, `createstamp`, `updatedby`, `updatestamp`, `s_config_handle`, `s_config_value`, `s_config_origin`, `s_description`) VALUES
(1, '146d40e0-1530-11ee-9383-1a1105e56d75', '1', 2, 0, 0, 0, '2023-06-28 02:47:54', 1, '2024-08-18 09:33:02', 'project_title', 'ACME Co Application', 'S', ''),
(2, '146d4482-1530-11ee-9383-1a1105e56d75', '1', 4, 0, 0, 0, '2023-06-28 02:47:54', 1, '2024-08-18 09:32:39', 'author', 'Ashwini Rath', 'S', ''),
(3, '146d46bc-1530-11ee-9383-1a1105e56d75', '1', 2, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'encryption_key', 'encryptionkey', 'S', 'Encryption'),
(4, '146d5986-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'encryption_salt', 'encryptionsalt', 'S', NULL),
(5, '146d5a3a-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'base_url', 'https://localhost.radsandbox', 'S', NULL),
(6, '146d5ac6-1530-11ee-9383-1a1105e56d75', '1', 9, 0, 0, 0, '2023-06-28 02:47:54', 1, '2024-09-16 03:32:19', 'allowed_ips', '115.246.160.19,103.199.183.3,182.72.107.186,127.0.0.1,::1', 'S', ''),
(7, '146d5b52-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'timezone', 'America/New_York', 'S', NULL),
(8, '146d5bd4-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'locale', 'en_US', 'S', NULL),
(9, '146d5c56-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'currency', 'USD', 'S', NULL),
(10, '146d5cd8-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'country', 'US', 'S', NULL),
(11, '146d5d46-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'session_name', 'mysession', 'S', NULL),
(12, '146d5db4-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'session_lifetime', '1800', 'S', NULL),
(13, '146d5e40-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'session_path_default', 'N', 'S', NULL),
(14, '146d5eae-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'session_domain', 'localhost.radsandbox', 'S', NULL),
(15, '146d5f26-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'session_secure', '1', 'S', NULL),
(16, '146d5f9e-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'session_httponly', '1', 'S', NULL),
(17, '146d6016-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'sso_role_id_default', 'defaultrole', 'S', NULL),
(18, '146d6098-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'mfa_settings', 'defaultmfa', 'S', NULL),
(19, '146d6106-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'display_errors', 'true', 'S', NULL),
(20, '146d6174-1530-11ee-9383-1a1105e56d75', '1', 3, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'site_status', 'L', 'S', ''),
(21, '146d61e2-1530-11ee-9383-1a1105e56d75', '1', 6, 0, 0, 0, '2023-06-28 02:47:54', 1, '2024-09-16 03:31:27', 'ip_access_restrict', 'Y', 'S', ''),
(22, '146d625a-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'site_update_pending', 'N', 'S', NULL),
(23, '146d62c8-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:54', 0, '2023-06-27 21:17:54', 'update_items', 'value_update_items', 'S', NULL),
(24, 'd159fc5e-1925-11ee-b672-682b9e9922e1', '1', 1, 0, 0, 0, '2023-07-03 03:44:31', 0, '2023-07-02 22:14:31', 'session_idle_timeout', '300', 'S', NULL),
(25, '0d5c4f4a-5dae-11ef-b9ad-996d5461e764', '1', 1, 0, 0, 1, '2024-08-19 03:36:00', 1, '2024-08-18 22:06:00', 'default_saas_route_id', '48', 'S', NULL),
(26, '88ff8cc8-1e82-11ee-876a-95b0b26afebb', '1', 11, 0, 0, 0, '2023-07-09 23:30:48', 0, '2023-07-09 18:00:48', 'mailgun_api_key', '738bc70cc9daadedd8de459998f9da44-6d8d428c-2aa32274', 'A', ''),
(27, 'c09443fa-23fe-11ee-8bfb-b22cc24825de', '1', 1, 0, 0, 0, '2023-07-16 23:02:35', 0, '2023-07-16 17:32:35', 'enable_sql_log', 'true', 'S', NULL),
(28, '366c6594-23ff-11ee-8bfb-b22cc24825de', '1', 1, 0, 0, 0, '2023-07-16 23:05:53', 0, '2023-07-16 17:35:53', 'encryption_method', 'AES-256-CBC', 'S', NULL),
(29, '34301fc7-3a8a-46fd-bb7e-6824246e6d71', '1', 2, 0, 0, 1, '2023-10-08 16:46:39', 1, '2023-10-08 11:16:39', 'Email_From_Address', 'noreply@batoi.com', 'A', ''),
(30, '4aac331b-4d41-4c95-adaf-792e35914675', '1', 6, 0, 0, 1, '2023-10-08 16:48:07', 1, '2023-10-08 11:18:07', 'Email_Server', 'mg.batoi.com', 'A', ''),
(31, 'b03eecc9-8b54-439d-98f6-25f9f02a18cd', '1', 1, 0, 0, 1, '2023-10-08 16:48:26', 1, '2023-10-08 11:18:26', 'paramer_test', 'asdsada', 'A', NULL),
(32, '9a6ae3d0-6884-11ee-aab2-29a0a663fbc3', '1', 3, 0, 0, NULL, NULL, NULL, '2023-10-11 22:22:03', 'home_page', 'content', 'S', ''),
(33, 'f41d6cda-6885-11ee-aab2-29a0a663fbc3', '1', 2, 0, 0, 0, NULL, 1, '2024-08-13 06:52:40', 'home_page_redirect_url', '/adminpanel/1', 'S', ''),
(34, 'aa01837c-6a13-11ee-a88c-b55279679ad0', '1', 0, 0, 0, 0, NULL, 0, '2023-10-13 21:58:38', 'home_page_content', 'web,1', 'S', NULL);

INSERT INTO `s_config` (`id`, `uid`, `livestatus`, `versioncode`, `wf_status`, `space_id`, `createdby`, `createstamp`, `updatedby`, `updatestamp`, `s_config_handle`, `s_config_value`, `s_config_origin`, `s_description`) VALUES
(NULL, NULL, '1', NULL, '0', '0', NULL, NULL, NULL, CURRENT_TIMESTAMP, 'home_dashboard_cache_ttl', '120', 'S', 'Home dashboard cache TTL in seconds.');

INSERT INTO `s_config` (`id`, `uid`, `livestatus`, `versioncode`, `wf_status`, `space_id`, `createdby`, `createstamp`, `updatedby`, `updatestamp`, `s_config_handle`, `s_config_value`, `s_config_origin`, `s_description`) VALUES
(NULL, NULL, '1', NULL, '0', '0', NULL, NULL, NULL, CURRENT_TIMESTAMP, 'rad_admin_uif_enabled', 'N', 'S', 'Enable experimental Batoi UIF assets in RAD Admin.');

INSERT INTO `s_config` (`id`, `uid`, `livestatus`, `versioncode`, `wf_status`, `space_id`, `createdby`, `createstamp`, `updatedby`, `updatestamp`, `s_config_handle`, `s_config_value`, `s_config_origin`, `s_description`) VALUES
(NULL, NULL, '1', NULL, '0', '0', NULL, NULL, NULL, CURRENT_TIMESTAMP, 'rad_admin_monaco_base_url', '', 'S', 'Self-hosted Monaco editor base URL for RAD Admin code editors.');

INSERT INTO `s_config` (`id`, `uid`, `livestatus`, `versioncode`, `wf_status`, `space_id`, `createdby`, `createstamp`, `updatedby`, `updatestamp`, `s_config_handle`, `s_config_value`, `s_config_origin`, `s_description`) VALUES (NULL, NULL, '1', NULL, '0', '0', NULL, NULL, NULL, CURRENT_TIMESTAMP, 'dev_debug_flag', 'Y', 'S', 'This flag has two values Y and N. If Y, developers debug statements will display and if N, they will not.');

CREATE TABLE `s_content` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_ms_id` bigint(20) DEFAULT NULL,
  `s_parent_id` bigint(20) NOT NULL DEFAULT '0',
  `s_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_summary` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_content` longtext COLLATE utf8mb4_unicode_ci,
  `s_publication` datetime DEFAULT NULL,
  `s_additional_info` json DEFAULT NULL,
  `s_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `s_meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_meta_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_canonical_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_type` enum('I','J','C','W','S','D','M','F') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'I',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `s_slug` (`s_slug`),
  KEY `s_ms_id` (`s_ms_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_data_field_group` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` timestamp NULL DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_service_id` bigint(20) DEFAULT NULL,
  `s_group_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_data_field_type` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` timestamp NULL DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_description` text COLLATE utf8mb4_unicode_ci,
  `s_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `s_name` (`s_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `s_data_field_type` (`id`, `uid`, `livestatus`, `versioncode`, `wf_status`, `space_id`, `createdby`, `createstamp`, `updatedby`, `updatestamp`, `s_name`, `s_description`, `s_definition`) VALUES
(1, 'd7607636-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'TEXT_BOX', 'Single-line text box', '{\"input_type\": \"text\"}'),
(2, 'd7610dc2-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'EMAIL', 'Email Field', '{\"input_type\": \"email\", \"options\": null}'),
(3, 'd761ac53-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'URL', 'URL Field', '{\"input_type\": \"url\", \"options\": null}'),
(4, 'd76239b7-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'PASSWORD', 'Password Field', '{\"input_type\": \"password\", \"options\": null}'),
(5, 'd7632735-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'NUMBER', 'Number Field (Integer)', '{\"input_type\": \"number\", \"options\": null}'),
(6, 'd763b231-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'DECIMAL', 'Number Field (Decimal)', '{\"input_type\": \"decimal\", \"options\": null}'),
(7, 'd7643924-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'PHONE', 'Phone Number Field (with country code)', '{\"input_type\": \"phone\", \"options\": null}'),
(8, 'd764c166-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'PHONE_NO_CC', 'Phone Number Field (without country code)', '{\"input_type\": \"phone_no_cc\", \"options\": null}'),
(9, 'd7654cec-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'MONEY', 'Money Field (with currency code)', '{\"input_type\": \"money\", \"options\": null}'),
(10, 'd765e915-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'MONEY_NO_CC', 'Money Field (without currency code)', '{\"input_type\": \"money_no_cc\", \"options\": null}'),
(11, 'd7667565-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'PERCENTAGE', 'Percentage Field (with currency code)', '{\"input_type\": \"percentage\", \"options\": null}'),
(12, 'd7670ac0-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'SINGLE_LINE_ENCRYPTED_TEXT', 'Single-Line Encrypted Textbox', '{\"input_type\": \"single_line_encrypted_text\", \"options\": null}'),
(13, 'd76798e9-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'MULTI_LINE_ENCRYPTED_TEXT', 'Multi-Line Encrypted Textbox', '{\"input_type\": \"multi_line_encrypted_text\", \"options\": null}'),
(14, 'd7682390-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'AUTO_SUGGEST', 'Auto-suggested Textbox', '{\"input_type\": \"auto_suggest\", \"source\": \"remote_or_local_data_source\"}'),
(15, 'd768b137-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'TEXT_AREA', 'Multi-line text box', '{\"input_type\": \"textarea\"}'),
(16, 'd76941ee-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'RICH_TEXT', 'Rich text box', '{\"input_type\": \"rich_text\"}'),
(17, 'd769cdc1-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'DROPDOWN', 'Dropdown (ENUM)', '{\"input_type\": \"enum\", \"options\": [\"Option 1\", \"Option 2\"]}'),
(18, 'd76a5cfc-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'SINGLE_CHECKBOX', 'Single-select Checkbox', '{\"input_type\": \"single_checkbox\"}'),
(19, 'd76ae8a7-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'MULTI_CHECKBOX', 'Multi-select Checkboxes', '{\"input_type\": \"multi_checkbox\", \"options\": [\"Option 1\", \"Option 2\", \"Option 3\"]}'),
(20, 'd76b7973-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'MULTI_SELECT', 'Multi-select Input Field (jQuery UI)', '{\"input_type\": \"multi_select\", \"options\": [\"Option 1\", \"Option 2\", \"Option 3\"], \"ui\": \"jquery_ui\"}'),
(21, 'd76c0a7f-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'RADIO_BUTTON', 'Radio Buttons', '{\"input_type\": \"radio\", \"options\": [\"Option 1\", \"Option 2\"]}'),
(22, 'd76c9722-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'RADIO_YES_NO', 'Radio Switcher for Yes and No', '{\"input_type\": \"radio\", \"options\": [\"Yes\", \"No\"]}'),
(23, 'd76d36cc-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'DATE_PICKER', 'Date picker', '{\"input_type\": \"date\"}'),
(24, 'd76dc351-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'TIME_PICKER', 'Time Picker Field', '{\"input_type\": \"time_picker\", \"options\": null}'),
(25, 'd76e73cd-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'DATE_RANGE', 'Date Range Picker Field', '{\"input_type\": \"date_range_picker\", \"options\": null}'),
(26, 'd76eface-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'COLOR_PICKER', 'Color Picker Field', '{\"input_type\": \"color_picker\", \"options\": null}'),
(27, 'd76f8472-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'CREDIT_CARD_FIELD', 'Credit Card Field (with expiry date and CVV)', '{\"input_type\": \"credit_card_field\", \"options\": {\"include_expiry\": true, \"include_cvv\": true}}'),
(28, 'd7700d74-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'FILE_UPLOAD', 'File Upload Field', '{\"input_type\": \"file\", \"options\": null}'),
(29, 'd7709937-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'MULTI_FILE_UPLOAD', 'Multiple File Upload Field', '{\"input_type\": \"multi_file\", \"options\": null}'),
(30, 'd771218d-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'FOREIGN_KEY', 'Foreign Key to another table', '{\"input_type\": \"foreign_key\", \"related_table\": \"related_table_here\", \"related_field\": \"related_field_here\"}'),
(31, 'd771a8f1-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'CSV_FOREIGN_KEYS', 'Comma-separated Foreign Keys', '{\"input_type\": \"csv_foreign_keys\", \"related_table\": \"related_table_here\", \"related_field\": \"related_field_here\"}'),
(32, 'd7722fd5-7597-11ee-9f7b-5254002886a4', '1', 1, 0, 0, NULL, NULL, NULL, '2023-10-28 13:55:04', 'CUSTOM_TYPE', 'Custom type where the developer can specify the details', '{\"input_type\": \"custom\", \"options\": {}}');


CREATE TABLE `s_data_field` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` timestamp NULL DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_mscontroller_id` bigint(20) DEFAULT NULL,
  `s_field_group_id` bigint(20) DEFAULT NULL,
  `s_sort_order` int(11) DEFAULT NULL,
  `s_field_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_field_label` text COLLATE utf8mb4_unicode_ci,
  `s_help_text` text COLLATE utf8mb4_unicode_ci,
  `s_field_type_id` bigint(20) DEFAULT NULL,
  `s_is_nullable` tinyint(1) DEFAULT '1',
  `s_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_data_method` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_service_id` bigint(20) DEFAULT NULL,
  `s_method_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_description` text COLLATE utf8mb4_unicode_ci,
  `s_method_type` enum('S','C') COLLATE utf8mb4_unicode_ci DEFAULT 'C',
  `s_custom_query` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_entity` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_type` enum('U','A') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'U' COMMENT 'U = User, A = API',
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_identity` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_identity_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_nonsaas_role_id` bigint(20) DEFAULT NULL,
  `s_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_mobile` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_login_mode` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_enable_mfa` enum('Y','N') COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `s_access_ips` text COLLATE utf8mb4_unicode_ci,
  `s_agreement_signed` enum('Y','N') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_api_types` json DEFAULT NULL,
  `s_api_allowed_endpoints` json DEFAULT NULL,
  `s_api_system_tables` json DEFAULT NULL,
  `s_api_system_services` json DEFAULT NULL,
  `s_mfa_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_mfa_backup_codes` json DEFAULT NULL,
  `s_trusted_devices` json DEFAULT NULL,
  `s_definition` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `s_name` (`s_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE s_entity
  ADD KEY idx_entity_type (s_type),
  ADD KEY idx_entity_type_status (s_type, livestatus),
  ADD KEY idx_entity_mfa (s_enable_mfa),
  ADD KEY idx_entity_identity (s_identity),
  ADD KEY idx_entity_email (s_email),
  ADD KEY idx_entity_mobile (s_mobile);

ALTER TABLE s_space_membership
  ADD KEY idx_entity (s_entity_id),
  ADD KEY idx_entity_live (s_entity_id, livestatus);


INSERT INTO `s_entity` (`id`, `uid`, `livestatus`, `versioncode`, `wf_status`, `space_id`, `createdby`, `createstamp`, `updatedby`, `updatestamp`, `s_type`, `s_name`, `s_identity`, `s_identity_secret`, `s_nonsaas_role_id`, `s_email`, `s_mobile`, `s_login_mode`, `s_enable_mfa`, `s_access_ips`, `s_agreement_signed`, `s_api_types`, `s_api_allowed_endpoints`, `s_api_system_tables`, `s_api_system_services`, `s_mfa_secret`, `s_mfa_backup_codes`, `s_trusted_devices`, `s_definition`) VALUES
(1, '142c2c40-1530-11ee-9383-1a1105e56d75', '1', 6, 0, 0, 0, '2023-06-28 02:47:53', 1, '2024-08-15 10:48:39', 'U', 'Administrator', 'admin', '$2y$10$vqMJxa2DF.ruAoNnsXJoe./WV3jCJpz3R90pmJdPulu3NeiWviyMG', 1, 'value_admin_email', 'value_admin_mobile', 'SE', 'N', '', 'N', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{}'),
(2, '72b74478-19e7-11ee-986e-e647b09d0336', '1', 5, 0, 0, 1, '2023-07-04 02:50:34', 1, '2024-08-18 09:24:30', 'U', 'Ashwini Rath', 'rath.ashwini@gmail.com', '$2y$10$lLUF5EE6SsZqD/QwtKtbUeF3hbqaj6Fi7jMRlN.w7Rk5zMpfJdku6', 1, 'rath.ashwini@gmail.com', '00917381044100', 'SE', 'N', '', 'Y', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{}'),
(3, 'bea8bcb8-19e7-11ee-986e-e647b09d0336', '1', 24, 0, 0, 1, '2023-07-04 02:52:42', 1, '2025-05-19 14:00:39', 'U', 'Joe Dale', 'joe.dale@gmail.com', '$2y$10$QjiK/HBAjRxbutjBMMef9uPSUPPRxZlxjxT5JHxNcJ8.q1ilne7lS', NULL, 'joe.dale@gmail.com', '00917381044101', 'SE', 'N', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{}'),
(4, 'ee21f5f6-0e6c-4766-9acf-249b0382862c', '1', 6, 0, 0, 1, '2023-11-15 10:03:05', 1, '2024-08-15 10:47:54', 'U', 'Ashwini Rath', 'ashwini.rath@batoi.systems', '$2y$10$3uEU8GG22L8PkG9smsqETe9Lt6ul7YdZbtQtlMH5qqRHrbsZfmuSG', 1, 'ashwini.rath@batoi.systems', '0013025091617', 'SE', 'N', '', 'Y', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{}');

CREATE TABLE `s_entity_session` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `s_entity_id` bigint(20) NOT NULL,
  `s_entity_sub_id` bigint(20) NOT NULL,
  `s_session_key` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_device_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Type of device e.g. mobile, desktop, server for API',
  `s_operating_system` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Operating System of the device or server of API',
  `s_browser` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Browser used for user and NULL for API',
  `s_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_otp` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `s_entity_session`
  ADD KEY `idx_entity_id` (`s_entity_id`),
  ADD KEY `idx_entity_sub_id` (`s_entity_sub_id`),
  ADD KEY `idx_session_key` (`s_session_key`);

CREATE TABLE `s_ms` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_description` text COLLATE utf8mb4_unicode_ci,
  `s_type` enum('STA','DYN','ID','UID') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ID',
  `s_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `s_scope` enum('global','platform','workspace') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `s_default_route_id` bigint(20) DEFAULT NULL,
  `s_tpl_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `s_name` (`s_name`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_mscontroller` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` timestamp NULL DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_ms_id` bigint(20) DEFAULT '0',
  `s_name` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_source_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_class_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_description` text COLLATE utf8mb4_unicode_ci,
  `s_type` enum('BL','DM') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_msroute` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_ms_id` bigint(20) DEFAULT '0',
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_activity_template` VARCHAR(255) NULL,
  `s_notification_template` VARCHAR(255) NULL,
  `s_degree` int(11) NOT NULL DEFAULT '3',
  `s_entity_scope` enum('UA','U','A') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'U',
  `s_service_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_notification` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_user_id` bigint(20) DEFAULT NULL,
  `s_message` text COLLATE utf8mb4_unicode_ci,
  `s_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `s_is_read` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  KEY `idx_user` (`s_user_id`,`s_is_read`,`livestatus`),
  KEY `idx_created` (`createstamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_permission_binding` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_object_type` enum('ms','route') COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_object_id` bigint(20) NOT NULL,
  `s_role_id` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_queue` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_queue_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_queue_script_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_execution_frequency` enum('1 min','5 min','15 min','30 min','1 h','2h','4h','6h','8h','12h','1d','1w','2w','1m','2m','3m','4m','6m','1y') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_last_executed` timestamp NULL DEFAULT NULL,
  `s_next_execution` timestamp NULL DEFAULT NULL,
  `s_queue_status` enum('Success','Failure') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_error_message` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_role` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_role_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_default_route_id` bigint(20) DEFAULT NULL,
  `s_scope` enum('platform','workspace','ms') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `s_description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `s_role` (`id`, `uid`, `livestatus`, `versioncode`, `wf_status`, `space_id`, `createdby`, `createstamp`, `updatedby`, `updatestamp`, `s_role_name`, `s_default_route_id`, `s_scope`, `s_code`, `s_ms_id`, `s_description`) VALUES
(1, '144cfc36-1530-11ee-9383-1a1105e56d75', '1', 1, 0, 0, 0, '2023-06-28 02:47:53', 0, '2023-06-27 21:17:53', 'Administrator', 1, 'platform', NULL, NULL, NULL),
(2, '2cf34c8e-19e7-11ee-986e-e647b09d0336', '1', 4, 0, 0, 0, '2023-07-04 02:48:37', 1, '2024-08-16 13:55:14', 'Manager', 48, 'workspace', NULL, NULL, NULL),
(3, '46748100-19e7-11ee-986e-e647b09d0336', '1', 5, 0, 0, 0, '2023-07-04 02:49:20', 1, '2024-08-16 13:54:39', 'Member', 48, 'workspace', NULL, NULL, NULL),
(4, '07b4f20f-875a-4868-a0de-a2d04321eea0', '1', 1, 0, 0, 1, '2024-08-15 14:41:48', 1, '2024-08-15 09:11:48', 'Support Role', 34, 'platform', NULL, NULL, NULL);

CREATE TABLE `s_space` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_slug` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_description` text COLLATE utf8mb4_unicode_ci,
  `s_definition` json DEFAULT NULL,
  `s_owner_entity_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `s_name` (`s_name`),
  UNIQUE KEY `idx_slug` (`s_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_space_membership` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_entity_id` bigint(20) NOT NULL,
  `s_role_id` bigint(20) DEFAULT NULL,
  `s_scope_level` enum('workspace','ms') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'workspace',
  `s_ms_id` bigint(20) DEFAULT NULL,
  `s_effective_from` datetime DEFAULT NULL,
  `s_effective_to` datetime DEFAULT NULL,
  `s_meta` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  KEY `idx_space_entity` (`space_id`,`s_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_sso_provider` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_provider_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_sso_configuration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `s_provider_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_client_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_issuer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_auth_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_token_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_userinfo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_jwks_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_redirect_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_scopes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_claim_map` json DEFAULT NULL,
  `s_status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `s_notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_sso_server_auth_code` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_code_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_client_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_entity_id` bigint(20) NOT NULL DEFAULT '0',
  `s_redirect_uri` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_sub` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_nonce` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_scope` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_code_challenge` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_integration_level` enum('verify_only','full_integration') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'verify_only',
  `s_expires_at` datetime NOT NULL,
  `s_consumed_at` datetime DEFAULT NULL,
  `s_client_ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `uniq_code_hash` (`s_code_hash`),
  KEY `idx_client_live` (`s_client_id`,`livestatus`),
  KEY `idx_entity_live` (`s_entity_id`,`livestatus`),
  KEY `idx_expires_at` (`s_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_sso_server_access_token` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_client_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_entity_id` bigint(20) NOT NULL DEFAULT '0',
  `s_sub` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_scope` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_integration_level` enum('verify_only','full_integration') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'verify_only',
  `s_expires_at` datetime NOT NULL,
  `s_revoked_at` datetime DEFAULT NULL,
  `s_client_ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `uniq_token_hash` (`s_token_hash`),
  KEY `idx_client_live` (`s_client_id`,`livestatus`),
  KEY `idx_entity_live` (`s_entity_id`,`livestatus`),
  KEY `idx_expires_at` (`s_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_vendor` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_handle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_summary` text COLLATE utf8mb4_unicode_ci,
  `s_category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_doc_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_source_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_install_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_version_installed` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_version_available` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_usage_notes` mediumtext COLLATE utf8mb4_unicode_ci,
  `s_install_notes` text COLLATE utf8mb4_unicode_ci,
  `s_last_scan` datetime DEFAULT NULL,
  `s_service_type_id` enum('1','2','3','4') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '1 = App, 2 = MS, 3 = Lib, 4 = Integration',
  `s_mp_uid` bigint(20) DEFAULT '0',
  `s_dependencies` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_client_tier` enum('Y','N') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_server_tier` enum('Y','N') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_version_latest` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_version_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `s_db_table` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_data_record_id` bigint(20) DEFAULT NULL,
  `s_data_record_dump` longblob,
  `s_version_number` int(11) DEFAULT NULL,
  `s_modified_by` bigint(20) DEFAULT NULL,
  `s_modified_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_wf_action` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` timestamp NULL DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_mscontroller_id` bigint(20) DEFAULT NULL,
  `s_wf_state_id` bigint(20) DEFAULT NULL,
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_role_id` bigint(20) DEFAULT NULL,
  `s_invoking_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_next_wf_state_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_wf_state` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` timestamp NULL DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_mscontroller_id` bigint(20) DEFAULT NULL,
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_flow_order` int(20) DEFAULT NULL,
  `s_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_nav` (
  `id` bigint(20) NOT NULL,
  `s_parent_id` bigint(20) NOT NULL DEFAULT '0',
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_navset_id` bigint(20) NOT NULL,
  `s_parent_nav_id` bigint(20) DEFAULT NULL,
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_href` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_icon` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_target` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '_self',
  `s_badge` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_condition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_device` enum('all','desktop','mobile') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `s_meta` json DEFAULT NULL,
  `s_sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `s_nav`
--
ALTER TABLE `s_nav`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nav_navset_idx` (`s_navset_id`),
  ADD KEY `nav_parent_idx` (`s_parent_nav_id`),
  ADD KEY `idx_nav_parent` (`s_parent_id`);
--
-- AUTO_INCREMENT for table `s_nav`
--
ALTER TABLE `s_nav`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

CREATE TABLE `s_navset` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_description` text COLLATE utf8mb4_unicode_ci,
  `s_sort_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_api_endpoint` (
  `id` bigint(20) NOT NULL,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '1',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `s_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_type` enum('system_table','system_service','utility','vendor','ai') COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_target` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `s_description` text COLLATE utf8mb4_unicode_ci,
  `s_access_role_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_rate_limit` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_telemetry_event` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `component_type` varchar(32) NOT NULL COMMENT 'ms|route|controller|job|vendor|custom',
  `component_ref` varchar(255) NOT NULL COMMENT 'e.g., ms name or route uid',
  `severity` enum('info','warning','error') NOT NULL DEFAULT 'info',
  `status_code` int DEFAULT NULL,
  `duration_ms` int DEFAULT NULL,
  `message` varchar(512) DEFAULT NULL,
  `context_json` json DEFAULT NULL,
  `user_id` bigint DEFAULT NULL,
  `space_id` bigint DEFAULT NULL,
  `correlation_id` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_component` (`component_type`,`component_ref`),
  KEY `idx_created` (`created_at`),
  KEY `idx_severity` (`severity`),
  KEY `idx_correlation` (`correlation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollups (per component per day/hour)
CREATE TABLE `s_telemetry_rollup` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `period_start` datetime NOT NULL,
  `period_granularity` enum('hour','day') NOT NULL DEFAULT 'hour',
  `component_type` varchar(32) NOT NULL,
  `component_ref` varchar(255) NOT NULL,
  `requests` int NOT NULL DEFAULT 0,
  `errors` int NOT NULL DEFAULT 0,
  `avg_duration_ms` int DEFAULT NULL,
  `p95_duration_ms` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_period_component` (`period_start`,`period_granularity`,`component_type`,`component_ref`),
  KEY `idx_component` (`component_type`,`component_ref`),
  KEY `idx_period` (`period_start`,`period_granularity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Telemetry configuration/settings
CREATE TABLE `s_telemetry_config` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `enabled` enum('Y','N') NOT NULL DEFAULT 'Y',
  `sampling_rate` int NOT NULL DEFAULT 100 COMMENT 'percentage 0-100',
  `retention_days` int NOT NULL DEFAULT 30,
  `collect_requests` enum('Y','N') NOT NULL DEFAULT 'Y',
  `collect_errors` enum('Y','N') NOT NULL DEFAULT 'Y',
  `collect_jobs` enum('Y','N') NOT NULL DEFAULT 'Y',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Telemetry API tokens (for external access)
CREATE TABLE `s_telemetry_token` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) DEFAULT NULL,
  `token_hash` char(64) NOT NULL COMMENT 'SHA-256 of the token',
  `scopes` varchar(255) NOT NULL COMMENT 'csv: events,rollups,stats',
  `expires_at` datetime DEFAULT NULL,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_token_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_dotphrase` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '1' COMMENT '0=inactive,1=active,2=archived,3=suspended',
  `versioncode` int(11) DEFAULT 1,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `s_owner_id` bigint(20) DEFAULT NULL,
  `s_phrase` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_scope` enum('platform','workspace','app','member_org') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `s_is_public` enum('Y','N') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `s_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_tags` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `u_phrase_scope_space_owner` (`s_phrase`,`s_scope`,`space_id`,`s_owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_dotphrase_usage` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '1' COMMENT '0=inactive,1=active,2=archived,3=suspended',
  `versioncode` int(11) DEFAULT 1,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `dotphrase_id` bigint(20) NOT NULL,
  `entity_id` bigint(20) DEFAULT NULL,
  `s_context` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  KEY `idx_dotphrase` (`dotphrase_id`),
  KEY `idx_entity` (`entity_id`),
  KEY `idx_space` (`space_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test plan definitions
CREATE TABLE s_test_plan (
  id BIGINT(20) NOT NULL AUTO_INCREMENT,
  uid CHAR(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  livestatus ENUM('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  versioncode INT(11) DEFAULT NULL,
  wf_status INT(11) NOT NULL DEFAULT '0',
  space_id BIGINT(20) NOT NULL DEFAULT '0',
  createdby BIGINT(20) DEFAULT NULL,
  createstamp TIMESTAMP NULL DEFAULT NULL,
  updatedby BIGINT(20) DEFAULT NULL,
  updatestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  s_name VARCHAR(255) NOT NULL,
  s_description TEXT,
  s_scope ENUM('microservice','route','api') NOT NULL,
  s_ms_id BIGINT(20) DEFAULT NULL,
  s_route_id BIGINT(20) DEFAULT NULL,
  s_apiendpoint_id BIGINT(20) DEFAULT NULL,
  s_auto ENUM('Y','N') DEFAULT 'N',
  PRIMARY KEY (id),
  UNIQUE KEY uq_plan_uid (uid),
  KEY idx_scope (s_scope, s_ms_id, s_route_id, s_apiendpoint_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual test items under a plan
CREATE TABLE s_test_item (
  id BIGINT(20) NOT NULL AUTO_INCREMENT,
  uid CHAR(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  livestatus ENUM('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0',
  versioncode INT(11) DEFAULT NULL,
  wf_status INT(11) NOT NULL DEFAULT '0',
  space_id BIGINT(20) NOT NULL DEFAULT '0',
  createdby BIGINT(20) DEFAULT NULL,
  createstamp TIMESTAMP NULL DEFAULT NULL,
  updatedby BIGINT(20) DEFAULT NULL,
  updatestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  s_test_plan_id BIGINT(20) NOT NULL,
  s_name VARCHAR(255) NOT NULL,
  s_description TEXT,
  s_type ENUM('auto','manual') DEFAULT 'manual',
  s_url TEXT,
  s_method VARCHAR(16) DEFAULT 'GET',
  s_payload TEXT,
  s_expected TEXT,
  s_order INT DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_item_uid (uid),
  KEY idx_plan (s_test_plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test run sessions (per plan)
CREATE TABLE s_test_run (
  id BIGINT(20) NOT NULL AUTO_INCREMENT,
  uid CHAR(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  livestatus ENUM('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0',
  versioncode INT(11) DEFAULT NULL,
  wf_status INT(11) NOT NULL DEFAULT '0',
  space_id BIGINT(20) NOT NULL DEFAULT '0',
  createdby BIGINT(20) DEFAULT NULL,
  createstamp TIMESTAMP NULL DEFAULT NULL,
  updatedby BIGINT(20) DEFAULT NULL,
  updatestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  s_test_plan_id BIGINT(20) NOT NULL,
  s_status ENUM('pending','running','completed','failed') DEFAULT 'pending',
  s_notes TEXT,
  s_started_at DATETIME NULL,
  s_completed_at DATETIME NULL,
  runby BIGINT(20) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_run_uid (uid),
  KEY idx_plan_status (s_test_plan_id, s_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Results per item per run
CREATE TABLE s_test_result (
  id BIGINT(20) NOT NULL AUTO_INCREMENT,
  uid CHAR(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  livestatus ENUM('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0',
  versioncode INT(11) DEFAULT NULL,
  wf_status INT(11) NOT NULL DEFAULT '0',
  space_id BIGINT(20) NOT NULL DEFAULT '0',
  createdby BIGINT(20) DEFAULT NULL,
  createstamp TIMESTAMP NULL DEFAULT NULL,
  updatedby BIGINT(20) DEFAULT NULL,
  updatestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  s_test_run_id BIGINT(20) NOT NULL,
  s_test_item_id BIGINT(20) NOT NULL,
  s_status ENUM('not_run','passed','failed','blocked') DEFAULT 'not_run',
  s_comment TEXT,
  s_duration_ms INT NULL,
  s_evidence TEXT,
  PRIMARY KEY (id),
  UNIQUE KEY uq_result_uid (uid),
  KEY idx_run_item (s_test_run_id, s_test_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE s_navset_role (
  id           BIGINT(20) NOT NULL AUTO_INCREMENT,
  uid          CHAR(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  livestatus   ENUM('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0',
  versioncode  INT(11) DEFAULT NULL,
  wf_status    INT(11) NOT NULL DEFAULT 0,
  space_id     BIGINT(20) NOT NULL DEFAULT 0,
  createdby    BIGINT(20) DEFAULT NULL,
  createstamp  TIMESTAMP NULL DEFAULT NULL,
  updatedby    BIGINT(20) DEFAULT NULL,
  updatestamp  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  s_navset_id  BIGINT(20) NOT NULL,
  s_role_id    BIGINT(20) NOT NULL,
  s_ms_id     BIGINT(20) NOT NULL DEFAULT 0, -- 0 = not app-scoped
  PRIMARY KEY (id),
  KEY (s_navset_id),
  KEY (s_role_id),
  KEY (s_ms_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `s_password_reset` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  `s_entity_id` bigint(20) NOT NULL,
  `s_token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s_expires_at` datetime NOT NULL,
  `s_used_at` datetime DEFAULT NULL,
  `s_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  KEY `idx_entity_id` (`s_entity_id`),
  KEY `idx_token_hash` (`s_token_hash`),
  KEY `idx_expires_at` (`s_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s_branch` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
