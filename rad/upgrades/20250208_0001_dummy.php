<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20250208_0001_dummy',
    'description' => 'Dummy upgrade that logs the current count of configuration rows.',
    'run' => function (Database $db, Logger $logger): void {
        $rows = $db->select('s_config', [], true);
        $message = 'Dummy upgrade executed. s_config row count: ' . count($rows);
        $logger->logError($message);
        echo $message . PHP_EOL;
    },
];
