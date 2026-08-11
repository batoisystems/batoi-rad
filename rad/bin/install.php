#!/usr/bin/env php
<?php
declare(strict_types=1);

const EXIT_USAGE = 2;

$radDir = dirname(__DIR__);
$projectRoot = dirname($radDir);
$options = getopt('', [
    'help',
    'check',
    'dry-run',
    'db-host:',
    'db-port::',
    'db-socket::',
    'db-ssl-ca::',
    'db-name:',
    'db-user:',
    'db-password::',
    'db-password-file::',
    'base-url:',
    'admin-name::',
    'admin-username::',
    'admin-email::',
    'admin-password::',
    'admin-password-file::',
    'non-interactive',
    'skip-composer',
]);
if (isset($options['help'])) {
    printHelp();
    exit(0);
}
$nonInteractive = isset($options['non-interactive']);

try {
    if (isset($options['check'])) {
        preflight($radDir);
        echo "Installer platform preflight passed.\n";
        exit(0);
    }
    $existingConfig = loadExistingConfig($radDir . '/config/sys.inc.php');
    $database = is_array($existingConfig['database'] ?? null) ? $existingConfig['database'] : [];

    $dbHost = requiredValue('Database host', 'db-host', 'RAD_DB_HOST', $options, $database['host'] ?? '127.0.0.1', $nonInteractive);
    $dbPort = optionalValue('Database port', 'db-port', 'RAD_DB_PORT', $options, (string)($database['port'] ?? ''), $nonInteractive);
    $dbSocket = optionalValue('Database socket', 'db-socket', 'RAD_DB_SOCKET', $options, (string)($database['socket'] ?? ''), $nonInteractive);
    $dbSslCa = optionalValue('Database TLS CA file', 'db-ssl-ca', 'RAD_DB_SSL_CA', $options, (string)($database['ssl_ca'] ?? ''), $nonInteractive);
    $dbName = requiredValue('Database name', 'db-name', 'RAD_DB_NAME', $options, $database['name'] ?? '', $nonInteractive);
    $dbUser = requiredValue('Database user', 'db-user', 'RAD_DB_USER', $options, $database['user'] ?? '', $nonInteractive);
    $dbPassword = secretValue('Database password', 'db-password', 'db-password-file', 'RAD_DB_PASSWORD', 'RAD_DB_PASSWORD_FILE', $options, $database['password'] ?? '', $nonInteractive);
    $baseUrl = rtrim(requiredValue('Application base URL', 'base-url', 'RAD_BASE_URL', $options, 'http://localhost', $nonInteractive), '/');
    $adminName = requiredValue('Administrator display name', 'admin-name', 'RAD_ADMIN_NAME', $options, 'Administrator', $nonInteractive);
    $adminUsername = requiredValue('Administrator username', 'admin-username', 'RAD_ADMIN_USERNAME', $options, 'admin', $nonInteractive);
    $adminEmail = optionalValue('Administrator email', 'admin-email', 'RAD_ADMIN_EMAIL', $options, '', $nonInteractive);
    $adminPassword = secretValue('Administrator password', 'admin-password', 'admin-password-file', 'RAD_ADMIN_PASSWORD', 'RAD_ADMIN_PASSWORD_FILE', $options, '', $nonInteractive);
    validateConnectionInput($dbHost, $dbPort, $dbSocket, $dbSslCa, $dbName);
    validateBaseUrl($baseUrl);
    validateAdminPassword($adminPassword);

    preflight($radDir);
    if (!isset($options['dry-run'])) {
        createRuntimeDirectories($radDir);
        if (!isset($options['skip-composer'])) {
            installComposerDependencies($radDir);
        }
    }

    $pdo = connectDatabase($dbHost, $dbPort, $dbSocket, $dbSslCa, $dbName, $dbUser, $dbPassword);
    if (isset($options['dry-run'])) {
        inspectDatabaseState($pdo);
        echo "Installer dry run passed; no database or configuration changes were made.\n";
        exit(0);
    }
    $freshInstall = false;
    try {
        $freshInstall = initializeDatabase($pdo, $radDir . '/admin/install/schema.sql', $radDir . '/admin/install/seed.community.sql');
        if ($freshInstall) {
            baselineBundledMigrations($pdo, $radDir . '/upgrades', releaseVersion($projectRoot));
        }
        updateBaseUrl($pdo, $baseUrl);
        provisionAdministrator($pdo, $adminName, $adminUsername, $adminEmail, $adminPassword);
    } catch (Throwable $exception) {
        if ($freshInstall) {
            resetFreshDatabase($pdo);
        }
        throw $exception;
    }
    writeConfig($radDir . '/config/sys.inc.php', [
        'host' => $dbHost,
        'port' => $dbPort,
        'socket' => $dbSocket,
        'ssl_ca' => $dbSslCa,
        'name' => $dbName,
        'user' => $dbUser,
        'password' => $dbPassword,
        'enable_sql_log' => false,
    ]);
    if (!$freshInstall) {
        runDatabaseUpgrades($radDir);
    }

    echo PHP_EOL . 'Batoi RAD installation completed.' . PHP_EOL;
    echo 'Site: ' . $baseUrl . PHP_EOL;
    echo 'RAD Admin: ' . $baseUrl . '/rad-admin' . PHP_EOL;
    echo 'Administrator username: ' . $adminUsername . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Installation failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

/** @return array<string, mixed> */
function loadExistingConfig(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $config = require $path;
    return is_array($config) ? $config : [];
}

/** @param array<string, mixed> $options */
function requiredValue(string $label, string $option, string $environment, array $options, string $default, bool $nonInteractive): string
{
    $value = optionOrEnvironment($option, $environment, $options, $default);
    if (!$nonInteractive) {
        $prompt = $label . ($value !== '' ? ' [' . $value . ']' : '') . ': ';
        $input = trim((string) readline($prompt));
        if ($input !== '') {
            $value = $input;
        }
    }
    $value = trim($value);
    if ($value === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    return $value;
}

/** @param array<string, mixed> $options */
function optionalValue(string $label, string $option, string $environment, array $options, string $default, bool $nonInteractive): string
{
    $value = optionOrEnvironment($option, $environment, $options, $default);
    if (!$nonInteractive) {
        $prompt = $label . ($value !== '' ? ' [' . $value . ']' : '') . ': ';
        $input = trim((string) readline($prompt));
        if ($input !== '') {
            $value = $input;
        }
    }
    return trim($value);
}

/** @param array<string, mixed> $options */
function secretValue(string $label, string $option, string $fileOption, string $environment, string $fileEnvironment, array $options, string $default, bool $nonInteractive): string
{
    $value = optionOrEnvironment($option, $environment, $options, '');
    if ($value === '') {
        $secretFile = optionOrEnvironment($fileOption, $fileEnvironment, $options, '');
        $value = $secretFile !== '' ? readSecretFile($secretFile) : $default;
    }
    if (!$nonInteractive && ($value === '' || $option === 'admin-password')) {
        $input = hiddenPrompt($label . ($value !== '' ? ' [press Enter to keep existing]' : '') . ': ');
        if ($input !== '') {
            $value = $input;
        }
    }
    return $value;
}

function readSecretFile(string $path): string
{
    if (!is_file($path) || !is_readable($path)) {
        throw new InvalidArgumentException('Secret file is not readable: ' . $path);
    }
    $value = file_get_contents($path);
    if ($value === false || strlen($value) > 65536) {
        throw new InvalidArgumentException('Secret file is invalid: ' . $path);
    }
    return rtrim($value, "\r\n");
}

/** @param array<string, mixed> $options */
function optionOrEnvironment(string $option, string $environment, array $options, string $default): string
{
    if (array_key_exists($option, $options) && $options[$option] !== false) {
        return (string) $options[$option];
    }
    $environmentValue = getenv($environment);
    return $environmentValue !== false ? (string) $environmentValue : $default;
}

function hiddenPrompt(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $sttyAvailable = function_exists('shell_exec') && trim((string) shell_exec('command -v stty 2>/dev/null')) !== '';
    if ($sttyAvailable) {
        shell_exec('stty -echo');
    }
    $value = trim((string) fgets(STDIN));
    if ($sttyAvailable) {
        shell_exec('stty echo');
    }
    fwrite(STDOUT, PHP_EOL);
    return $value;
}

function validateAdminPassword(string $password): void
{
    if (strlen($password) < 12) {
        throw new InvalidArgumentException('Administrator password must be at least 12 characters.');
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        throw new InvalidArgumentException('Administrator password must include upper-case, lower-case, and numeric characters.');
    }
}

function validateConnectionInput(string $host, string $port, string $socket, string $sslCa, string $name): void
{
    if (str_contains($host, ';') || preg_match('/[\x00-\x1f]/', $host)) {
        throw new InvalidArgumentException('Database host is invalid.');
    }
    if ($port !== '' && (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535)) {
        throw new InvalidArgumentException('Database port must be between 1 and 65535.');
    }
    if ($socket !== '' && (!str_starts_with($socket, '/') || str_contains($socket, "\0"))) {
        throw new InvalidArgumentException('Database socket must be an absolute path.');
    }
    if ($sslCa !== '' && (!is_file($sslCa) || !is_readable($sslCa))) {
        throw new InvalidArgumentException('Database TLS CA file is not readable.');
    }
    if (!preg_match('/^[A-Za-z0-9_$]+$/', $name)) {
        throw new InvalidArgumentException('Database name may contain only letters, numbers, underscore, and dollar sign.');
    }
}

function validateBaseUrl(string $baseUrl): void
{
    $parts = parse_url($baseUrl);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        throw new InvalidArgumentException('Application base URL must be an http(s) origin or path without credentials, query, or fragment.');
    }
}

function preflight(string $radDir): void
{
    if (PHP_VERSION_ID < 80300) {
        throw new RuntimeException('Batoi RAD requires PHP 8.3 or newer.');
    }
    foreach (['curl', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'zip'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException('Required PHP extension is missing: ' . $extension);
        }
    }
    foreach ([$radDir . '/admin/install/schema.sql', $radDir . '/admin/install/seed.community.sql'] as $file) {
        if (!is_readable($file)) {
            throw new RuntimeException('Required installation file is not readable: ' . $file);
        }
    }
    if (!is_writable($radDir)) {
        throw new RuntimeException('RAD directory is not writable: ' . $radDir);
    }
}

function createRuntimeDirectories(string $radDir): void
{
    $directories = [
        'config', 'data/cache', 'data/health/workspace_audit', 'data/queue/jobs',
        'data/temp/sqlconsole', 'data/trash/ms', 'data/trash/stray', 'data/uitpl',
        'data/upgrade', 'data/uploads', 'data/versions/controller',
        'data/versions/queuejob', 'data/versions/route', 'data/versions/theme',
        'log/session', 'ms',
    ];
    foreach ($directories as $directory) {
        $path = $radDir . '/' . $directory;
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }
}

/** @param array<string, mixed> $database */
function writeConfig(string $path, array $database): void
{
    $config = [
        'database' => $database,
        'auth' => ['sso_role' => 'disabled'],
    ];
    $contents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
    if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write temporary configuration: ' . $temporary);
    }
    @chmod($temporary, 0600);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to publish configuration: ' . $path);
    }
}

function installComposerDependencies(string $radDir): void
{
    if (is_file($radDir . '/vendor/autoload.php')) {
        echo 'Composer dependencies already installed.' . PHP_EOL;
        return;
    }
    $composer = getenv('COMPOSER_BINARY') ?: 'composer';
    $command = 'cd ' . escapeshellarg($radDir) . ' && ' . escapeshellcmd($composer) . ' install --no-interaction --prefer-dist --no-dev';
    passthru($command, $status);
    if ($status !== 0 || !is_file($radDir . '/vendor/autoload.php')) {
        throw new RuntimeException('Composer dependency installation failed.');
    }
}

function runDatabaseUpgrades(string $radDir): void
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($radDir . '/bin/upgrade.php');
    passthru($command, $status);
    if ($status !== 0) {
        throw new RuntimeException('Database upgrade failed. Configuration was retained so the upgrade can be retried.');
    }
}

function connectDatabase(string $host, string $port, string $socket, string $sslCa, string $name, string $user, string $password): PDO
{
    $dsn = 'mysql:' . ($socket !== '' ? 'unix_socket=' . $socket . ';' : 'host=' . $host . ($port !== '' ? ';port=' . $port : '') . ';')
        . 'dbname=' . $name . ';charset=utf8mb4';
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ];
    if ($sslCa !== '') {
        $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    return new PDO($dsn, $user, $password, $pdoOptions);
}

function inspectDatabaseState(PDO $pdo): void
{
    $tableCount = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    if ($tableCount === 0) {
        return;
    }
    foreach (['s_config', 's_entity', 's_role'] as $table) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $statement->execute([$table]);
        if ((int)$statement->fetchColumn() !== 1) {
            throw new RuntimeException('Database is partially initialized; missing table ' . $table . '.');
        }
    }
}

function printHelp(): void
{
    echo <<<'HELP'
Batoi RAD installer

Usage:
  php rad/bin/install.php [options]
  php rad/bin/install.php --check

Options:
  --check                         Run platform/filesystem preflight only
  --dry-run                       Validate inputs and database without mutation
  --non-interactive               Never prompt for missing values
  --skip-composer                 Require already-installed Composer packages
  --db-host=HOST                  MySQL hostname (RAD_DB_HOST)
  --db-port=PORT                  MySQL TCP port (RAD_DB_PORT)
  --db-socket=PATH                MySQL Unix socket (RAD_DB_SOCKET)
  --db-ssl-ca=PATH                TLS CA certificate (RAD_DB_SSL_CA)
  --db-name=NAME                  Database name (RAD_DB_NAME)
  --db-user=USER                  Database user (RAD_DB_USER)
  --db-password=SECRET            Database password (RAD_DB_PASSWORD)
  --db-password-file=PATH         Read database password from file (RAD_DB_PASSWORD_FILE)
  --base-url=URL                  Site base URL (RAD_BASE_URL)
  --admin-name=NAME               Administrator display name (RAD_ADMIN_NAME)
  --admin-username=USER           Administrator username (RAD_ADMIN_USERNAME)
  --admin-email=EMAIL             Administrator email (RAD_ADMIN_EMAIL)
  --admin-password=SECRET         Administrator password (RAD_ADMIN_PASSWORD)
  --admin-password-file=PATH      Read admin password from file (RAD_ADMIN_PASSWORD_FILE)
  --help                          Show this help
HELP;
    echo PHP_EOL;
}

function initializeDatabase(PDO $pdo, string $schemaPath, string $seedPath): bool
{
    $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    if ($tableCount === 0) {
        try {
            executeSqlFile($pdo, $schemaPath);
            executeSqlFile($pdo, $seedPath);
        } catch (Throwable $exception) {
            resetFreshDatabase($pdo);
            throw $exception;
        }
        echo 'Database schema and Community seed installed.' . PHP_EOL;
        return true;
    }

    $requiredTables = ['s_config', 's_entity', 's_role'];
    foreach ($requiredTables as $table) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $statement->execute([$table]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Database is partially initialized; missing table ' . $table . '. Use an empty database.');
        }
    }
    echo 'Existing RAD schema detected; schema import skipped.' . PHP_EOL;
    return false;
}

function resetFreshDatabase(PDO $pdo): void
{
    $tables = $pdo->query(
        'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = \'BASE TABLE\''
    )->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($tables as $table) {
            $quoted = '`' . str_replace('`', '``', (string)$table) . '`';
            $pdo->exec('DROP TABLE IF EXISTS ' . $quoted);
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

function releaseVersion(string $projectRoot): string
{
    $version = trim((string)@file_get_contents($projectRoot . '/VERSION'));
    if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
        throw new RuntimeException('The repository VERSION file is missing or invalid.');
    }
    return $version;
}

function baselineBundledMigrations(PDO $pdo, string $upgradeDir, string $releaseVersion): void
{
    $files = glob(rtrim($upgradeDir, '/') . '/*.php') ?: [];
    sort($files);
    $statement = $pdo->prepare(
        "INSERT INTO s_migration
            (migration_id, checksum, release_version, status, started_at, finished_at, applied_by, error_message)
         VALUES (?, ?, ?, 'applied', NOW(), NOW(), 'fresh-install', NULL)"
    );
    foreach ($files as $file) {
        $migrationId = pathinfo($file, PATHINFO_FILENAME);
        $checksum = hash_file('sha256', $file);
        if ($checksum === false) {
            throw new RuntimeException('Unable to checksum migration: ' . $file);
        }
        $statement->execute([$migrationId, $checksum, $releaseVersion]);
    }
    echo 'Bundled database migrations baselined.' . PHP_EOL;
}

function executeSqlFile(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Unable to read SQL file: ' . $path);
    }
    $pdo->exec($sql);
}

function updateBaseUrl(PDO $pdo, string $baseUrl): void
{
    $statement = $pdo->prepare("UPDATE s_config SET s_config_value = ? WHERE s_config_handle = 'base_url' AND s_config_origin = 'S'");
    $statement->execute([$baseUrl]);
}

function provisionAdministrator(PDO $pdo, string $name, string $username, string $email, string $password): void
{
    $existing = $pdo->query('SELECT id, s_identity FROM s_entity WHERE id = 1')->fetch();
    if (is_array($existing)) {
        if ((string) $existing['s_identity'] !== $username) {
            throw new RuntimeException('Entity ID 1 already exists with a different username; administrator was not modified.');
        }
        echo 'Existing system administrator preserved.' . PHP_EOL;
        return;
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM s_entity')->fetchColumn() !== 0) {
        throw new RuntimeException('Cannot provision system administrator because other entities already exist and ID 1 is unavailable.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO s_entity '
        . '(id, uid, livestatus, versioncode, wf_status, space_id, createdby, createstamp, updatedby, updatestamp, '
        . 's_type, s_name, s_identity, s_identity_secret, s_nonsaas_role_id, s_email, s_login_mode, s_enable_mfa, s_agreement_signed) '
        . "VALUES (1, ?, '1', 1, 0, 0, 1, NOW(), 1, NOW(), 'U', ?, ?, ?, 1, ?, 'SE', 'N', 'N')"
    );
    $statement->execute([uuidV4(), $name, $username, password_hash($password, PASSWORD_DEFAULT), $email !== '' ? $email : null]);
    echo 'System administrator provisioned.' . PHP_EOL;
}

function uuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}
