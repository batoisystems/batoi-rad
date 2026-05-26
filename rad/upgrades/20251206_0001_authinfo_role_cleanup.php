<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20251206_0001_authinfo_role_cleanup',
    'description' => 'Deprecated: s_auth_info/portal_role_id removed; no action required.',
    'run' => function (Database $db, Logger $logger, array $config = []): void {
        $logger->logSql('[authinfo cleanup] Deprecated: s_auth_info/portal_role_id removed.');
    },
];
