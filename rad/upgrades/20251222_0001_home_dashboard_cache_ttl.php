<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20251222_0001_home_dashboard_cache_ttl',
    'description' => 'Add system config home_dashboard_cache_ttl for RAD Admin home caching.',
    'run' => function (Database $db, Logger $logger): void {
        $rows = $db->select('s_config', ['s_config_handle' => 'home_dashboard_cache_ttl'], true);
        if (!empty($rows)) {
            $logger->logSql('[home dashboard cache ttl] config already exists.');
            return;
        }
        $db->insert('s_config', [
            's_config_handle' => 'home_dashboard_cache_ttl',
            's_config_value' => '120',
            's_config_origin' => 'S',
            's_description' => 'Home dashboard cache TTL in seconds.',
        ], [
            'space_id' => 0,
            'livestatus' => '1',
        ]);
        $logger->logSql('[home dashboard cache ttl] config inserted.');
    },
];
