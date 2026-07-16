<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Batoi\Rad\Diagnostics\QueryProfiler;

$profiler = new QueryProfiler();
$profiler->record("SELECT id\nFROM s_entity WHERE id = :id");
$profiler->record(' select id from s_entity where id = :id ');
$profiler->record('SELECT id FROM s_config WHERE id = :id');

if ($profiler->queryCount() !== 3) {
    throw new RuntimeException('Query profiler total is incorrect.');
}
if ($profiler->uniqueQueryCount() !== 2) {
    throw new RuntimeException('Query profiler normalization is incorrect.');
}
if ($profiler->duplicateQueryCount() !== 1) {
    throw new RuntimeException('Query profiler duplicate count is incorrect.');
}

echo "Query profiler test passed.\n";
