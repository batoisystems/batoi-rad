<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20250209_0002_create_mytestash',
    'description' => 'Create the a_mytestash demo table for upgrade testing.',
    'run' => function (Database $db, Logger $logger): void {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `a_mytestash` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `label` varchar(255) NOT NULL,
    `notes` text NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $db->query($sql);
        $logger->logError('Upgrade created/ensured table a_mytestash.');
    },
    'rollback' => function (Database $db, Logger $logger, array $config = []): void {
        $db->query('DROP TABLE IF EXISTS `a_mytestash`');
        $logger->logError('Rolled back and dropped table a_mytestash.');
    },
];
