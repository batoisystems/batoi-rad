<?php
namespace Core\Sys;

class CacheService {
    private string $baseDir;
    private array $config;

    public function __construct(array $config) {
        $this->config = $config;
        $radDir = rtrim((string)($config['dir']['rad'] ?? dirname(__DIR__, 2)), '/');
        $this->baseDir = $radDir . '/data/cache';
    }

    public function isEnabled(): bool {
        $flag = $this->getSysValue('rad_cache_enabled', null);
        if ($flag === null) {
            $flag = $this->config['rad']['cache']['enabled'] ?? true;
        }
        if (is_string($flag)) {
            return strtoupper($flag) === 'Y';
        }
        return (bool)$flag;
    }

    public function defaultTtl(string $type): int {
        $ttl = $this->getSysJson('rad_cache_ttl');
        if (empty($ttl)) {
            $ttl = $this->config['rad']['cache']['ttl'] ?? [];
        }
        if (is_array($ttl)) {
            $value = $ttl[$type] ?? $ttl['default'] ?? null;
            if ($value !== null) {
                return max(0, (int)$value);
            }
        }
        switch ($type) {
            case 'content':
                return 600;
            case 'dm':
                return 300;
            case 'route':
            default:
                return 300;
        }
    }

    public function variantDefaults(): array {
        $defaults = $this->getSysJson('rad_cache_variant_defaults');
        if (empty($defaults)) {
            $defaults = $this->config['rad']['cache']['variant_defaults'] ?? [];
        }
        $resolved = [
            'segments' => true,
            'query' => true,
            'space' => true,
            'host' => true,
        ];
        if (is_array($defaults)) {
            foreach ($resolved as $key => $value) {
                if (array_key_exists($key, $defaults)) {
                    $resolved[$key] = (bool)$defaults[$key];
                }
            }
        }
        return $resolved;
    }

    public function ignoredQueryParams(): array {
        $ignore = $this->getSysJson('rad_cache_ignore_query');
        if (empty($ignore)) {
            $ignore = $this->config['rad']['cache']['ignore_query'] ?? [];
        }
        if (!is_array($ignore)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $ignore)));
    }

    public function get(string $msName, string $type, string $id, string $variant = ''): array {
        $file = $this->resolveCacheFile($msName, $type, $id, $variant);
        if (!is_file($file)) {
            return ['hit' => false, 'reason' => 'miss', 'path' => $file];
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return ['hit' => false, 'reason' => 'empty', 'path' => $file];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['hit' => false, 'reason' => 'invalid', 'path' => $file];
        }
        $created = (int)($decoded['created_at'] ?? 0);
        $ttl = (int)($decoded['ttl'] ?? 0);
        if ($ttl > 0 && $created > 0 && (time() > ($created + $ttl))) {
            @unlink($file);
            return ['hit' => false, 'reason' => 'expired', 'path' => $file];
        }
        $payloadRaw = base64_decode((string)($decoded['payload_base64'] ?? ''), true);
        if ($payloadRaw === false) {
            return ['hit' => false, 'reason' => 'decode_error', 'path' => $file];
        }
        $format = $decoded['payload_format'] ?? 'text';
        $payload = $payloadRaw;
        if ($format === 'json') {
            $payload = json_decode($payloadRaw, true);
        }
        return [
            'hit' => true,
            'payload' => $payload,
            'meta' => $decoded['meta'] ?? [],
            'created_at' => $created,
            'ttl' => $ttl,
            'path' => $file,
        ];
    }

    public function set(string $msName, string $type, string $id, string $variant, $payload, int $ttl, array $meta = []): bool {
        if (!$this->isEnabled()) {
            return false;
        }
        $dir = $this->resolveCacheDir($msName, $type, $id);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
            return false;
        }
        $format = is_array($payload) ? 'json' : 'text';
        $payloadText = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : (string)$payload;
        $payloadText = $payloadText ?? '';
        $file = $this->resolveCacheFile($msName, $type, $id, $variant);
        $data = [
            'created_at' => time(),
            'ttl' => max(0, (int)$ttl),
            'expires_at' => max(0, (int)$ttl) > 0 ? time() + (int)$ttl : null,
            'payload_format' => $format,
            'payload_base64' => base64_encode($payloadText),
            'meta' => $meta,
        ];
        $temp = $file . '.' . uniqid('tmp', true);
        if (@file_put_contents($temp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            @unlink($temp);
            return false;
        }
        @rename($temp, $file);
        return true;
    }

    public function purgeAll(): int {
        return $this->deleteDirectory($this->baseDir);
    }

    public function purgeMs(string $msName): int {
        return $this->deleteDirectory($this->resolveMsDir($msName));
    }

    public function purgeType(string $msName, string $type): int {
        $msDir = $this->resolveMsDir($msName);
        if (!is_dir($msDir)) {
            return 0;
        }
        $deleted = 0;
        foreach (glob($msDir . '/' . $this->sanitizeKey($type) . '_*', GLOB_ONLYDIR) ?: [] as $path) {
            $deleted += $this->deleteDirectory($path);
        }
        return $deleted;
    }

    public function purgeItem(string $msName, string $type, string $id): int {
        return $this->deleteDirectory($this->resolveCacheDir($msName, $type, $id));
    }

    public function summarize(): array {
        $summary = [
            'base_dir' => $this->baseDir,
            'enabled' => $this->isEnabled(),
            'total_size' => 0,
            'total_entries' => 0,
            'services' => [],
        ];
        if (!is_dir($this->baseDir)) {
            return $summary;
        }
        foreach (glob($this->baseDir . '/*', GLOB_ONLYDIR) ?: [] as $msDir) {
            $msName = basename($msDir);
            $msEntry = [
                'ms_name' => $msName,
                'size' => 0,
                'entries' => 0,
                'types' => [],
            ];
            foreach (glob($msDir . '/*', GLOB_ONLYDIR) ?: [] as $typeDir) {
                $dirName = basename($typeDir);
                if (!preg_match('/^(route|dm|content)_([0-9A-Za-z_-]+)/', $dirName, $match)) {
                    continue;
                }
                $type = $match[1];
                $id = $match[2];
                $variantFiles = glob($typeDir . '/*.json') ?: [];
                $variants = count($variantFiles);
                $size = $this->directorySize($typeDir);
                $msEntry['entries'] += $variants;
                $msEntry['size'] += $size;
                if (!isset($msEntry['types'][$type])) {
                    $msEntry['types'][$type] = [
                        'type' => $type,
                        'count' => 0,
                        'size' => 0,
                        'items' => [],
                    ];
                }
                $msEntry['types'][$type]['count'] += $variants;
                $msEntry['types'][$type]['size'] += $size;
                $msEntry['types'][$type]['items'][] = [
                    'id' => $id,
                    'variants' => $variants,
                    'size' => $size,
                    'path' => $typeDir,
                ];
            }
            $summary['services'][] = $msEntry;
            $summary['total_entries'] += $msEntry['entries'];
            $summary['total_size'] += $msEntry['size'];
        }
        return $summary;
    }

    public function formatBytes(int $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
    }

    private function resolveCacheDir(string $msName, string $type, string $id): string {
        $msDir = $this->resolveMsDir($msName);
        $typeKey = $this->sanitizeKey($type) . '_' . $this->sanitizeKey($id);
        return $msDir . '/' . $typeKey;
    }

    private function resolveCacheFile(string $msName, string $type, string $id, string $variant): string {
        $dir = $this->resolveCacheDir($msName, $type, $id);
        $variant = trim($variant);
        $variantKey = $variant !== '' ? sha1($variant) : 'default';
        return $dir . '/' . $variantKey . '.json';
    }

    private function resolveMsDir(string $msName): string {
        $safe = $this->sanitizeKey($msName);
        return $this->baseDir . '/' . ($safe !== '' ? $safe : 'unknown');
    }

    private function sanitizeKey(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/[^a-z0-9._-]+/i', '-', $value);
        return trim($value, '-_');
    }

    private function getSysValue(string $handle, $fallback) {
        if (isset($this->config['sys'][$handle])) {
            return $this->config['sys'][$handle];
        }
        return $fallback;
    }

    private function getSysJson(string $handle): array {
        $value = $this->getSysValue($handle, '');
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function directorySize(string $path): int {
        $size = 0;
        if (!is_dir($path)) {
            return 0;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function deleteDirectory(string $path): int {
        if (!is_dir($path)) {
            return 0;
        }
        $deleted = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                if (@unlink($file->getPathname())) {
                    $deleted++;
                }
            }
        }
        @rmdir($path);
        return $deleted;
    }
}
