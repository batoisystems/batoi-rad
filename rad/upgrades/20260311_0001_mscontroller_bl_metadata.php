<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20260311_0001_mscontroller_bl_metadata',
    'description' => 'Extend s_mscontroller.s_name to 25 chars and add BL source file/class metadata.',
    'run' => function (Database $db, Logger $logger): void {
        $db->query("
            ALTER TABLE s_mscontroller
                MODIFY s_name VARCHAR(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                ADD COLUMN s_source_file VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER s_name,
                ADD COLUMN s_class_name VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER s_source_file
        ");

        $logger->logSql('[mscontroller bl metadata] Extended s_name to 25 and added s_source_file/s_class_name.');
    },
];
