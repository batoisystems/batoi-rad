<?php
declare(strict_types=1);

// Invoked through build-release.php; no ambient vendor or runtime state is copied.
if (!extension_loaded('sodium') || !extension_loaded('zip')) {
    throw new RuntimeException('Headless release tooling requires Sodium and ZIP extensions.');
}
$root = dirname(__DIR__, 2);
$version = trim((string)file_get_contents($root . '/VERSION'));
$testMode = isset($options['test-mode']);
$prepare = isset($options['prepare']);
$commit = headlessCommand(['git', '-C', $root, 'rev-parse', 'HEAD']);
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    throw new RuntimeException('Headless foundations require a stable VERSION.');
}
if (!$testMode) {
    if (headlessCommand(['git', '-C', $root, 'status', '--porcelain', '--untracked-files=all']) !== '') {
        throw new RuntimeException('Production headless builds require a clean committed tree.');
    }
    if (!$prepare && headlessCommand(['git', '-C', $root, 'rev-parse', 'v' . $version . '^{commit}']) !== $commit) {
        throw new RuntimeException('The stable tag must resolve to the build commit.');
    }
}
headlessCommand([PHP_BINARY, $root . '/rad/bin/verify-release.php']);
require_once $root . '/rad/src/Distribution/HeadlessPackage.php';
$contract = \Batoi\Rad\Distribution\HeadlessPackage::contract();
$public = base64_decode($contract['signing']['public_key'], true);
$keyId = $contract['signing']['key_id'];
$secret = '';
if (isset($options['signing-key-file'])) {
    $secret = base64_decode(trim((string)file_get_contents((string)$options['signing-key-file'])), true);
    if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        throw new RuntimeException('Signing key file must contain a base64 Ed25519 64-byte secret key.');
    }
    $actualPublic = sodium_crypto_sign_publickey_from_secretkey($secret);
    if ($testMode) {
        $public = $actualPublic;
        $keyId = 'test-only';
    } elseif (!hash_equals($public, $actualPublic)) {
        throw new RuntimeException('Signing key does not match the existing Build trust root.');
    }
}
$output = (string)($options['output'] ?? ($root . '/batoi-rad-headless-app.zip'));
if (!str_starts_with($output, '/')) $output = getcwd() . '/' . $output;
if (!$testMode && basename($output) !== 'batoi-rad-headless-app.zip') {
    throw new RuntimeException('Production output must be named batoi-rad-headless-app.zip.');
}
if ($prepare) $output .= '.unsigned';
if ($testMode) $output .= '.test-only';
if (file_exists($output)) throw new RuntimeException('Refusing to replace an existing artifact: ' . $output);
$stage = sys_get_temp_dir() . '/rad-headless-' . bin2hex(random_bytes(8));
mkdir($stage . '/rad', 0700, true);
try {
    $tracked = explode("\n", headlessCommand(['git', '-C', $root, 'ls-files']));
    foreach ($tracked as $path) {
        if (!headlessIncluded($path)) continue;
        if (is_link($root . '/' . $path)) throw new RuntimeException('Symlink in runtime input: ' . $path);
        $destination = str_replace('rad/admin/install/', 'rad/install/', $path);
        $content = (string)file_get_contents($root . '/' . $path);
        if (in_array($path, ['public_html/index.php', 'rad/bin/upgrade.php'], true)) {
            $content = headlessProjection($content, $path);
        }
        headlessWrite($stage . '/' . $destination, $content);
    }
    headlessCommand(['composer', '--working-dir=' . $stage . '/rad', 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--no-scripts', '--no-plugins', '--no-progress']);
    headlessCommand(['composer', '--working-dir=' . $stage . '/rad', 'check-platform-reqs', '--no-dev']);
    headlessCommand([PHP_BINARY, $root . '/rad/bin/build-sbom.php', '--output=' . $stage . '/SBOM.cdx.json']);
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isLink()) throw new RuntimeException('Symlink in staged runtime.');
        if ($file->isFile()) $files[] = substr($file->getPathname(), strlen($stage) + 1);
    }
    sort($files, SORT_STRING);
    $inventory = [];
    foreach ($files as $file) $inventory[] = ['path' => $file, 'sha256' => hash_file('sha256', $stage . '/' . $file)];
    $manifest = [
        'schema_version' => 1,
        'profile' => 'headless-app',
        'version' => $version,
        'source' => ['repository' => 'https://github.com/batoisystems/batoi-rad', 'tag' => 'v' . $version, 'commit' => $commit],
        'signing' => ['algorithm' => 'ed25519', 'key_id' => $keyId],
        'compatibility' => [
            'contract' => 'batoi-build-rad-foundation/v1',
            'php' => ['min' => '8.3.0', 'max_exclusive' => '9.0.0'],
            'database' => ['engines' => ['mysql'], 'minimum_versions' => ['mysql' => '8.0.0']],
        ],
        'database_baseline_hashes' => [
            'schema_sha256' => hash_file('sha256', $stage . '/rad/install/schema.sql'),
            'seed_sha256' => hash_file('sha256', $stage . '/rad/install/seed.community.sql'),
            'sys_core_sha256' => hash_file('sha256', $stage . '/rad/install/sys_core.sql'),
        ],
        'include' => ['public_html/**', 'rad/core/**', 'rad/vendor/**', 'rad/theme/**', 'rad/install/**', 'rad/upgrades/**', 'rad/bin/install.php', 'rad/bin/doctor.php', 'rad/bin/upgrade.php', 'rad/autoload.php', 'rad/config/sys.inc.php.example'],
        'exclude' => ['rad/admin/**', 'rad/core/sys/RadAdminController.cls.php', 'rad/ms/**', 'rad/config/sys.inc.php', '.env', 'rad/data/**', 'rad/log/**'],
        'files' => $inventory,
    ];
    if ($testMode) $manifest['test_only'] = true;
    $raw = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $signature = '';
    if ($secret !== '') {
        $signature = sodium_crypto_sign_detached($raw, $secret);
        sodium_memzero($secret);
    } elseif (isset($options['signature-file'])) {
        $signature = base64_decode(trim((string)file_get_contents((string)$options['signature-file'])), true);
    }
    if (!$prepare && (!is_string($signature) || strlen($signature) !== 64 || !sodium_crypto_sign_verify_detached($signature, $raw, $public))) {
        throw new RuntimeException('A valid signature from the Build-pinned signer is required. Use --prepare for offline signing.');
    }
    $epoch = (int)headlessCommand(['git', '-C', $root, 'show', '-s', '--format=%ct', 'HEAD']);
    $zip = new ZipArchive();
    if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create headless ZIP.');
    foreach ($files as $file) headlessZipEntry($zip, 'artifact/' . $file, (string)file_get_contents($stage . '/' . $file), $epoch);
    headlessZipEntry($zip, 'headless-app.manifest.json', $raw, $epoch);
    headlessZipEntry($zip, 'headless-app.public-key', base64_encode($public) . "\n", $epoch);
    if (!$prepare) headlessZipEntry($zip, 'headless-app.manifest.sig', base64_encode($signature) . "\n", $epoch);
    if (!$zip->close()) throw new RuntimeException('Cannot finish headless ZIP.');
    if (!$prepare) \Batoi\Rad\Distribution\HeadlessPackage::verify($output, 'v' . $version, $commit, $testMode ? $public : null);
    foreach (['manifest.json' => $raw, 'public-key' => base64_encode($public) . "\n", 'SBOM.cdx.json' => (string)file_get_contents($stage . '/SBOM.cdx.json')] as $suffix => $contents) {
        headlessWrite($output . '.' . $suffix, $contents);
    }
    if (!$prepare) headlessWrite($output . '.manifest.sig', base64_encode($signature) . "\n");
    headlessWrite($output . '.sha256', hash_file('sha256', $output) . '  ' . basename($output) . "\n");
    echo 'Built ' . $output . "\nSHA-256 " . hash_file('sha256', $output) . "\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($stage);
}

/** @param list<string> $arguments */
function headlessCommand(array $arguments): string
{
    $lines = [];
    exec(implode(' ', array_map('escapeshellarg', $arguments)), $lines, $status);
    if ($status !== 0) throw new RuntimeException('Release command failed: ' . $arguments[0]);
    return trim(implode("\n", $lines));
}

function headlessIncluded(string $path): bool
{
    if (str_starts_with($path, 'rad/vendor/batoi/')) return true;
    if (preg_match('#(^|/)(?:\.env(?:\.|$)|\.DS_Store|tests?|docs?|\.git)(?:/|$)#i', $path)) return false;
    if ($path === 'rad/core/sys/RadAdminController.cls.php') return false;
    if (in_array($path, ['LICENSE', 'NOTICE', 'VERSION', 'rad/autoload.php', 'rad/contracts/headless-app-v1.json', 'rad/composer.json', 'rad/composer.lock', 'rad/config/sys.inc.php.example', 'rad/bin/install.php', 'rad/bin/doctor.php', 'rad/bin/upgrade.php'], true)) return true;
    foreach (['public_html/assets/uif/', 'public_html/assets/css/', 'public_html/assets/js/', 'public_html/assets/img/', 'rad/core/', 'rad/src/', 'rad/vendor/batoi/', 'rad/theme/', 'rad/upgrades/'] as $prefix) {
        if (str_starts_with($path, $prefix)) return true;
    }
    return in_array($path, ['public_html/index.php', 'public_html/.htaccess', 'rad/admin/install/schema.sql', 'rad/admin/install/seed.community.sql', 'rad/admin/install/sys_core.sql', 'rad/admin/install/schema-manifest.json'], true);
}

function headlessProjection(string $content, string $path): string
{
    $lines = explode("\n", $content);
    $removed = 0;
    foreach ($lines as $index => $line) {
        if (str_contains($line, '->addDirectory(') && str_contains($line, '/rad/admin/classes')) {
            unset($lines[$index]);
            $removed++;
        } elseif ($path === 'public_html/index.php' && str_contains($line, "\$this->baseRoutes['/rad-admin'] = 'RadAdminController';")) {
            unset($lines[$index]);
            $removed++;
        }
    }
    if ($removed !== ($path === 'public_html/index.php' ? 2 : 1)) throw new RuntimeException('Admin projection source changed; review packaging: ' . $path);
    return implode("\n", $lines);
}

function headlessWrite(string $path, string $content): void
{
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
    if (file_put_contents($path, $content) === false) throw new RuntimeException('Cannot write package file: ' . $path);
}

function headlessZipEntry(ZipArchive $zip, string $path, string $content, int $epoch): void
{
    if (!$zip->addFromString($path, $content)) throw new RuntimeException('Cannot add ZIP entry.');
    $zip->setMtimeName($path, $epoch);
    $zip->setCompressionName($path, ZipArchive::CM_DEFLATE, 9);
    $zip->setExternalAttributesName($path, ZipArchive::OPSYS_UNIX, 0100644 << 16);
}
