<?php
declare(strict_types=1);

$repositoryRoot = dirname(__DIR__, 2);
$forbiddenPrefixes = [
    'specs/',
    'test-results/',
    'rad/config/',
    'rad/data/',
    'rad/log/',
    'rad/ms/',
];
$allowedRuntimePlaceholders = [
    'rad/config/sys.inc.php.example',
    'rad/data/.gitkeep',
    'rad/log/.gitkeep',
    'rad/ms/.gitkeep',
];

$tracked = [];
exec('git -C ' . escapeshellarg($repositoryRoot) . ' ls-files', $tracked, $status);
if ($status !== 0) {
    throw new RuntimeException('Unable to inspect the Git publication boundary.');
}

foreach ($tracked as $path) {
    if (in_array($path, $allowedRuntimePlaceholders, true)) {
        continue;
    }
    foreach ($forbiddenPrefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            throw new RuntimeException('Forbidden path is tracked for publication: ' . $path);
        }
    }

    $absolute = $repositoryRoot . '/' . $path;
    if (!is_file($absolute) || filesize($absolute) > 2 * 1024 * 1024) {
        continue;
    }
    $contents = (string)file_get_contents($absolute);
    if (str_contains($contents, "\0")) {
        continue;
    }
    if (preg_match('#/(?:Users|home)/[A-Za-z0-9._-]+/#', $contents, $matches)) {
        throw new RuntimeException('Local home-directory path is tracked in ' . $path . ': ' . $matches[0]);
    }
}

echo "Publication boundary test passed.\n";
