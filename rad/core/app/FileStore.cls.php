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
    private \Core\Sys\SafePath $globalPolicy;
    private \Core\Sys\SafePath $workspacePolicy;

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
        $this->ensureDir($this->globalBase);
        $this->ensureDir($this->workspaceBase);
        $this->globalPolicy = new \Core\Sys\SafePath($this->globalBase);
        $this->workspacePolicy = new \Core\Sys\SafePath($this->workspaceBase);
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
        $relative = $this->datedRelativePath() . '/' . $safeName;
        $targetPath = $this->globalPolicy->forWrite($relative);
        if ($targetPath === null) {
            throw new RuntimeException('Invalid global storage path.');
        }
        $targetDir = dirname($targetPath);
        $this->ensureDir($targetDir);
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
        $spaceUid = $this->normalizeWorkspaceUid($spaceUid);
        $relative = $this->workspaceHash($spaceUid) . '/' . $spaceUid . '/' . $this->datedRelativePath() . '/' . $safeName;
        $targetPath = $this->workspacePolicy->forWrite($relative);
        if ($targetPath === null) {
            throw new RuntimeException('Invalid workspace storage path.');
        }
        $targetDir = dirname($targetPath);
        $this->ensureDir($targetDir);
        $this->writeFile($targetPath, $contentOrPath, $isTempPath);
        return $targetPath;
    }

    /**
     * Build a dated path (no write) for a global file.
     */
    public function pathForGlobal(string $fileName, ?\DateTimeInterface $date = null): string
    {
        $safeName = $this->sanitizeFileName($fileName);
        $target = $this->globalPolicy->forWrite($this->datedRelativePath($date) . '/' . $safeName);
        if ($target === null) {
            throw new RuntimeException('Invalid global storage path.');
        }
        return $target;
    }

    /**
     * Build a dated path (no write) for a workspace file.
     */
    public function pathForWorkspace(string $spaceUid, string $fileName, ?\DateTimeInterface $date = null): string
    {
        $safeName = $this->sanitizeFileName($fileName);
        $spaceUid = $this->normalizeWorkspaceUid($spaceUid);
        $relative = $this->workspaceHash($spaceUid) . '/' . $spaceUid . '/' . $this->datedRelativePath($date) . '/' . $safeName;
        $target = $this->workspacePolicy->forWrite($relative);
        if ($target === null) {
            throw new RuntimeException('Invalid workspace storage path.');
        }
        return $target;
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

    private function datedRelativePath(?\DateTimeInterface $date = null): string
    {
        return ltrim($this->datedPath('', $date), '/');
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
        return $this->globalPolicy->relative($path) !== null
            || $this->workspacePolicy->relative($path) !== null;
    }

    private function normalizeWorkspaceUid(string $uid): string
    {
        $uid = strtolower(trim($uid));
        if (str_contains($uid, '/') || str_contains($uid, '\\') || str_contains($uid, '..') || str_contains($uid, "\0")) {
            throw new RuntimeException('Invalid workspace identifier.');
        }
        $uid = preg_replace('/[^a-z0-9]+/', '', $uid) ?? '';
        if ($uid === '') {
            throw new RuntimeException('Invalid workspace identifier.');
        }
        return $uid;
    }
}
