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
  UNIQUE KEY `s_name` (`s_name`)
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
  KEY `idx_space_entity` (`space_id`,`s_entity_id`),
  KEY `idx_entity` (`s_entity_id`),
  KEY `idx_entity_live` (`s_entity_id`,`livestatus`)
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

-- Community Edition seed data

-- Safe Community Edition seed data.
-- This file intentionally does not create users, password hashes, API keys, provider secrets, IP allowlists, or private URLs.

INSERT INTO `s_config` (`uid`, `livestatus`, `wf_status`, `space_id`, `updatestamp`, `s_config_handle`, `s_config_value`, `s_config_origin`, `s_description`) VALUES
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'project_title', 'Batoi RAD Application', 'S', 'Application title.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'author', 'Batoi', 'S', 'Application author or organization.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'base_url', 'http://localhost', 'S', 'Base URL for the application.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'allowed_ips', '127.0.0.1,::1', 'S', 'Comma-separated admin IP allowlist.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'timezone', 'UTC', 'S', 'Default timezone.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'locale', 'en_US', 'S', 'Default locale.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'currency', 'USD', 'S', 'Default currency.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'country', 'US', 'S', 'Default country.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'session_name', 'batoi_rad_session', 'S', 'PHP session name.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'session_lifetime', '1800', 'S', 'Session lifetime in seconds.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'session_path_default', 'N', 'S', 'Use PHP default session path.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'session_domain', '', 'S', 'Session cookie domain.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'session_secure', '0', 'S', 'Require secure session cookie.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'session_httponly', '1', 'S', 'Use HTTP-only session cookie.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'display_errors', 'false', 'S', 'Display PHP errors.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'site_status', 'L', 'S', 'Site status.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'ip_access_restrict', 'N', 'S', 'Restrict RAD Admin by IP allowlist.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'site_update_pending', 'N', 'S', 'Site update pending flag.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'enable_sql_log', 'false', 'S', 'Enable SQL logging.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'encryption_method', 'AES-256-CBC', 'S', 'Encryption method.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'home_dashboard_cache_ttl', '120', 'S', 'Home dashboard cache TTL in seconds.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'rad_admin_uif_enabled', 'Y', 'S', 'Enable Batoi UIF assets in RAD Admin.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'public_theme_uif_enabled', 'Y', 'S', 'Enable Batoi UIF assets in public runtime themes.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'rad_admin_monaco_base_url', '', 'S', 'Self-hosted Monaco editor base URL for RAD Admin code editors.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'rad_admin_community_edition', 'Y', 'S', 'Hide and block held-back RAD Admin modules in Community Edition.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'dev_debug_flag', 'N', 'S', 'Show developer debug output.');

INSERT INTO `s_role` (`uid`, `livestatus`, `wf_status`, `space_id`, `updatestamp`, `s_role_name`, `s_default_route_id`, `s_scope`, `s_code`, `s_ms_id`, `s_description`) VALUES
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'Administrator', NULL, 'platform', 'system_admin', NULL, 'Default platform administrator role.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'Manager', NULL, 'workspace', 'manager', NULL, 'Default workspace manager role.'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'Member', NULL, 'workspace', 'member', NULL, 'Default workspace member role.');

INSERT INTO `s_data_field_type` (`uid`, `livestatus`, `wf_status`, `space_id`, `updatestamp`, `s_name`, `s_description`, `s_definition`) VALUES
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'TEXT_BOX', 'Single-line text box', '{"input_type":"text"}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'EMAIL', 'Email field', '{"input_type":"email","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'URL', 'URL field', '{"input_type":"url","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'PASSWORD', 'Password field', '{"input_type":"password","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'NUMBER', 'Number field', '{"input_type":"number","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'DECIMAL', 'Decimal number field', '{"input_type":"decimal","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'PHONE', 'Phone number field with country code', '{"input_type":"phone","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'PHONE_NO_CC', 'Phone number field without country code', '{"input_type":"phone_no_cc","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'MONEY', 'Money field with currency code', '{"input_type":"money","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'MONEY_NO_CC', 'Money field without currency code', '{"input_type":"money_no_cc","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'PERCENTAGE', 'Percentage field', '{"input_type":"percentage","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'SINGLE_LINE_ENCRYPTED_TEXT', 'Single-line encrypted text box', '{"input_type":"single_line_encrypted_text","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'MULTI_LINE_ENCRYPTED_TEXT', 'Multi-line encrypted text box', '{"input_type":"multi_line_encrypted_text","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'AUTO_SUGGEST', 'Auto-suggest text box', '{"input_type":"auto_suggest","source":"remote_or_local_data_source"}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'TEXT_AREA', 'Multi-line text box', '{"input_type":"textarea"}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'RICH_TEXT', 'Rich text box', '{"input_type":"rich_text"}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'DROPDOWN', 'Dropdown field', '{"input_type":"enum","options":["Option 1","Option 2"]}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'SINGLE_CHECKBOX', 'Single checkbox field', '{"input_type":"single_checkbox"}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'MULTI_CHECKBOX', 'Multi-checkbox field', '{"input_type":"multi_checkbox","options":["Option 1","Option 2","Option 3"]}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'MULTI_SELECT', 'Multi-select field', '{"input_type":"multi_select","options":["Option 1","Option 2","Option 3"]}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'RADIO_BUTTON', 'Radio button field', '{"input_type":"radio","options":["Option 1","Option 2"]}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'RADIO_YES_NO', 'Yes/no radio field', '{"input_type":"radio","options":["Yes","No"]}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'DATE_PICKER', 'Date picker field', '{"input_type":"date"}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'TIME_PICKER', 'Time picker field', '{"input_type":"time_picker","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'DATE_RANGE', 'Date range field', '{"input_type":"date_range_picker","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'COLOR_PICKER', 'Color picker field', '{"input_type":"color_picker","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'CREDIT_CARD_FIELD', 'Credit card field', '{"input_type":"credit_card_field","options":{"include_expiry":true,"include_cvv":true}}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'FILE_UPLOAD', 'File upload field', '{"input_type":"file","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'MULTI_FILE_UPLOAD', 'Multiple file upload field', '{"input_type":"multi_file","options":null}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'FOREIGN_KEY', 'Foreign key field', '{"input_type":"foreign_key","related_table":"related_table_here","related_field":"related_field_here"}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'CSV_FOREIGN_KEYS', 'Comma-separated foreign keys field', '{"input_type":"csv_foreign_keys","related_table":"related_table_here","related_field":"related_field_here"}'),
(UUID(), '1', '0', '0', CURRENT_TIMESTAMP, 'CUSTOM_TYPE', 'Custom field type', '{"input_type":"custom","options":{}}');
