<?php
declare(strict_types=1);

$options = getopt('', ['app:', 'consumer:', 'port::']);
$appRoot = (string)($options['app'] ?? '');
$consumer = (string)($options['consumer'] ?? '');
$port = (int)($options['port'] ?? 18087);
$name = getenv('RAD_TEST_DB_NAME') ?: 'rad_headless_test';
if (!preg_match('/^rad_headless_[a-z0-9_]+$/', $name)) throw new RuntimeException('Only disposable rad_headless_* databases are allowed.');
if (!is_file($appRoot . '/rad/install/schema.sql') || is_dir($appRoot . '/rad/admin')) throw new RuntimeException('Expected an extracted headless App.');
$host = getenv('RAD_TEST_DB_HOST') ?: '127.0.0.1';
$socket = getenv('RAD_TEST_DB_SOCKET') ?: '';
$user = getenv('RAD_TEST_DB_USER') ?: 'root';
$password = getenv('RAD_TEST_DB_PASSWORD') ?: '';
$adminPassword = bin2hex(random_bytes(10)) . 'Aa1!';
$dsn = 'mysql:' . ($socket !== '' ? 'unix_socket=' . $socket : 'host=' . $host) . ';dbname=' . $name;
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$install = [PHP_BINARY, $appRoot . '/rad/bin/install.php', '--db-host=' . $host, '--db-name=' . $name, '--db-user=' . $user, '--base-url=http://127.0.0.1:' . $port, '--non-interactive', '--skip-composer'];
if ($socket !== '') $install[] = '--db-socket=' . $socket;
putenv('RAD_DB_PASSWORD=' . $password);
putenv('RAD_ADMIN_PASSWORD=' . $adminPassword);
foreach ([$install, $install, [PHP_BINARY, $appRoot . '/rad/bin/doctor.php'], [PHP_BINARY, $appRoot . '/rad/bin/upgrade.php']] as $command) {
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Headless installation/readiness failed.');
}
if ($consumer !== '') {
    require_once $consumer . '/rad/ms/build/BuildRadFoundationRegistrationService.cls.php';
    $registration = json_decode((string)file_get_contents($appRoot . '/.build/foundation-registration.json'), true, 512, JSON_THROW_ON_ERROR);
    $apply = new ReflectionMethod('BuildRadFoundationRegistrationService', 'apply');
    $apply->invoke(null, $pdo, [$registration], $registration['app_uid']);
    $apply->invoke(null, $pdo, [$registration], $registration['app_uid']);
    // Make the generated module public only in this disposable fixture to test rendering without workspace login.
    $pdo->exec("UPDATE s_ms SET s_scope='global' WHERE s_name='contract-app'");
    $pdo->exec("UPDATE s_msroute SET s_degree=1 WHERE s_name='health'");
} else {
    installStandaloneFixture($pdo, $appRoot);
}
$log = $appRoot . '/http-test.log';
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $appRoot . '/public_html', $appRoot . '/public_html/index.php'], [['pipe', 'r'], ['file', $log, 'a'], ['file', $log, 'a']], $pipes);
if (!is_resource($server)) throw new RuntimeException('Cannot start test HTTP server.');
try {
    for ($i = 0; $i < 30; $i++) {
        $connection = @fsockopen('127.0.0.1', $port);
        if (is_resource($connection)) { fclose($connection); break; }
        usleep(100000);
    }
    foreach (['/' => 'rad-home-page', '/contract-app/health' => ($consumer !== '' ? 'Application route generated' : 'Standalone RAD health')] as $path => $needle) {
        $curl = curl_init('http://127.0.0.1:' . $port . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'RAD headless runtime test']);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($status !== 200 || !is_string($body) || !str_contains($body, $needle)) {
            throw new RuntimeException('Application request failed: ' . $path . ' HTTP ' . $status . '; inspect ' . $log);
        }
    }
    $curl = curl_init('http://127.0.0.1:' . $port . '/rad-admin');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'RAD headless runtime test']);
    $adminBody = curl_exec($curl);
    if (curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 404 && $adminBody !== 'No or Multiple Microservicelets found') throw new RuntimeException('Removed Admin gateway must not resolve to an Admin controller.');
} finally {
    proc_terminate($server);
    proc_close($server);
}
echo "Headless install, idempotent rerun, doctor, upgrade and HTTP requests passed.\n";

// RAD-owned fixture: only public runtime tables and a normal DYN route.
function installStandaloneFixture(PDO $pdo, string $appRoot): void
{
    $pdo->exec("INSERT INTO s_ms (uid,livestatus,s_name,s_type,s_definition,s_scope,s_tpl_name) VALUES ('f249df3c-95fa-48fc-b9a4-70ef6fb15e94','1','contract-app','DYN','{\"route_path\":\"manual\"}','global','app.tpl.php') ON DUPLICATE KEY UPDATE s_tpl_name='app.tpl.php'");
    $msId = (int)$pdo->query("SELECT id FROM s_ms WHERE s_name='contract-app'")->fetchColumn();
    $statement = $pdo->prepare("INSERT INTO s_msroute (uid,livestatus,s_ms_id,s_name,s_degree,s_entity_scope,s_service_definition) VALUES ('95edb3ef-30f2-41b4-9dba-b4c378d4978e','1',?,'health',1,'U','{}') ON DUPLICATE KEY UPDATE s_degree=1");
    $statement->execute([$msId]);
    $directory = $appRoot . '/rad/ms/contract-app';
    if (!is_dir($directory)) mkdir($directory, 0700, true);
    file_put_contents($directory . '/route.health.php', "<?php\n\$this->runData['route']['meta_title'] = 'Standalone RAD health';\n");
    file_put_contents($directory . '/route.health.pagepart.php', '<main><h1>Standalone RAD health</h1></main>');
}
