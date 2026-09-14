<?php
declare(strict_types=1);

// Contract code is loaded from the real consumer checkout, never reimplemented.
$options = getopt('', ['consumer:', 'archive:', 'extract:', 'test-trust']);
$consumer = (string)($options['consumer'] ?? '');
$archive = (string)($options['archive'] ?? '');
if (!is_file($consumer . '/rad/ms/build/BuildRadFoundationService.cls.php') || !is_file($archive)) {
    throw new RuntimeException('Usage: --consumer=PATH --archive=ZIP [--test-trust] [--extract=EMPTY_DIRECTORY]');
}
require_once $consumer . '/rad/ms/build/BuildRadFoundationService.cls.php';
$zip = new ZipArchive();
if ($zip->open($archive) !== true) throw new RuntimeException('Cannot read test archive.');
$raw = (string)$zip->getFromName('headless-app.manifest.json');
$manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
$public = trim((string)$zip->getFromName('headless-app.public-key'));
$entries = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string)$zip->getNameIndex($i);
    if (isset($entries[$name])) throw new RuntimeException('Duplicate ZIP entry.');
    $entries[$name] = true;
}
$zip->close();
$expected = ['headless-app.manifest.json', 'headless-app.manifest.sig', 'headless-app.public-key'];
foreach ($manifest['files'] as $entry) {
    $path = $entry['path'];
    if (preg_match('#^(?:rad/(?:admin|ms|data|log)/|rad/vendor/(?:phpstan|bin)/)|(?:^|/)\.env(?:\.|$)|(?:^|/)(?:\.git|\.DS_Store)(?:/|$)|^rad/config/(?!sys\.inc\.php\.example$)#i', $path)
        || $path === 'rad/core/sys/RadAdminController.cls.php') {
        throw new RuntimeException('Forbidden runtime content: ' . $path);
    }
    $expected[] = 'artifact/' . $path;
}
sort($expected);
$actual = array_keys($entries);
sort($actual);
if ($expected !== $actual) throw new RuntimeException('ZIP contains unmanifested files.');
$temporary = sys_get_temp_dir() . '/rad-consumer-test-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
register_shutdown_function(static function () use ($temporary): void {
    if (!is_dir($temporary)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($temporary);
});
$db = new HeadlessCatalogDouble();
$runData = ['db' => $db, 'config' => ['dir' => ['data' => $temporary]]];
if (isset($options['test-trust'])) {
    if (($manifest['signing']['key_id'] ?? '') !== 'test-only' || empty($manifest['test_only'])) throw new RuntimeException('Test trust requires explicitly marked test artifact.');
    $runData['config']['build']['rad_foundation_trusted_keys'] = ['test-only' => $public];
} elseif (!empty($manifest['test_only'])) {
    throw new RuntimeException('Test artifacts cannot pass production validation.');
}
$serviceClass = (new ReflectionMethod('BuildRadFoundationService', '__construct'))->getDeclaringClass();
$register = $serviceClass->getMethod('registerCanonicalReleaseArchive');
$service = $serviceClass->newInstanceArgs([&$runData]);
$context = ['execution_principal' => 'rad_foundation_acquisition_worker'];
$tag = $manifest['source']['tag'];
$commit = $manifest['source']['commit'];
$result = $register->invoke($service, $context, $archive, $tag, $commit);
if (empty($result['ok'])) throw new RuntimeException('Actual consumer registration failed.');
$verified = (new ReflectionMethod($service, 'verifiedManifest'))->invoke($service, $db->release);
$files = (new ReflectionMethod($service, 'foundationFiles'))->invoke($service, $db->release, $verified);
foreach (['rad/install/schema.sql', 'rad/install/sys_core.sql', 'rad/install/seed.community.sql', 'rad/bin/upgrade.php', 'rad/vendor/autoload.php', 'rad/vendor/league/commonmark/src/MarkdownConverter.php', 'rad/vendor/batoi/aif/autoload.php', 'rad/theme/app.tpl.php', 'public_html/assets/css/rad-theme.css', 'public_html/assets/img/welcome.svg'] as $required) {
    if (!isset($files[$required])) throw new RuntimeException('Missing runtime dependency: ' . $required);
}
foreach (['schema_sha256' => 'schema.sql', 'sys_core_sha256' => 'sys_core.sql', 'seed_sha256' => 'seed.community.sql'] as $hashKey => $path) {
    if (!hash_equals($manifest['database_baseline_hashes'][$hashKey], hash('sha256', $files['rad/install/' . $path]))) throw new RuntimeException('Baseline hash mismatch.');
}
$distribution = json_decode($files['rad/vendor/batoi/distributions.json'], true, 512, JSON_THROW_ON_ERROR);
$aifFiles = array_filter(array_keys($files), static fn(string $path): bool => str_starts_with($path, 'rad/vendor/batoi/aif/'));
sort($aifFiles, SORT_STRING);
$tree = hash_init('sha256');
foreach ($aifFiles as $path) hash_update($tree, substr($path, strlen('rad/vendor/batoi/aif/')) . "\0" . hash('sha256', $files[$path]) . "\n");
if (count($aifFiles) !== $distribution['distributions']['batoi-aif']['file_count']
    || !hash_equals($distribution['distributions']['batoi-aif']['tree_sha256'], hash_final($tree))) throw new RuntimeException('Bundled AIF provenance mismatch.');
$app = ['id'  => 1, 'uid' => '3f4abe73-929a-5c71-8328-661294ab216f', 'a_name' => 'Contract App', 'a_app_key' => 'contract-app'];
$overlay = (new ReflectionMethod($service, 'appOverlay'))->invoke($service, $app, (new ReflectionMethod($service, 'appDraft'))->invoke($service, $app), $db->release, $verified);
require_once $consumer . '/rad/ms/build/BuildRadFoundationRegistrationService.cls.php';
(new ReflectionMethod('BuildRadFoundationRegistrationService', 'validateManifest'))->invoke(null, json_decode($overlay['.build/foundation-registration.json'], true), $app['uid']);
if (isset($options['extract'])) {
    $destination = (string)$options['extract'];
    if (file_exists($destination)) throw new RuntimeException('Extraction destination must not exist.');
    foreach (array_replace($files, $overlay) as $path => $content) {
        if (!is_dir(dirname($destination . '/' . $path))) mkdir(dirname($destination . '/' . $path), 0700, true);
        file_put_contents($destination . '/' . $path, $content);
    }
}
// Reject tampering through the public acquisition entrypoint, with fresh catalogs.
foreach (['payload', 'manifest', 'signature', 'key', 'commit', 'tag', 'traversal', 'symlink', 'untrusted'] as $mutation) {
    $modified = $temporary . '/' . $mutation . '.zip';
    copy($archive, $modified);
    $z = new ZipArchive();
    $z->open($modified);
    if ($mutation === 'payload') $z->addFromString('artifact/public_html/index.php', '<?php echo "tampered";');
    if ($mutation === 'manifest') $z->addFromString('headless-app.manifest.json', $raw . ' ');
    if ($mutation === 'signature') $z->addFromString('headless-app.manifest.sig', base64_encode(str_repeat("\0", 64)));
    if ($mutation === 'key') $z->addFromString('headless-app.public-key', base64_encode(str_repeat("\0", 32)));
    if ($mutation === 'traversal') $z->addFromString('../escape', 'invalid');
    if ($mutation === 'symlink') {
        $z->addFromString('artifact/link', '/etc/passwd');
        $z->setExternalAttributesName('artifact/link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    }
    $z->close();
    $badData = $runData;
    $badData['db'] = new HeadlessCatalogDouble();
    mkdir($temporary . '/' . $mutation, 0700);
    $badData['config']['dir']['data'] = $temporary . '/' . $mutation;
    if ($mutation === 'untrusted') $badData['config']['build']['rad_foundation_trusted_keys'] = [$manifest['signing']['key_id'] => base64_encode(str_repeat("\0", 32))];
    try {
        $register->invoke($serviceClass->newInstanceArgs([&$badData]), $context, $modified, $mutation === 'tag' ? 'v99.0.0' : $tag, $mutation === 'commit' ? str_repeat('0', 40) : $commit);
        throw new LogicException('Actual consumer accepted tampering: ' . $mutation);
    } catch (RuntimeException $exception) {
        // A storage collision is not evidence of tamper rejection.
        if (str_contains($exception->getMessage(), 'already present')) throw $exception;
    }
}
// Installation revalidates private artifacts as well as acquisition downloads.
file_put_contents($temporary . '/' . $db->release['a_artifact_root_ref'] . '/public_html/index.php', 'changed after acquisition');
try {
    (new ReflectionMethod($service, 'foundationFiles'))->invoke($service, $db->release, $verified);
    throw new LogicException('Consumer accepted post-acquisition tampering.');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'hash verification failed')) throw $exception;
}
echo 'Actual Build registration, manifest revalidation, runtime inventory, App overlay and 10 tamper cases passed (' . count($files) . " files).\n";

// Only catalog persistence is replaced; all archive, signature and install-file validation is real.
final class HeadlessCatalogDouble
{
    public array $release = [];
    public function query(string $sql, array $parameters = []): array
    {
        if (str_contains($sql, 'INFORMATION_SCHEMA.TABLES')) return [['present' => 1]];
        return [];
    }
    public function insert(string $table, array $payload, array $metadata = []): int
    {
        $this->release = array_merge($payload, $metadata, ['id' => 1]);
        return 1;
    }
}
