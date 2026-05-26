#!/usr/bin/env php
<?php
declare(strict_types=1);

use Core\Sys\AutoLoader;
use Core\Sys\ConfigLoader;
use Core\Sys\Database;
use Core\Sys\Logger;
use Core\Sys\ErrorHandler;

$rootDir = dirname(__DIR__);
$projectRoot = dirname($rootDir);

require_once $projectRoot . '/rad/core/sys/AutoLoader.cls.php';

$autoloader = new AutoLoader();
$autoloader->addDirectory($projectRoot . '/rad/core/app');
$autoloader->addDirectory($projectRoot . '/rad/core/sys');
$autoloader->addDirectory($projectRoot . '/rad/admin/classes');
$autoloader->addDirectory($projectRoot . '/rad');
$autoloader->register();

try {
    $configLoader = new ConfigLoader($projectRoot . '/rad/config/sys.inc.php');
    $configInit = $configLoader->getInitAll();
    $logger = new Logger($configInit['dir']['log']);
    $errorHandler = new ErrorHandler($logger);
    $db = new Database($configInit['database'], $errorHandler);
    $configInit = [];
    $dbConfig = $configLoader->fetchDbConfig($db);
    $config = $configLoader->getAll($dbConfig);

    $upgrader = new \Core\Sys\UpgradeController($config, $db, $logger, $errorHandler);
    $upgrader->handleCli($argv);
} catch (\Throwable $e) {
    fwrite(STDERR, "Upgrade failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
