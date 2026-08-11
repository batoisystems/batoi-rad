<?php
$versionFile = dirname(__DIR__) . '/VERSION';
if (!defined('BATOI_RAD_VERSION') && is_file($versionFile)) {
    $version = trim((string)file_get_contents($versionFile));
    if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
        define('BATOI_RAD_VERSION', $version);
    }
}

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.cls.php';
    if (file_exists($file)) {
        require $file;
    }
});
