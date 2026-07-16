#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$options = getopt('', ['output::']);
$lock = json_decode((string)file_get_contents($root . '/rad/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$distribution = json_decode(
    (string)file_get_contents($root . '/rad/vendor/batoi/distributions.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);

$components = [];
foreach ($lock['packages'] ?? [] as $package) {
    $component = [
        'type' => 'library',
        'bom-ref' => 'pkg:composer/' . $package['name'] . '@' . $package['version'],
        'name' => $package['name'],
        'version' => $package['version'],
        'purl' => 'pkg:composer/' . $package['name'] . '@' . rawurlencode($package['version']),
    ];
    if (($package['license'] ?? []) !== []) {
        $component['licenses'] = array_map(
            static fn(string $license): array => ['license' => ['id' => $license]],
            $package['license']
        );
    }
    $components[] = $component;
}

foreach ($distribution['distributions'] ?? [] as $name => $item) {
    if (($item['included'] ?? true) !== true) {
        continue;
    }
    $component = [
        'type' => 'library',
        'bom-ref' => 'batoi:' . $name . ':' . ($item['version'] ?? $item['source_commit'] ?? 'unknown'),
        'name' => 'Batoi ' . strtoupper((string)$name),
        'version' => (string)($item['version'] ?? $item['source_commit'] ?? 'unknown'),
        'properties' => [],
    ];
    foreach (['source_commit', 'tree_sha256', 'license'] as $property) {
        if (isset($item[$property])) {
            $component['properties'][] = [
                'name' => 'batoi:' . $property,
                'value' => (string)$item[$property],
            ];
        }
    }
    $components[] = $component;
}

usort($components, static fn(array $a, array $b): int => strcmp($a['bom-ref'], $b['bom-ref']));
$version = trim((string)file_get_contents($root . '/VERSION'));
$sbom = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.5',
    'version' => 1,
    'metadata' => [
        'component' => [
            'type' => 'framework',
            'bom-ref' => 'pkg:composer/batoi/rad@' . $version,
            'name' => 'batoi/rad',
            'version' => $version,
            'purl' => 'pkg:composer/batoi/rad@' . rawurlencode($version),
        ],
    ],
    'components' => $components,
];
$json = json_encode($sbom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$output = $options['output'] ?? null;
if (is_string($output) && $output !== '') {
    if (file_put_contents($output, $json) === false) {
        throw new RuntimeException('Unable to write SBOM: ' . $output);
    }
    echo 'Wrote ' . $output . PHP_EOL;
} else {
    echo $json;
}
