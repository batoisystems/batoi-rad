<?php
declare(strict_types=1);

use Batoi\Rad\Distribution\HeadlessPackage;

require_once dirname(__DIR__) . '/src/Distribution/HeadlessPackage.php';
$options = getopt('', ['archive:', 'signing-key-file:', 'tag:', 'commit:', 'extract:']);
$archive = (string)($options['archive'] ?? '');
$secret = base64_decode(trim((string)file_get_contents((string)($options['signing-key-file'] ?? ''))), true);
if ($secret === false || strlen($secret) !== 64) throw new RuntimeException('An explicit ephemeral test signing key is required.');
$public = sodium_crypto_sign_publickey_from_secretkey($secret);
$tag = (string)($options['tag'] ?? '');
$commit = (string)($options['commit'] ?? '');
$valid = HeadlessPackage::verify($archive, $tag, $commit, $public);
$temporary = sys_get_temp_dir() . '/rad-package-test-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
$cases = ['payload', 'manifest', 'signature', 'key', 'commit', 'tag', 'traversal', 'symlink', 'untrusted', 'production-trust', 'extra-file', 'missing-runtime', 'admin', 'config', 'admin-code', 'duplicate', 'baseline', 'profile', 'compatibility', 'dependency', 'oversized'];
try {
    foreach ($cases as $case) {
        $path = $temporary . '/' . $case . '.zip';
        copy($archive, $path);
        $zip = new ZipArchive();
        $zip->open($path);
        $manifest = $valid['manifest'];
        $resign = false;
        if ($case === 'payload') $zip->addFromString('artifact/public_html/index.php', 'tampered');
        if ($case === 'manifest') $zip->addFromString('headless-app.manifest.json', (string)$zip->getFromName('headless-app.manifest.json') . ' ');
        if ($case === 'signature') $zip->addFromString('headless-app.manifest.sig', base64_encode(str_repeat("\0", 64)));
        if ($case === 'key') $zip->addFromString('headless-app.public-key', base64_encode(str_repeat("\0", 32)));
        if ($case === 'traversal') $zip->addFromString('../escape', 'bad');
        if ($case === 'symlink') {
            $zip->addFromString('artifact/link', '/etc/passwd');
            $zip->setExternalAttributesName('artifact/link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        }
        if ($case === 'extra-file') $zip->addFromString('artifact/unmanifested.txt', 'bad');
        if ($case === 'missing-runtime') {
            $zip->deleteName('artifact/rad/bin/install.php');
            $manifest['files'] = array_values(array_filter($manifest['files'], static fn(array $entry): bool => $entry['path'] !== 'rad/bin/install.php'));
            $resign = true;
        }
        if (in_array($case, ['admin', 'config', 'admin-code', 'dependency'], true)) {
            $file = match ($case) {
                'admin' => 'rad/admin/secret.php',
                'config' => 'rad/config/sys.inc.php',
                'dependency' => 'rad/vendor/composer/installed.json',
                default => 'rad/core/sys/Unsafe.cls.php',
            };
            $content = match ($case) {
                'admin-code' => '<?php require "rad/admin/start.php";',
                'dependency' => '{"packages":[],"dev":false}',
                default => '<?php echo "unwanted";',
            };
            $zip->addFromString('artifact/' . $file, $content);
            $manifest['files'] = array_values(array_filter($manifest['files'], static fn(array $entry): bool => $entry['path'] !== $file));
            $manifest['files'][] = ['path' => $file, 'sha256' => hash('sha256', $content)];
            $resign = true;
        }
        if ($case === 'duplicate') { $manifest['files'][] = $manifest['files'][0]; $resign = true; }
        if ($case === 'baseline') { $manifest['database_baseline_hashes']['schema_sha256'] = str_repeat('0', 64); $resign = true; }
        if ($case === 'profile') { $manifest['profile'] = 'standard'; $resign = true; }
        if ($case === 'compatibility') { $manifest['compatibility']['contract'] = 'unknown/v1'; $resign = true; }
        if ($case === 'oversized') $zip->addFromString('headless-app.manifest.json', str_repeat(' ', 8388609));
        if ($resign) {
            $raw = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $zip->addFromString('headless-app.manifest.json', $raw);
            $zip->addFromString('headless-app.manifest.sig', base64_encode(sodium_crypto_sign_detached($raw, $secret)));
        }
        $zip->close();
        try {
            HeadlessPackage::verify($path, $case === 'tag' ? 'v99.0.0' : $tag, $case === 'commit' ? str_repeat('0', 40) : $commit,
                $case === 'production-trust' ? null : ($case === 'untrusted' ? str_repeat("\0", 32) : $public));
            throw new LogicException('Package verifier accepted invalid case: ' . $case);
        } catch (RuntimeException $expected) {
            // Only verifier failures count; LogicException above fails the test.
        }
    }
    if (isset($options['extract'])) HeadlessPackage::extract($valid['files'], (string)$options['extract']);
} finally {
    foreach (glob($temporary . '/*.zip') ?: [] as $path) unlink($path);
    rmdir($temporary);
    sodium_memzero($secret);
}
echo 'Standalone package verification and ' . count($cases) . " adversarial cases passed.\n";
