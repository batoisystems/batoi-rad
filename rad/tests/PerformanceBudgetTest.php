<?php
declare(strict_types=1);

$repositoryRoot = dirname(__DIR__, 2);
$budgets = [
    'public UIF' => [$repositoryRoot . '/public_html/assets/uif', 4 * 1024 * 1024],
    'RAD Admin assets' => [$repositoryRoot . '/rad/admin/assets', 28 * 1024 * 1024],
    'bundled AIF' => [$repositoryRoot . '/rad/vendor/batoi/aif', 3 * 1024 * 1024],
];

foreach ($budgets as $label => [$path, $limit]) {
    $bytes = directoryBytes($path);
    if ($bytes > $limit) {
        throw new RuntimeException(sprintf('%s exceeds its release budget: %d > %d bytes.', $label, $bytes, $limit));
    }
}

$peakLimit = 32 * 1024 * 1024;
if (memory_get_peak_usage(true) > $peakLimit) {
    throw new RuntimeException('Release-boundary tests exceeded the 32 MiB PHP memory budget.');
}

echo "Performance budget test passed.\n";

function directoryBytes(string $path): int
{
    $bytes = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $bytes += $file->getSize();
        }
    }
    return $bytes;
}
