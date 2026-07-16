<?php
declare(strict_types=1);

$logRoot = $argv[1] ?? dirname(__DIR__) . '/log';
$latest = null;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($logRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getFilename() === 'access.log') {
        if ($latest === null || $file->getMTime() > $latest->getMTime()) {
            $latest = $file;
        }
    }
}

if ($latest === null) {
    throw new RuntimeException('No access log is available for the runtime performance gate.');
}

$dashboard = null;
foreach (file($latest->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $jsonStart = strpos($line, '{');
    if ($jsonStart === false) {
        continue;
    }
    $entry = json_decode(substr($line, $jsonStart), true);
    if (is_array($entry) && ($entry['uri'] ?? '') === '/rad-admin/home/view') {
        $dashboard = $entry;
    }
}

if ($dashboard === null) {
    throw new RuntimeException('No authenticated dashboard request was recorded for the runtime performance gate.');
}

$limits = [
    'execution_time' => 4.0,
    'query_count' => 200,
    'duplicate_query_count' => 50,
    'peak_memory_bytes' => 64 * 1024 * 1024,
];
foreach ($limits as $metric => $limit) {
    if (!array_key_exists($metric, $dashboard)) {
        throw new RuntimeException('Dashboard log is missing performance metric: ' . $metric);
    }
    if ((float)$dashboard[$metric] > $limit) {
        throw new RuntimeException(sprintf('Dashboard %s exceeds its budget: %s > %s.', $metric, $dashboard[$metric], $limit));
    }
}

if (array_key_exists('session_key', $dashboard)) {
    throw new RuntimeException('Access logs must not contain a raw session key.');
}

echo sprintf(
    "Runtime performance gate passed: %.3fs, %d queries (%d duplicate), %.1f MiB peak memory.\n",
    (float)$dashboard['execution_time'],
    (int)$dashboard['query_count'],
    (int)$dashboard['duplicate_query_count'],
    (int)$dashboard['peak_memory_bytes'] / 1024 / 1024
);
