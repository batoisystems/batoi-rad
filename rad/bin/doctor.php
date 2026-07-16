#!/usr/bin/env php
<?php
declare(strict_types=1);

$radDir = dirname(__DIR__);
$checks = [];
$check = static function (string $name, bool $passed, string $detail = '') use (&$checks): void {
    $checks[] = [$name, $passed, $detail];
};

$check('PHP >= 8.3', PHP_VERSION_ID >= 80300, PHP_VERSION);
foreach (['curl', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'zip'] as $extension) {
    $check('PHP extension: ' . $extension, extension_loaded($extension));
}
$check('Composer autoloader', is_file($radDir . '/vendor/autoload.php'));
$check('Batoi AIF distribution', is_file($radDir . '/vendor/batoi/aif/autoload.php'));
$check('Batoi UIF distribution', is_file(dirname($radDir) . '/public_html/assets/uif/uif.esm.js'));

foreach (['data', 'log', 'log/session'] as $directory) {
    $path = $radDir . '/' . $directory;
    $check('Writable: rad/' . $directory, is_dir($path) && is_writable($path));
}

$configPath = $radDir . '/config/sys.inc.php';
$check('Local configuration', is_file($configPath));
if (is_file($configPath)) {
    try {
        $config = require $configPath;
        if (!is_array($config) || !is_array($config['database'] ?? null)) {
            throw new RuntimeException('database settings are missing');
        }
        $database = $config['database'];
        $socket = (string)($database['socket'] ?? '');
        $port = (string)($database['port'] ?? '');
        $dsn = 'mysql:' . ($socket !== ''
            ? 'unix_socket=' . $socket . ';'
            : 'host=' . ($database['host'] ?? '') . ($port !== '' ? ';port=' . $port : '') . ';')
            . 'dbname=' . ($database['name'] ?? '') . ';charset=utf8mb4';
        $pdoOptions = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (!empty($database['ssl_ca'])) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $database['ssl_ca'];
            $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
        $pdo = new PDO(
            $dsn,
            (string)($database['user'] ?? ''),
            (string)($database['password'] ?? ''),
            $pdoOptions
        );
        $check('Database connection', true);
        foreach (['s_config', 's_entity', 's_entity_session', 's_auth_attempt', 's_migration'] as $table) {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $statement->execute([$table]);
            $check('Database table: ' . $table, (int)$statement->fetchColumn() === 1);
        }
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM s_entity WHERE id = 1')->fetchColumn();
        $check('System administrator', $adminCount === 1);
        $failedMigrations = (int)$pdo->query("SELECT COUNT(*) FROM s_migration WHERE status = 'failed'")->fetchColumn();
        $check('No failed migrations', $failedMigrations === 0, $failedMigrations > 0 ? $failedMigrations . ' failed' : '');
    } catch (Throwable $exception) {
        $check('Database connection', false, $exception->getMessage());
    }
}

$failed = 0;
foreach ($checks as [$name, $passed, $detail]) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name;
    if ($detail !== '') {
        echo ' (' . $detail . ')';
    }
    echo PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
echo PHP_EOL . ($failed === 0 ? 'RAD is ready.' : $failed . ' check(s) failed.') . PHP_EOL;
exit($failed === 0 ? 0 : 1);
