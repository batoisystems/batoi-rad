CREATE TABLE `a_document_submissions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `space_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `a_user_id` bigint(20) NOT NULL,
  `a_document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `a_document_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `a_issue_date` date DEFAULT NULL,
  `a_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `a_kyc_verifications` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `versioncode` int(11) DEFAULT NULL,
  `wf_status` int(11) NOT NULL DEFAULT '0',
  `tenant_id` bigint(20) NOT NULL DEFAULT '0',
  `createdby` bigint(20) DEFAULT NULL,
  `createstamp` datetime DEFAULT NULL,
  `updatedby` bigint(20) DEFAULT NULL,
  `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `a_document_submission_id` bigint(20) NOT NULL,
  `a_verification_status` enum('0','1','2') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = pending, 1 = verified, 2 = rejected',
  `a_blockchain_tx_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `a_verifiedby` bigint(20) DEFAULT NULL,
  `a_verifystamp` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

