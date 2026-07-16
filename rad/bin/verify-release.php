#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$required = [
    'LICENSE', 'NOTICE', 'README.md', 'SECURITY.md', 'CODE_OF_CONDUCT.md', 'VERSION',
    'public_html/index.php', 'public_html/assets/uif/uif.esm.js',
    'rad/bin/install.php', 'rad/bin/doctor.php', 'rad/composer.json', 'rad/composer.lock',
    'rad/config/sys.inc.php.example', 'rad/vendor/batoi/aif/autoload.php',
    'rad/vendor/batoi/distributions.json',
    'rad/admin/install/schema-manifest.json',
    'rad/tests/browser/package-lock.json',
];
foreach ($required as $path) {
    if (!is_file($root . '/' . $path)) {
        $errors[] = 'Missing required release file: ' . $path;
    }
}

$distributionManifestPath = $root . '/rad/vendor/batoi/distributions.json';
if (is_file($distributionManifestPath)) {
    $distributions = json_decode((string)file_get_contents($distributionManifestPath), true);
    $aif = $distributions['distributions']['batoi-aif'] ?? [];
    $aifRoot = $root . '/rad/vendor/batoi/aif';
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($aifRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = substr($file->getPathname(), strlen($aifRoot) + 1);
        }
    }
    sort($files, SORT_STRING);
    $tree = hash_init('sha256');
    foreach ($files as $file) {
        hash_update($tree, $file . "\0" . hash_file('sha256', $aifRoot . '/' . $file) . "\n");
    }
    if ((int)($aif['file_count'] ?? -1) !== count($files) || !hash_equals((string)($aif['tree_sha256'] ?? ''), hash_final($tree))) {
        $errors[] = 'Batoi AIF distribution manifest does not match the bundled tree.';
    }
}
foreach (['public_html', 'rad/admin', 'rad/core', 'rad/upgrades'] as $path) {
    if (!is_dir($root . '/' . $path)) {
        $errors[] = 'Missing required release directory: ' . $path;
    }
}
foreach (['rad/config/sys.inc.php', '.env', 'public_html/php_error.log'] as $path) {
    if (is_file($root . '/' . $path)) {
        $errors[] = 'Local/runtime file must not ship: ' . $path;
    }
}

$version = trim((string)@file_get_contents($root . '/VERSION'));
if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
    $errors[] = 'VERSION is not valid semantic version text.';
}

$uifIntegrity = $root . '/public_html/assets/uif/integrity.json';
if (!is_file($uifIntegrity)) {
    $errors[] = 'Missing UIF integrity manifest.';
} else {
    $manifest = json_decode((string)file_get_contents($uifIntegrity), true);
    if (!is_array($manifest)) {
        $errors[] = 'UIF integrity manifest is invalid JSON.';
    } else {
        foreach (($manifest['files'] ?? []) as $file => $metadata) {
            $publicFile = $root . '/public_html/assets/uif/' . $file;
            if (!is_file($publicFile)) {
                $errors[] = 'UIF manifest file is missing: ' . $file;
                continue;
            }
            $expectedHash = (string)($metadata['sha256'] ?? '');
            if ($expectedHash === '' || !hash_equals($expectedHash, hash_file('sha256', $publicFile))) {
                $errors[] = 'UIF checksum mismatch: ' . $file;
            }
            $adminFile = $root . '/rad/admin/assets/uif/' . $file;
            if (is_file($adminFile) && !hash_equals(hash_file('sha256', $publicFile), hash_file('sha256', $adminFile))) {
                $errors[] = 'RAD Admin UIF copy differs: ' . $file;
            }
        }
    }
}

$schemaManifestPath = $root . '/rad/admin/install/schema-manifest.json';
if (is_file($schemaManifestPath)) {
    $schemaManifest = json_decode((string)file_get_contents($schemaManifestPath), true);
    if (!is_array($schemaManifest)) {
        $errors[] = 'Install schema manifest is invalid JSON.';
    } else {
        foreach (($schemaManifest['files'] ?? []) as $file => $expectedHash) {
            $path = $root . '/rad/admin/install/' . basename((string)$file);
            if (!is_file($path) || !hash_equals((string)$expectedHash, (string)hash_file('sha256', $path))) {
                $errors[] = 'Install schema checksum mismatch: ' . $file;
            }
        }
        $combined = (string)@file_get_contents($root . '/rad/admin/install/schema.sql')
            . "\n-- Community Edition seed data\n\n"
            . (string)@file_get_contents($root . '/rad/admin/install/seed.community.sql');
        if (!hash_equals(hash('sha256', $combined), (string)@hash_file('sha256', $root . '/rad/admin/install/sys_core.sql'))) {
            $errors[] = 'Generated sys_core.sql differs from canonical schema plus Community seed.';
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}
echo 'Release structure verification passed for Batoi RAD ' . $version . '.' . PHP_EOL;
