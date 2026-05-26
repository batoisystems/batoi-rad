<?php
namespace Core\Sys;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use DirectoryIterator;

class WorkspaceStorageService {
    private string $basePath;
    private string $globalPath;

    public function __construct(array $config) {
        $this->basePath = rtrim($config['dir']['data'] ?? __DIR__ . '/../../data/uploads', '/');
        $this->globalPath = $this->basePath . '/global';
        if (!is_dir($this->globalPath)) {
            @mkdir($this->globalPath, 0775, true);
        }
    }

    public function getBasePath(): string {
        return $this->basePath;
    }

    public function workspaceRelativePath(string $spaceUid): string {
        $normalizedUid = $this->normalizeUid($spaceUid);
        $shard = $this->shardKey($normalizedUid);
        return 'workspaces/' . $shard . '/' . $normalizedUid;
    }

    public function workspaceAbsolutePath(string $spaceUid, bool $ensure = false): string {
        $path = $this->basePath . '/' . $this->workspaceRelativePath($spaceUid);
        if ($ensure && !is_dir($path)) {
            mkdir($path, 0775, true);
        }
        return $path;
    }

    public function summarizeWorkspace(string $spaceUid): array {
        $absolute = $this->workspaceAbsolutePath($spaceUid, true);
        if (!is_dir($absolute)) {
            return [
                'files' => 0,
                'size' => 0,
                'path' => $absolute,
                'relative' => $this->workspaceRelativePath($spaceUid),
            ];
        }

        $files = 0;
        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files++;
                $bytes += $file->getSize();
            }
        }

        return [
            'files' => $files,
            'size' => $bytes,
            'path' => $absolute,
            'relative' => $this->workspaceRelativePath($spaceUid),
        ];
    }

    public function listFiles(string $spaceUid, int $limit = 500): array {
        $absolute = $this->workspaceAbsolutePath($spaceUid);
        if (!is_dir($absolute)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relativeInternal = substr($file->getPathname(), strlen($absolute) + 1);
            $files[] = [
                'name' => $file->getFilename(),
                'subpath' => $relativeInternal,
                'relative' => $this->workspaceRelativePath($spaceUid) . '/' . $relativeInternal,
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
            ];
        }

        usort($files, static function ($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });

        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
        }
        return $files;
    }

    public function listLegacyBuckets(): array {
        $entries = [];
        if (!is_dir($this->basePath)) {
            return $entries;
        }

        foreach (new DirectoryIterator($this->basePath) as $item) {
            if ($item->isDot()) {
                continue;
            }
            if ($item->getFilename() === 'workspaces') {
                continue;
            }
            if (in_array($item->getFilename(), ['projectfiles', 'annotationfiles'], true)) {
                continue;
            }
            if ($item->getFilename() === 'global') {
                $entries[] = [
                    'label' => 'Non-SaaS / Global',
                    'name' => $item->getFilename(),
                    'is_dir' => true,
                    'size' => $this->directorySize($item->getPathname()),
                    'modified' => $item->getMTime(),
                    'relative' => $item->getFilename(),
                ];
                continue;
            }

            if ($item->getFilename() === '.DS_Store') {
                continue;
            }

            $entries[] = [
                'name' => $item->getFilename(),
                'is_dir' => $item->isDir(),
                'size' => $item->isFile() ? $item->getSize() : $this->directorySize($item->getPathname()),
                'modified' => $item->getMTime(),
                'relative' => $item->getFilename(),
            ];
        }
        usort($entries, static function ($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });
        return $entries;
    }

    private function directorySize(string $path): int {
        if (!is_dir($path)) {
            return 0;
        }
        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $bytes += $file->getSize();
            }
        }
        return $bytes;
    }

    private function shardKey(string $uid): string {
        $uid = $this->normalizeUid($uid);
        return substr($uid, 0, 2) ?: 'xx';
    }

    private function normalizeUid(string $uid): string {
        $uid = strtolower(trim($uid));
        $uid = preg_replace('/[^a-z0-9]+/', '', $uid);
        if ($uid === '') {
            $uid = 'default';
        }
        return $uid;
    }
    public function globalPath(string $subpath = ''): string {
        $subpath = ltrim($subpath, '/');
        $path = $this->globalPath . ($subpath ? '/' . $subpath : '');
        return $path;
    }
}
