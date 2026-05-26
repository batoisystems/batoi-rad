<?php
namespace Core\App;

use RuntimeException;

/**
 * FileStore
 *
 * Lightweight helper to persist files into RAD upload roots.
 * - Global files: /rad/data/uploads/global/YYYY/MM/DD/{file}
 * - Workspace files: /rad/data/uploads/workspaces/{hash}/{spaceUid}/YYYY/MM/DD/{file}
 *
 * Usage:
 * $fs = new \Core\App\FileStore($config);
 * $path = $fs->storeGlobal('report.pdf', $binary);
 * $path = $fs->storeWorkspace($spaceUid, 'avatar.png', '/tmp/php123', true);
 */
class FileStore
{
    private string $globalBase;
    private string $workspaceBase;
    private string $normalizedGlobalBase;
    private string $normalizedWorkspaceBase;

    /**
     * @param array $config expects ['dir']['data'] pointing to rad/data
     */
    public function __construct(array $config)
    {
        $dataDir = rtrim($config['dir']['data'] ?? '', '/');
        if ($dataDir === '') {
            throw new RuntimeException('Data directory is not configured.');
        }
        $this->globalBase = $dataDir . '/uploads/global';
        $this->workspaceBase = $dataDir . '/uploads/workspaces';
        $this->normalizedGlobalBase = $this->normalizePath($this->globalBase);
        $this->normalizedWorkspaceBase = $this->normalizePath($this->workspaceBase);
    }

    /**
     * Store a file in the global namespace.
     *
     * @param string $fileName Target file name (sanitized)
     * @param string $contentOrPath Raw content or path to an existing temp file
     * @param bool $isTempPath When true, treat $contentOrPath as an existing file path
     * @return string Full path of the stored file
     */
    public function storeGlobal(string $fileName, string $contentOrPath, bool $isTempPath = false): string
    {
        $safeName = $this->sanitizeFileName($fileName);
        $targetDir = $this->datedPath($this->globalBase);
        $this->ensureDir($targetDir);
        $targetPath = $targetDir . '/' . $safeName;
        $this->writeFile($targetPath, $contentOrPath, $isTempPath);
        return $targetPath;
    }

    /**
     * Store a file scoped to a workspace.
     *
     * @param string $spaceUid Workspace UID
     * @param string $fileName Target file name (sanitized)
     * @param string $contentOrPath Raw content or path to an existing temp file
     * @param bool $isTempPath When true, treat $contentOrPath as an existing file path
     * @return string Full path of the stored file
     */
    public function storeWorkspace(string $spaceUid, string $fileName, string $contentOrPath, bool $isTempPath = false): string
    {
        $safeName = $this->sanitizeFileName($fileName);
        $hash = $this->workspaceHash($spaceUid);
        $base = $this->workspaceBase . '/' . $hash . '/' . $spaceUid;
        $targetDir = $this->datedPath($base);
        $this->ensureDir($targetDir);
        $targetPath = $targetDir . '/' . $safeName;
        $this->writeFile($targetPath, $contentOrPath, $isTempPath);
        return $targetPath;
    }

    /**
     * Build a dated path (no write) for a global file.
     */
    public function pathForGlobal(string $fileName, ?\DateTimeInterface $date = null): string
    {
        $safeName = $this->sanitizeFileName($fileName);
        $targetDir = $this->datedPath($this->globalBase, $date);
        return $targetDir . '/' . $safeName;
    }

    /**
     * Build a dated path (no write) for a workspace file.
     */
    public function pathForWorkspace(string $spaceUid, string $fileName, ?\DateTimeInterface $date = null): string
    {
        $safeName = $this->sanitizeFileName($fileName);
        $hash = $this->workspaceHash($spaceUid);
        $base = $this->workspaceBase . '/' . $hash . '/' . $spaceUid;
        $targetDir = $this->datedPath($base, $date);
        return $targetDir . '/' . $safeName;
    }

    /**
     * Read a stored file (within allowed roots).
     *
     * @return string
     */
    public function read(string $path): string
    {
        if (!$this->isAllowedPath($path) || !is_file($path)) {
            throw new RuntimeException('File not found or outside allowed roots.');
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Unable to read file: ' . $path);
        }
        return $data;
    }

    /**
     * Deterministic short hash for workspace sharding.
     */
    public function workspaceHash(string $spaceUid): string
    {
        return substr(md5($spaceUid), 0, 2);
    }

    /**
     * Build a dated directory path under a base.
     */
    private function datedPath(string $baseDir, ?\DateTimeInterface $date = null): string
    {
        $d = $date ?: new \DateTimeImmutable();
        $parts = [$d->format('Y'), $d->format('m'), $d->format('d')];
        return $baseDir . '/' . implode('/', $parts);
    }

    /**
     * Ensure directory exists with appropriate permissions.
     */
    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create directory: ' . $dir);
        }
    }

    /**
     * Check if a stored file exists (within allowed bases).
     */
    public function exists(string $path): bool
    {
        if (!$this->isAllowedPath($path)) {
            return false;
        }
        return is_file($path);
    }

    /**
     * Delete a stored file (within allowed bases).
     *
     * @return bool true if deleted, false if not found
     */
    public function delete(string $path): bool
    {
        if (!$this->isAllowedPath($path)) {
            throw new RuntimeException('Path is outside allowed storage roots.');
        }
        if (!is_file($path)) {
            return false;
        }
        return @unlink($path);
    }

    /**
     * Write or move file contents safely.
     */
    private function writeFile(string $targetPath, string $contentOrPath, bool $isTempPath): void
    {
        if ($isTempPath) {
            if (!is_file($contentOrPath)) {
                throw new RuntimeException('Source file not found: ' . $contentOrPath);
            }
            if (!@rename($contentOrPath, $targetPath)) {
                if (!@copy($contentOrPath, $targetPath)) {
                    throw new RuntimeException('Unable to move file to destination.');
                }
            }
            return;
        }

        if (file_put_contents($targetPath, $contentOrPath) === false) {
            throw new RuntimeException('Unable to write file: ' . $targetPath);
        }
    }

    /**
     * Basic filename sanitization to avoid traversal and control chars.
     */
    private function sanitizeFileName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        if ($name === '' || $name === '.' || $name === '..') {
            throw new RuntimeException('Invalid file name.');
        }
        return $name;
    }

    private function isAllowedPath(string $path): bool
    {
        $normalized = $this->normalizePath($path);
        return ($normalized !== null)
            && (strpos($normalized, $this->normalizedGlobalBase . '/') === 0
                || strpos($normalized, $this->normalizedWorkspaceBase . '/') === 0);
    }

    private function normalizePath(string $path): ?string
    {
        $parts = explode('/', str_replace('\\', '/', $path));
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($stack);
                continue;
            }
            $stack[] = $part;
        }
        if (empty($stack)) {
            return null;
        }
        return '/' . implode('/', $stack);
    }
}
