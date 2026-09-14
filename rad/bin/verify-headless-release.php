#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Distribution/HeadlessPackage.php';
$options = getopt('', ['archive:', 'tag:', 'commit:', 'test-public-key-file:', 'extract:']);
$testKey = isset($options['test-public-key-file']) ? base64_decode(trim((string)file_get_contents($options['test-public-key-file'])), true) : null;
if ($testKey === false) throw new RuntimeException('Invalid test public key.');
$result = \Batoi\Rad\Distribution\HeadlessPackage::verify((string)($options['archive'] ?? ''), (string)($options['tag'] ?? ''), (string)($options['commit'] ?? ''), $testKey);
if (isset($options['extract'])) \Batoi\Rad\Distribution\HeadlessPackage::extract($result['files'], (string)$options['extract']);
echo 'Standalone headless verification passed: ' . count($result['files']) . " runtime files.\n";
