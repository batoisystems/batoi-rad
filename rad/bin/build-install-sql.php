#!/usr/bin/env php
<?php
declare(strict_types=1);

$installDir = dirname(__DIR__) . '/admin/install';
$options = getopt('', ['write']);
$combined = (string)file_get_contents($installDir . '/schema.sql')
    . "\n-- Community Edition seed data\n\n"
    . (string)file_get_contents($installDir . '/seed.community.sql');
$target = $installDir . '/sys_core.sql';

if (isset($options['write'])) {
    if (file_put_contents($target, $combined) === false) {
        throw new RuntimeException('Unable to write generated install SQL.');
    }
    echo "Generated rad/admin/install/sys_core.sql.\n";
    exit(0);
}

if (!is_file($target) || !hash_equals(hash('sha256', $combined), (string)hash_file('sha256', $target))) {
    fwrite(STDERR, "sys_core.sql is stale; run php rad/bin/build-install-sql.php --write.\n");
    exit(1);
}
echo "Generated install SQL is current.\n";
