<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20251206_0003_ms_scope_backfill',
    'description' => 'Backfill s_ms.s_scope from legacy s_is_saas and s_access_scope.',
    'run' => function (Database $db, Logger $logger): void {
        $rows = $db->query("SELECT id, s_access_scope, s_is_saas, s_scope FROM s_ms");
        $updated = 0;
        foreach ($rows as $row) {
            $scope = $row['s_scope'] ?? '';
            if ($scope !== '' && $scope !== null) {
                continue;
            }
            $accessScope = $row['s_access_scope'] ?? 'private';
            $isSaas = strtoupper($row['s_is_saas'] ?? 'N') === 'Y';
            $newScope = 'platform';
            if ($accessScope === 'public') {
                $newScope = 'global';
            } elseif ($isSaas) {
                $newScope = 'workspace';
            }
            $db->update('s_ms', ['s_scope' => $newScope], ['id' => $row['id']]);
            $updated++;
        }
        $logger->logSql("[ms scope backfill] Updated {$updated} microservicelets with inferred scopes.");
    },
];
