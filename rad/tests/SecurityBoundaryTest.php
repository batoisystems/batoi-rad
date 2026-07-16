<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/sys/SafePath.cls.php';
require_once dirname(__DIR__) . '/core/sys/SafeZipExtractor.cls.php';
require_once dirname(__DIR__) . '/core/sys/ReadOnlySqlPolicy.cls.php';
require_once dirname(__DIR__) . '/core/app/FileStore.cls.php';
require_once dirname(__DIR__) . '/core/sys/PrivilegeService.cls.php';
require_once dirname(__DIR__) . '/core/sys/DeveloperToolPolicy.cls.php';

use Core\Sys\ReadOnlySqlPolicy;
use Core\Sys\SafePath;
use Core\Sys\SafeZipExtractor;
use Core\Sys\DeveloperToolPolicy;

$root = sys_get_temp_dir() . '/rad-safe-path-' . bin2hex(random_bytes(6));
$outside = sys_get_temp_dir() . '/rad-safe-path-outside-' . bin2hex(random_bytes(6));
mkdir($root . '/core', 0700, true);
mkdir($outside, 0700, true);
file_put_contents($root . '/core/example.php', '<?php');
file_put_contents($outside . '/secret.txt', 'secret');

try {
    $paths = new SafePath($root);
    assertSecuritySame(realpath($root . '/core/example.php'), $paths->existing('core/example.php'), 'existing path');
    assertSecuritySame(null, $paths->existing('../' . basename($outside) . '/secret.txt'), 'traversal rejection');
    assertSecuritySame(realpath($root) . '/core/new.php', $paths->forWrite('core/new.php'), 'write path');
    assertSecuritySame(null, $paths->forWrite('core/../../escape.php'), 'write traversal rejection');

    if (function_exists('symlink') && @symlink($outside, $root . '/core/outside-link')) {
        assertSecuritySame(null, $paths->existing('core/outside-link/secret.txt'), 'symlink rejection');
    }

    assertSecuritySame('SELECT id FROM s_entity LIMIT 1', ReadOnlySqlPolicy::assertSafe('SELECT id FROM s_entity LIMIT 1'), 'select policy');
    assertSecuritySame('WITH rows AS (SELECT 1 AS id) SELECT * FROM rows', ReadOnlySqlPolicy::assertSafe('WITH rows AS (SELECT 1 AS id) SELECT * FROM rows'), 'with policy');
    foreach ([
        'UPDATE s_entity SET livestatus = 0',
        'SELECT 1; DROP TABLE s_entity',
        'SELECT SLEEP(10)',
        'SELECT * FROM s_entity INTO OUTFILE "/tmp/entities"',
        'SELECT 1 -- comment',
    ] as $unsafeSql) {
        assertSecurityThrows(static fn () => ReadOnlySqlPolicy::assertSafe($unsafeSql), 'unsafe SQL rejection');
    }

    $zipPath = $root . '/unsafe.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('../escape.txt', 'unsafe');
    $zip->close();

    $store = new \Core\App\FileStore(['dir' => ['data' => $root . '/storage']]);
    assertSecurityThrows(
        static fn () => $store->storeWorkspace('../outside', 'unsafe.txt', 'unsafe'),
        'workspace upload traversal rejection',
        RuntimeException::class
    );

    $disabledTools = new DeveloperToolPolicy(['sys' => ['developer_tools_enabled' => 'N']], ['id' => 1]);
    assertSecuritySame(false, $disabledTools->allowsTool('source_write'), 'disabled developer tool policy');
    $enabledAdminTools = new DeveloperToolPolicy(['sys' => ['developer_tools_enabled' => 'Y']], ['id' => 1]);
    assertSecuritySame(true, $enabledAdminTools->allowsTool('source_write'), 'system administrator tool policy');
    $unprivilegedTools = new DeveloperToolPolicy(['sys' => ['developer_tools_enabled' => 'Y']], ['id' => 999]);
    assertSecuritySame(false, $unprivilegedTools->allowsTool('source_write'), 'unprivileged developer tool policy');
    $zip->open($zipPath);
    assertSecurityThrows(static fn () => SafeZipExtractor::extract($zip, $root . '/core'), 'ZIP traversal rejection', RuntimeException::class);
    $zip->close();

    $loginSource = (string)file_get_contents(dirname(__DIR__) . '/core/sys/LoginController.cls.php');
    if (str_contains($loginSource, "code === '000000'") || !str_contains($loginSource, 'rotateId()')) {
        throw new RuntimeException('Authentication hardening boundary is missing.');
    }
    $sessionSource = (string)file_get_contents(dirname(__DIR__) . '/core/sys/SessionManager.cls.php');
    if (str_contains($sessionSource, "['idle_timeout'] * 60") || !str_contains($sessionSource, 'session_regenerate_id(true)')) {
        throw new RuntimeException('Session timeout or rotation hardening is missing.');
    }
    $adminControllerSource = (string)file_get_contents(dirname(__DIR__) . '/core/sys/RadAdminController.cls.php');
    if (!str_contains($adminControllerSource, 'enforceCsrfForMutation') || !str_contains($adminControllerSource, 'MUTATING_EVENTS')) {
        throw new RuntimeException('RAD Admin mutation protection is missing.');
    }
    $assistantSource = (string)file_get_contents(dirname(__DIR__) . '/admin/classes/Codeassistapi.cls.php');
    if (preg_match('/\bexec\s*\(/', $assistantSource) || str_contains($assistantSource, 'function run_php')) {
        throw new RuntimeException('Arbitrary PHP execution returned to Code Assist.');
    }
} finally {
    removeSecurityFixture($root);
    removeSecurityFixture($outside);
}

echo "Security boundary test passed.\n";

function assertSecuritySame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ' mismatch.');
    }
}

function assertSecurityThrows(callable $callback, string $label, string $exceptionClass = InvalidArgumentException::class): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if ($exception instanceof $exceptionClass) {
            return;
        }
        throw $exception;
    }
    throw new RuntimeException($label . ' did not throw.');
}

function removeSecurityFixture(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        removeSecurityFixture($path . '/' . $entry);
    }
    @rmdir($path);
}
