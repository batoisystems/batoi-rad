<?php
return [
    'id' => '20240826_portal_role_backfill',
    'description' => 'Deprecated: portal_role_id removed; no action required.',
    'run' => function ($db, $logger, $config) {
        echo "Portal role backfill deprecated: s_auth_info/portal_role_id removed." . PHP_EOL;
    },
];
