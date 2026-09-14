<?php
declare(strict_types=1);

namespace Batoi\Rad\Distribution;

use RuntimeException;
use ZipArchive;

/** Producer-owned checks for the existing published headless-app wire format. */
final class HeadlessPackage
{
    public static function contract(): array
    {
        return json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/contracts/headless-app-v1.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array{manifest: array, files: array<string, string>} */
    public static function verify(string $archive, string $tag, string $commit, ?string $testPublicKey = null): array
    {
        $contract = self::contract();
        $limits = $contract['limits'];
        if (!is_file($archive) || filesize($archive) > $limits['archive_bytes']) throw new RuntimeException('Invalid archive size.');
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Invalid ZIP.');
        try {
            if ($zip->numFiles < 4 || $zip->numFiles > $limits['files'] + 3) throw new RuntimeException('Invalid ZIP entry count.');
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                self::safePath($name);
                if (isset($names[$name])) throw new RuntimeException('Duplicate ZIP entry.');
                $names[$name] = true;
                $zip->getExternalAttributesIndex($i, $system, $attributes);
                if ((($attributes >> 16) & 0170000) === 0120000) throw new RuntimeException('ZIP symlinks are prohibited.');
            }
            $raw = self::entry($zip, 'headless-app.manifest.json', $limits['manifest_bytes']);
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            self::identity($manifest, $tag, $commit, $contract);
            $keyId = $testPublicKey === null ? $contract['signing']['key_id'] : 'test-only';
            $public = $testPublicKey ?? (string)base64_decode($contract['signing']['public_key'], true);
            $uploaded = base64_decode(trim(self::entry($zip, 'headless-app.public-key', 4096)), true);
            $signature = base64_decode(trim(self::entry($zip, 'headless-app.manifest.sig', 4096)), true);
            if (($manifest['signing']['key_id'] ?? '') !== $keyId
                || ($manifest['signing']['algorithm'] ?? '') !== 'ed25519'
                || (!empty($manifest['test_only'])) !== ($testPublicKey !== null)
                || strlen($public) !== 32 || $uploaded !== $public || $signature === false || strlen($signature) !== 64
                || !sodium_crypto_sign_verify_detached($signature, $raw, $public)) throw new RuntimeException('Manifest signature or trusted key mismatch.');
            $files = [];
            $bytes = 0;
            $inventory = $manifest['files'] ?? [];
            if (!is_array($inventory) || count($inventory) < 1 || count($inventory) > $limits['files']) throw new RuntimeException('Invalid manifest inventory.');
            foreach ($inventory as $entry) {
                $path = (string)($entry['path'] ?? '');
                self::payloadPath($path);
                if (isset($files[$path])) throw new RuntimeException('Duplicate manifest path.');
                $content = self::entry($zip, 'artifact/' . $path, $limits['file_bytes']);
                if (!hash_equals(hash('sha256', $content), (string)($entry['sha256'] ?? ''))) throw new RuntimeException('Payload hash mismatch: ' . $path);
                self::headlessContent($path, $content);
                $bytes += strlen($content);
                if ($bytes > $limits['payload_bytes']) throw new RuntimeException('Payload size limit exceeded.');
                $files[$path] = $content;
                unset($names['artifact/' . $path]);
            }
            foreach (['headless-app.manifest.json', 'headless-app.manifest.sig', 'headless-app.public-key'] as $name) unset($names[$name]);
            if ($names !== []) throw new RuntimeException('Unmanifested ZIP entries.');
            self::runtime($files, $manifest, $contract);
            return ['manifest' => $manifest, 'files' => $files];
        } finally {
            $zip->close();
        }
    }

    private static function identity(array $manifest, string $tag, string $commit, array $contract): void
    {
        $version = (string)($manifest['version'] ?? '');
        if (($manifest['schema_version'] ?? null) !== 1 || ($manifest['profile'] ?? '') !== 'headless-app'
            || !preg_match('/^v?\d+\.\d+\.\d+$/', $tag) || ltrim($tag, 'v') !== $version
            || !preg_match('/^[a-f0-9]{40,64}$/', $commit)
            || ($manifest['source']['repository'] ?? '') !== $contract['repository']
            || ($manifest['source']['tag'] ?? '') !== $tag || ($manifest['source']['commit'] ?? '') !== $commit
            || !in_array('rad/admin/**', $manifest['exclude'] ?? [], true)) throw new RuntimeException('Invalid source identity or headless profile.');
        $compatibility = $manifest['compatibility'] ?? [];
        if (($compatibility['contract'] ?? '') !== $contract['compatibility_contract']
            || ($compatibility['php'] ?? []) !== ['min' => '8.3.0', 'max_exclusive' => '9.0.0']
            || ($compatibility['database'] ?? []) !== ['engines' => ['mysql'], 'minimum_versions' => ['mysql' => '8.0.0']]) throw new RuntimeException('Unsupported runtime compatibility declaration.');
    }

    private static function safePath(string $path): void
    {
        if ($path === '' || str_contains($path, '\\') || str_contains($path, "\0") || str_contains($path, ':')) throw new RuntimeException('Unsafe archive path.');
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') throw new RuntimeException('Unsafe archive path.');
        }
    }

    private static function payloadPath(string $path): void
    {
        self::safePath($path);
        if (preg_match('#^(?:rad/(?:admin|ms|data|log)/|rad/vendor/(?:phpstan|bin)/)|(?:^|/)\.env(?:\.|$)|(?:^|/)(?:\.git|\.DS_Store)(?:/|$)|^rad/config/(?!sys\.inc\.php\.example$)#i', $path)
            || $path === 'rad/core/sys/RadAdminController.cls.php') throw new RuntimeException('Forbidden payload path: ' . $path);
    }

    private static function headlessContent(string $path, string $content): void
    {
        if (!str_ends_with($path, '.php')) return;
        if (($path === 'public_html/index.php' && (stripos($content, 'rad/admin') !== false || stripos($content, '/rad-admin') !== false))
            || preg_match('/\b(?:require|require_once|include|include_once)\s*\(?[^;\r\n]*(?:rad\/admin|\/rad-admin)/i', $content)
            || preg_match('/addDirectory\s*\([^;\r\n]*rad\/admin/i', $content)
            || preg_match('/baseRoutes\s*\[[^\]]*[\'\"]\/rad-admin[\'\"][^\]]*\]|new\s+\\\\?Core\\\\Sys\\\\RadAdminController\b/i', $content)) throw new RuntimeException('Executable Admin dependency: ' . $path);
    }

    /** @param array<string, string> $files */
    private static function runtime(array $files, array $manifest, array $contract): void
    {
        foreach ($contract['required_files'] as $path) {
            if (!isset($files[$path])) throw new RuntimeException('Missing runtime dependency: ' . $path);
        }
        if (trim($files['VERSION']) !== $manifest['version']) throw new RuntimeException('Runtime VERSION mismatch.');
        foreach ($contract['database_baselines'] as $key => $path) {
            if (($manifest['database_baseline_hashes'][$key] ?? '') !== hash('sha256', $files[$path])) throw new RuntimeException('Database baseline mismatch.');
        }
        $lock = json_decode($files['rad/composer.lock'], true, 512, JSON_THROW_ON_ERROR);
        $installed = json_decode($files['rad/vendor/composer/installed.json'], true, 512, JSON_THROW_ON_ERROR);
        $expected = array_column($lock['packages'], 'version', 'name');
        $actual = array_column($installed['packages'], 'version', 'name');
        ksort($expected);
        ksort($actual);
        if ($actual !== $expected || ($installed['dev'] ?? true) !== false) throw new RuntimeException('Production dependency lock mismatch.');
        $distribution = json_decode($files['rad/vendor/batoi/distributions.json'], true, 512, JSON_THROW_ON_ERROR);
        $aif = array_filter(array_keys($files), static fn(string $path): bool => str_starts_with($path, 'rad/vendor/batoi/aif/'));
        sort($aif, SORT_STRING);
        $tree = hash_init('sha256');
        foreach ($aif as $path) hash_update($tree, substr($path, strlen('rad/vendor/batoi/aif/')) . "\0" . hash('sha256', $files[$path]) . "\n");
        if (count($aif) !== $distribution['distributions']['batoi-aif']['file_count']
            || !hash_equals($distribution['distributions']['batoi-aif']['tree_sha256'], hash_final($tree))) throw new RuntimeException('AIF provenance mismatch.');
    }

    private static function entry(ZipArchive $zip, string $name, int $limit): string
    {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] < 0 || $stat['size'] > $limit) throw new RuntimeException('Missing or oversized ZIP entry: ' . $name);
        $content = $zip->getFromName($name);
        if ($content === false || strlen($content) !== $stat['size']) throw new RuntimeException('Unreadable ZIP entry: ' . $name);
        return $content;
    }

    /** @param array<string, string> $files */
    public static function extract(array $files, string $destination): void
    {
        if (file_exists($destination)) throw new RuntimeException('Extraction destination must not exist.');
        foreach ($files as $path => $content) {
            self::payloadPath($path);
            if (!is_dir(dirname($destination . '/' . $path))) mkdir(dirname($destination . '/' . $path), 0700, true);
            if (file_put_contents($destination . '/' . $path, $content) === false) throw new RuntimeException('Cannot extract runtime file.');
        }
    }
}
