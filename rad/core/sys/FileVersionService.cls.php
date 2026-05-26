<?php
namespace Core\Sys;

class FileVersionService {
    private const DEFAULT_MAX_VERSIONS = 20;
    private $root;
    private $maxVersions;
    private $userResolver;
    private $minInterval;

    public function __construct(array $config = [], ?callable $userResolver = null) {
        $radDir = $config['dir']['rad'] ?? dirname(__DIR__, 2);
        $this->root = rtrim($radDir . '/data/versions', '/');
        $max = $config['versioning']['max_versions'] ?? $config['versions']['max'] ?? self::DEFAULT_MAX_VERSIONS;
        $this->maxVersions = (int)$max;
        if ($this->maxVersions <= 0) {
            $this->maxVersions = self::DEFAULT_MAX_VERSIONS;
        }
        $this->userResolver = $userResolver;
        $interval = $config['versioning']['min_interval_seconds'] ?? 60;
        $this->minInterval = max(5, (int)$interval);
    }

    public function snapshot(string $channel, string $itemId, string $content, array $meta = []): ?array {
        if ($channel === '' || $itemId === '') {
            return null;
        }
        $dir = $this->resolveDir($channel, $itemId);
        $this->ensureDirectory($dir);

        $timestamp = time();
        $checksum = sha1($content);
        $entries = $this->readManifest($channel, $itemId);
        $lastEntry = $entries[0] ?? null;
        $force = !empty($meta['force']);
        if ($lastEntry) {
            $lastTime = (int)($lastEntry['timestamp'] ?? 0);
            $lastChecksum = $lastEntry['checksum'] ?? '';
            if (!$force && $checksum === $lastChecksum) {
                return null;
            }
            if (!$force && $timestamp - $lastTime < $this->minInterval) {
                return null;
            }
        }
        $versionId = date('YmdHis', $timestamp) . '_' . substr(sha1($content . microtime(true)), 0, 8);
        $fileName = $versionId . '.txt';
        file_put_contents($dir . '/' . $fileName, $content);

        $entry = [
            'id' => $versionId,
            'timestamp' => $timestamp,
            'user' => $meta['user'] ?? $this->detectUser(),
            'size' => strlen($content),
            'size_human' => $this->formatBytes(strlen($content)),
            'checksum' => $checksum,
            'note' => isset($meta['note']) ? html_entity_decode((string)$meta['note'], ENT_QUOTES | ENT_HTML5) : '',
            'file' => $fileName,
        ];

        $entries[] = $entry;
        $entries = $this->trimEntries($dir, $entries);
        $this->writeManifest($channel, $itemId, $entries);
        return $entry;
    }

    public function listVersions(string $channel, string $itemId): array {
        $entries = $this->readManifest($channel, $itemId);
        foreach ($entries as &$entry) {
            if (isset($entry['note'])) {
                $entry['note'] = html_entity_decode((string)$entry['note'], ENT_QUOTES | ENT_HTML5);
            }
        }
        usort($entries, function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });
        return $entries;
    }

    public function listSnapshots(?string $channelFilter = null, string $search = ''): array {
        $results = [];
        $channels = $channelFilter ? [$channelFilter] : $this->discoverChannels();
        foreach ($channels as $channel) {
            $manifestMap = $this->findManifestPaths($channel);
            foreach ($manifestMap as $itemId => $manifestPath) {
                if ($search && stripos($itemId, $search) === false) {
                    continue;
                }
                $entries = $this->readManifest($channel, $itemId);
                foreach ($entries as $entry) {
                    if (isset($entry['note'])) {
                        $entry['note'] = html_entity_decode((string)$entry['note'], ENT_QUOTES | ENT_HTML5);
                    }
                    $results[] = array_merge($entry, [
                        'channel' => $channel,
                        'item' => $itemId,
                    ]);
                }
            }
        }
        usort($results, function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });
        return $results;
    }

    public function purgeSnapshots(string $channel, string $itemId, array $versionIds): int {
        $entries = $this->readManifest($channel, $itemId);
        $remaining = [];
        $deleted = 0;
        foreach ($entries as $entry) {
            if (in_array($entry['id'] ?? '', $versionIds, true)) {
                $file = $this->resolveFilePath($channel, $itemId, $entry['file'] ?? '');
                if (is_file($file)) {
                    @unlink($file);
                }
                $deleted++;
                continue;
            }
            $remaining[] = $entry;
        }
        $this->writeManifest($channel, $itemId, $remaining);
        return $deleted;
    }

    public function fetchVersion(string $channel, string $itemId, string $versionId): ?array {
        $entries = $this->readManifest($channel, $itemId);
        foreach ($entries as $entry) {
            if (($entry['id'] ?? '') === $versionId) {
                $path = $this->resolveFilePath($channel, $itemId, $entry['file'] ?? '');
                if (is_file($path)) {
                    $entry['content'] = file_get_contents($path);
                    return $entry;
                }
            }
        }
        return null;
    }

    public function diff(string $channel, string $itemId, string $versionId, string $currentContent): array {
        $version = $this->fetchVersion($channel, $itemId, $versionId);
        if (!$version) {
            return [];
        }
        $oldLines = explode("\n", $version['content'] ?? '');
        $newLines = explode("\n", $currentContent);
        $diff = [];
        $i = $j = 0;
        $oldCount = count($oldLines);
        $newCount = count($newLines);

        while ($i < $oldCount || $j < $newCount) {
            $oldLine = $oldLines[$i] ?? null;
            $newLine = $newLines[$j] ?? null;

            if ($oldLine !== null && $newLine !== null && $oldLine === $newLine) {
                $diff[] = ['type' => 'equal', 'old' => $oldLine, 'new' => $newLine, 'old_line' => $i + 1, 'new_line' => $j + 1];
                $i++; $j++;
                continue;
            }

            if ($oldLine !== null && $newLine !== null) {
                $diff[] = ['type' => 'replace', 'old' => $oldLine, 'new' => $newLine, 'old_line' => $i + 1, 'new_line' => $j + 1];
                $i++; $j++;
                continue;
            }

            if ($oldLine !== null) {
                $diff[] = ['type' => 'delete', 'old' => $oldLine, 'new' => '', 'old_line' => $i + 1, 'new_line' => null];
                $i++;
                continue;
            }

            if ($newLine !== null) {
                $diff[] = ['type' => 'insert', 'old' => '', 'new' => $newLine, 'old_line' => null, 'new_line' => $j + 1];
                $j++;
                continue;
            }
        }

        return $diff;
    }

    private function resolveDir(string $channel, string $itemId): string {
        return $this->root . '/' . trim($channel, '/') . '/' . trim($itemId, '/');
    }

    private function resolveFilePath(string $channel, string $itemId, string $file): string {
        return $this->resolveDir($channel, $itemId) . '/' . $file;
    }

    private function ensureDirectory(string $dir): void {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function readManifest(string $channel, string $itemId): array {
        $path = $this->resolveDir($channel, $itemId) . '/manifest.json';
        if (!is_file($path)) {
            return [];
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function writeManifest(string $channel, string $itemId, array $entries): void {
        $path = $this->resolveDir($channel, $itemId) . '/manifest.json';
        $this->ensureDirectory(dirname($path));
        file_put_contents($path, json_encode(array_values($entries), JSON_PRETTY_PRINT));
    }

    private function discoverChannels(): array {
        $dirs = glob($this->root . '/*', GLOB_ONLYDIR) ?: [];
        $channels = array_map('basename', $dirs);
        sort($channels);
        return $channels;
    }

    private function findManifestPaths(string $channel): array {
        $channelDir = $this->root . '/' . $channel;
        if (!is_dir($channelDir)) {
            return [];
        }

        $map = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($channelDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getFilename() === 'manifest.json') {
                $relative = trim(str_replace($channelDir, '', $fileInfo->getPath()), '/');
                $map[$relative] = $fileInfo->getPathname();
            }
        }

        // Also consider manifests directly under channel root
        if (is_file($channelDir . '/manifest.json')) {
            $map[''] = $channelDir . '/manifest.json';
        }
        return $map;
    }

    private function trimEntries(string $dir, array $entries): array {
        usort($entries, function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });
        if (count($entries) <= $this->maxVersions) {
            return $entries;
        }
        $excess = array_slice($entries, $this->maxVersions);
        foreach ($excess as $entry) {
            $file = $dir . '/' . ($entry['file'] ?? '');
            if (is_file($file)) {
                @unlink($file);
            }
        }
        return array_slice($entries, 0, $this->maxVersions);
    }

    private function detectUser(): string {
        if ($this->userResolver) {
            return (string)call_user_func($this->userResolver);
        }
        if (isset($_SESSION['fullname']) && $_SESSION['fullname'] !== '') {
            return $_SESSION['fullname'];
        }
        if (isset($_SESSION['username']) && $_SESSION['username'] !== '') {
            return $_SESSION['username'];
        }
        return 'RAD Admin';
    }

    private function formatBytes(int $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
