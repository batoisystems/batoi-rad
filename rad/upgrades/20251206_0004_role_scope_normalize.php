<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20251206_0004_role_scope_normalize',
    'description' => 'Normalize s_role.s_scope (platform/workspace/app/member_org) and clear legacy s_saas usage.',
    'run' => function (Database $db, Logger $logger): void {
        // Normalize invalid/null scopes to platform
        $db->query("
            UPDATE s_role
            SET s_scope = 'platform'
            WHERE s_scope IS NULL OR s_scope = '' OR s_scope NOT IN ('platform','workspace','app','member_org')
        ");

        // Optional: mirror legacy s_saas into scope if scope is still platform and s_saas says SaaS
        $db->query("
            UPDATE s_role
            SET s_scope = 'workspace'
            WHERE s_scope = 'platform' AND UPPER(COALESCE(s_saas,'N')) = 'Y'
        ");

        $logger->logSql('[role scope normalize] Scopes normalized to platform/workspace/app/member_org.');
    },
];
