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
$forbiddenPathPatterns = [
    '#(^|/)\.env(?:\.|$)#i',
    '#(^|/)(?:id_(?:rsa|dsa|ecdsa|ed25519)|credentials?|secrets?)(?:\.[^/]*)?$#i',
    '#\.(?:p12|pfx|pem)$#i',
];
$secretPatterns = [
    'private key' => '#-----BEGIN (?:[A-Z0-9]+ )?PRIVATE KEY-----#',
    'Mailgun private API key' => '#\bkey-[a-f0-9]{32}\b#i',
    'Mailgun public validation key' => '#\bpubkey-[a-f0-9]{32}\b#i',
    'Mailgun webhook signing key' => '#\b[a-h0-9]{32}-[a-h0-9]{8}-[a-h0-9]{8}\b#i',
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
    foreach ($forbiddenPathPatterns as $pattern) {
        if (preg_match($pattern, $path)) {
            throw new RuntimeException('Credential-bearing path is tracked for publication: ' . $path);
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
    foreach ($secretPatterns as $label => $pattern) {
        if (preg_match($pattern, $contents)) {
            throw new RuntimeException($label . ' material is tracked in ' . $path . '.');
        }
    }
    if (str_ends_with($path, '.sql') && preg_match(
        "#'(?:[^']*(?:api_key|smtp_password|account_password|client_secret)[^']*)'\\s*,\\s*'([^']{12,})'#i",
        $contents
    )) {
        throw new RuntimeException('Populated credential configuration is tracked in ' . $path . '.');
    }
}

echo "Publication boundary test passed.\n";
