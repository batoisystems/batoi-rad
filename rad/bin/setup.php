#!/usr/bin/env php
<?php
declare(strict_types=1);

fwrite(STDERR, "rad/bin/setup.php is an alias for rad/bin/install.php.\n");
require __DIR__ . '/install.php';
