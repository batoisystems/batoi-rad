<?php
declare(strict_types=1);

$radRoot = dirname(__DIR__);
$repositoryRoot = dirname($radRoot);
$contractPath = $radRoot . '/contracts/v1.json';
$contract = json_decode((string) file_get_contents($contractPath), true, 512, JSON_THROW_ON_ERROR);

if (($contract['major'] ?? null) !== 1 || ($contract['schema'] ?? null) !== 1) {
    throw new RuntimeException('The v1 compatibility contract header is invalid.');
}

$frontController = (string) file_get_contents($repositoryRoot . '/public_html/index.php');
foreach (($contract['public_routes'] ?? []) as $route => $controller) {
    $binding = '$this->baseRoutes[' . var_export($route, true) . '] = ' . var_export($controller, true) . ';';
    if (!str_contains($frontController, $binding)) {
        throw new RuntimeException('Public route contract is missing from the front controller: ' . $route);
    }
}

$composer = json_decode((string) file_get_contents($radRoot . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (($contract['runtime']['extensions'] ?? []) as $extension) {
    if (!isset($composer['require']['ext-' . $extension])) {
        throw new RuntimeException('Runtime extension contract is not declared by Composer: ' . $extension);
    }
}

$installer = (string) file_get_contents($radRoot . '/bin/install.php');
foreach (($contract['installer_options'] ?? []) as $option) {
    if (!str_contains($installer, $option)) {
        throw new RuntimeException('Installer option contract is missing: --' . $option);
    }
}

$configExample = (string) file_get_contents($radRoot . '/config/sys.inc.php.example');
foreach (($contract['environment'] ?? []) as $name) {
    if ($name === 'RAD_ADMIN_UIF_ENABLED') {
        continue;
    }
    if (!str_contains($configExample, $name)) {
        throw new RuntimeException('Configuration environment contract is missing: ' . $name);
    }
}

echo "Compatibility contract test passed.\n";
