#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$options = getopt('', ['output::', 'allow-dirty']);
$version = trim((string)file_get_contents($root . '/VERSION'));
$output = (string)($options['output'] ?? ($root . '/batoi-rad-' . $version . '.zip'));
if (!str_starts_with($output, '/')) {
    $output = getcwd() . '/' . $output;
}

if (!isset($options['allow-dirty'])) {
    exec('git -C ' . escapeshellarg($root) . ' status --porcelain --untracked-files=all', $statusLines, $status);
    if ($status !== 0 || $statusLines !== []) {
        fwrite(STDERR, "Release builds require a clean Git worktree. Use --allow-dirty only for local verification.\n");
        exit(1);
    }
}

passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/rad/bin/verify-release.php'), $verifyStatus);
if ($verifyStatus !== 0) {
    exit($verifyStatus);
}

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if (releaseExcluded($relative, basename($output))) {
        continue;
    }
    $files[] = $relative;
}
sort($files, SORT_STRING);

$epoch = (int)(getenv('SOURCE_DATE_EPOCH') ?: 0);
if ($epoch <= 0) {
    $epoch = 946684800; // 2000-01-01 UTC, stable across local builds.
}

$temporary = $output . '.tmp-' . bin2hex(random_bytes(5));
$zip = new ZipArchive();
if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Unable to create release archive: ' . $temporary);
}
$manifestFiles = [];
foreach ($files as $relative) {
    $source = $root . '/' . $relative;
    if (!$zip->addFile($source, $relative)) {
        throw new RuntimeException('Unable to add release file: ' . $relative);
    }
    $zip->setMtimeName($relative, $epoch);
    $mode = str_starts_with($relative, 'rad/bin/') ? 0100755 : 0100644;
    $zip->setExternalAttributesName($relative, ZipArchive::OPSYS_UNIX, $mode << 16);
    $manifestFiles[$relative] = hash_file('sha256', $source);
}
$uifSourceRoot = $root . '/public_html/assets/uif';
$uifIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uifSourceRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($uifIterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $uifRelative = substr($file->getPathname(), strlen($uifSourceRoot) + 1);
    $destination = 'rad/admin/assets/uif/' . $uifRelative;
    if (!$zip->addFile($file->getPathname(), $destination)) {
        throw new RuntimeException('Unable to generate RAD Admin UIF asset: ' . $uifRelative);
    }
    $zip->setMtimeName($destination, $epoch);
    $zip->setExternalAttributesName($destination, ZipArchive::OPSYS_UNIX, 0100644 << 16);
    $manifestFiles[$destination] = hash_file('sha256', $file->getPathname());
}
$sbomFile = tempnam(sys_get_temp_dir(), 'batoi-rad-sbom-');
if ($sbomFile === false) {
    throw new RuntimeException('Unable to allocate temporary SBOM file.');
}
passthru(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/rad/bin/build-sbom.php')
    . ' --output=' . escapeshellarg($sbomFile),
    $sbomStatus
);
if ($sbomStatus !== 0) {
    @unlink($sbomFile);
    throw new RuntimeException('Unable to generate the release SBOM.');
}
$sbomContents = file_get_contents($sbomFile);
if ($sbomContents === false || !$zip->addFromString('SBOM.cdx.json', $sbomContents)) {
    @unlink($sbomFile);
    throw new RuntimeException('Unable to add the release SBOM.');
}
$zip->setMtimeName('SBOM.cdx.json', $epoch);
$zip->setExternalAttributesName('SBOM.cdx.json', ZipArchive::OPSYS_UNIX, 0100644 << 16);
$manifestFiles['SBOM.cdx.json'] = hash('sha256', $sbomContents);
@unlink($sbomFile);
$manifest = json_encode([
    'schema' => 1,
    'product' => 'Batoi RAD',
    'version' => $version,
    'source_date_epoch' => $epoch,
    'files' => $manifestFiles,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$zip->addFromString('RELEASE-MANIFEST.json', $manifest);
$zip->setMtimeName('RELEASE-MANIFEST.json', $epoch);
$zip->setExternalAttributesName('RELEASE-MANIFEST.json', ZipArchive::OPSYS_UNIX, 0100644 << 16);
$zip->close();

if (!rename($temporary, $output)) {
    @unlink($temporary);
    throw new RuntimeException('Unable to publish release archive: ' . $output);
}
$checksum = hash_file('sha256', $output);
file_put_contents($output . '.sha256', $checksum . '  ' . basename($output) . "\n");
echo 'Built ' . $output . PHP_EOL;
echo 'SHA-256 ' . $checksum . PHP_EOL;

function releaseExcluded(string $path, string $outputName): bool
{
    if ($path === $outputName || $path === $outputName . '.sha256') {
        return true;
    }
    foreach (['.git/', '.github/', 'specs/'] as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }
    if (basename($path) === '.DS_Store' || in_array($path, ['.env', 'public_html/php_error.log'], true)) {
        return true;
    }
    if (str_starts_with($path, 'rad/config/') && $path !== 'rad/config/sys.inc.php.example') {
        return true;
    }
    foreach (['rad/data/', 'rad/log/', 'rad/ms/'] as $prefix) {
        if (str_starts_with($path, $prefix) && $path !== $prefix . '.gitkeep') {
            return true;
        }
    }
    foreach (['rad/tests/browser/node_modules/', 'rad/tests/browser/test-results/', 'rad/tests/browser/playwright-report/'] as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }
    if (str_starts_with($path, 'rad/admin/assets/uif/')) {
        return true;
    }
    if (str_starts_with($path, 'rad/vendor/') && !str_starts_with($path, 'rad/vendor/batoi/')) {
        return true;
    }
    return false;
}
