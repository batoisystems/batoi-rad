<?php
declare(strict_types=1);

$tests = [
    __DIR__ . '/AifBoundaryTest.php',
    __DIR__ . '/SecurityBoundaryTest.php',
    __DIR__ . '/PublicationBoundaryTest.php',
    __DIR__ . '/CompatibilityContractTest.php',
    __DIR__ . '/PerformanceBudgetTest.php',
    __DIR__ . '/QueryProfilerTest.php',
    __DIR__ . '/HttpBoundaryTest.php',
    __DIR__ . '/DynOnlyMicroserviceTest.php',
];

foreach ($tests as $test) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test), $status);
    if ($status !== 0) {
        fwrite(STDERR, basename($test) . " failed.\n");
        exit($status);
    }
}

echo "RAD test suite passed.\n";
