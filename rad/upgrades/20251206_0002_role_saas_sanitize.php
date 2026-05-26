<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20251206_0002_role_saas_sanitize',
    'description' => 'Normalize s_role.s_saas to Y/N and clear invalid values.',
    'run' => function (Database $db, Logger $logger): void {
        $rows = $db->query("SELECT id, s_role_name, s_saas FROM s_role");
        $fixed = 0;
        foreach ($rows as $row) {
            $val = strtoupper($row['s_saas'] ?? '');
            if ($val !== 'Y' && $val !== 'N') {
                $db->update('s_role', ['s_saas' => 'N'], ['id' => $row['id']]);
                $fixed++;
            }
        }
        $logger->logSql("[role saas sanitize] Normalized {$fixed} role(s) with invalid s_saas.");
    },
];
