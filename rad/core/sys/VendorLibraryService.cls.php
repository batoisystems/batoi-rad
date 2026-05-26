<?php
namespace Core\Sys;

use RuntimeException;
use ZipArchive;

class VendorLibraryService {
    private $vendorDir;
    private $packagesDir;
    private $vendorDirReal;
    private $timezone;

    public function __construct(array $config) {
        $dir = $config['dir']['vendor'] ?? dirname(__DIR__, 2) . '/vendor';
        $this->vendorDir = rtrim($dir, '/');
        $this->vendorDirReal = rtrim(realpath($this->vendorDir) ?: $this->vendorDir, '/');
        $this->packagesDir = $this->vendorDir . '/_packages';
        $this->timezone = TimeHelper::resolveTimezone($config['sys']['timezone'] ?? null, 'UTC');
    }

    public function describeFilesystem(string $handle, array $record = []): array {
        $path = $this->resolvePath($handle, $record);
        $exists = is_dir($path);
        $size = $exists ? $this->calculateDirectorySize($path) : 0;
        $modified = $exists ? $this->getLastModifiedTimestamp($path) : null;
        $version = $exists ? $this->detectVersion($path) : null;

        return [
            'path' => $path,
            'exists' => $exists,
            'installed' => $exists,
            'size_bytes' => $size,
            'size_human' => $this->humanBytes($size),
            'last_modified' => $modified,
            'last_modified_human' => $modified ? TimeHelper::formatUtc($modified, $this->timezone, 'M d, Y H:i') : null,
            'version' => $version,
        ];
    }

    public function linkExistingLibrary(string $handle, array $record = []): array {
        $info = $this->describeFilesystem($handle, $record);
        if (!$info['exists']) {
            throw new RuntimeException('Library directory not found under vendor/. Upload a package to install it.');
        }
        return [
            'path' => $info['path'],
            'version' => $info['version'],
            'size_bytes' => $info['size_bytes'],
        ];
    }

    public function storeUploadedArchive(array $file, string $handle): string {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed. Please choose a ZIP archive.');
        }
        $ext = strtolower(pathinfo($file['name'] ?? 'package.zip', PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            throw new RuntimeException('Only ZIP archives are supported.');
        }
        $this->ensurePackagesDir();
        $safeHandle = preg_replace('/[^a-z0-9_\-]/i', '-', $handle);
        $target = $this->packagesDir . '/' . $safeHandle . '-' . date('YmdHis') . '.zip';
        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            if (!@rename($file['tmp_name'], $target)) {
                throw new RuntimeException('Unable to store the uploaded archive.');
            }
        }
        return $target;
    }

    public function installFromArchive(string $handle, string $archivePath, array $record = []): array {
        if (!is_file($archivePath)) {
            throw new RuntimeException('Package archive missing.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is required to extract vendor packages.');
        }
        $this->assertPathWithinVendorRoot($this->resolvePath($handle, $record));

        $tmpDir = $this->vendorDir . '/.tmp-' . uniqid($handle, true);
        if (!@mkdir($tmpDir, 0775, true)) {
            throw new RuntimeException('Cannot prepare temporary directory for extraction.');
        }

        $zip = new ZipArchive();
        try {
            if ($zip->open($archivePath) !== true) {
                throw new RuntimeException('Unable to open ZIP archive.');
            }
            $this->validateArchiveEntries($zip);
            if (!$zip->extractTo($tmpDir)) {
                throw new RuntimeException('Unable to extract archive contents.');
            }
            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            $this->rrmdir($tmpDir);
            throw $e;
        }

        $root = $this->detectExtractionRoot($tmpDir);
        $destination = $this->resolvePath($handle, $record);
        $this->assertPathWithinVendorRoot($destination);
        $this->replaceDirectory($destination, $root);
        if ($root !== $tmpDir && is_dir($tmpDir)) {
            $this->rrmdir($tmpDir);
        }

        $info = $this->describeFilesystem($handle, $record);
        return [
            'path' => $info['path'],
            'version' => $info['version'],
            'size_bytes' => $info['size_bytes'],
        ];
    }

    public function packagesDirectory(): string {
        $this->ensurePackagesDir();
        return $this->packagesDir;
    }

    private function ensurePackagesDir(): void {
        if (!is_dir($this->packagesDir)) {
            @mkdir($this->packagesDir, 0775, true);
        }
    }

    private function resolvePath(string $handle, array $record): string {
        if (!empty($record['s_install_path'])) {
            $path = $record['s_install_path'];
            $path = is_dir($path) ? $path : $this->vendorDir . '/' . ltrim($path, '/');
            $this->assertPathWithinVendorRoot($path);
            return rtrim($path, '/');
        }
        $path = $this->vendorDir . '/' . $handle;
        $this->assertPathWithinVendorRoot($path);
        return rtrim($path, '/');
    }

    private function detectVersion(string $path): ?string {
        $composer = $path . '/composer.json';
        if (is_file($composer)) {
            $data = json_decode(file_get_contents($composer), true);
            if (is_array($data)) {
                if (!empty($data['version'])) {
                    return (string)$data['version'];
                }
                if (!empty($data['extra']['branch-alias'])) {
                    $alias = reset($data['extra']['branch-alias']);
                    if ($alias) {
                        return (string)$alias;
                    }
                }
            }
        }
        $candidates = ['VERSION', 'VERSION.txt', 'version.txt'];
        foreach ($candidates as $candidate) {
            $file = $path . '/' . $candidate;
            if (is_file($file)) {
                $contents = trim((string)file_get_contents($file));
                if ($contents !== '') {
                    return $contents;
                }
            }
        }
        return null;
    }

    private function detectExtractionRoot(string $tmpDir): string {
        $items = array_values(array_diff(scandir($tmpDir) ?: [], ['.', '..']));
        if (count($items) === 1) {
            $single = $tmpDir . '/' . $items[0];
            if (is_dir($single)) {
                return $single;
            }
        }
        return $tmpDir;
    }

    private function replaceDirectory(string $destination, string $source): void {
        if (is_dir($destination)) {
            $this->rrmdir($destination);
        }
        $parent = dirname($destination);
        if (!is_dir($parent)) {
            @mkdir($parent, 0775, true);
        }
        if (!@rename($source, $destination)) {
            $this->recursiveCopy($source, $destination);
            $this->rrmdir($source);
        }
    }

    private function validateArchiveEntries(ZipArchive $zip): void {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'] ?? '';
            if ($name === '') {
                continue;
            }
            if (strpos($name, '/') === 0 || strpos($name, '..') !== false) {
                throw new RuntimeException('Archive contains unsafe paths.');
            }
            $externalAttributes = $stat['external_attributes'] ?? 0;
            $isSymlink = ($externalAttributes & 0xA000) === 0xA000;
            if ($isSymlink) {
                throw new RuntimeException('Archive contains symbolic links. Upload a sanitized ZIP.');
            }
        }
    }

    private function assertPathWithinVendorRoot(string $path): void {
        $real = realpath($path);
        $normalizedVendor = rtrim($this->vendorDirReal ?: $this->vendorDir, '/');

        if ($real === false) {
            $candidate = $normalizedVendor . '/' . ltrim($path, '/');
            $real = $this->normalizePath($candidate);
        }

        if (strpos($real, $normalizedVendor . '/') !== 0
            && $real !== $normalizedVendor) {
            throw new RuntimeException('Install path must be inside the vendor directory.');
        }
    }

    private function normalizePath(string $path): string {
        $segments = [];
        $parts = explode('/', str_replace('\\', '/', $path));
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $part;
        }
        return '/' . implode('/', $segments);
    }

    private function calculateDirectorySize(string $path): int {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    private function getLastModifiedTimestamp(string $path): ?int {
        $last = null;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $time = $file->getMTime();
            if (!$last || $time > $last) {
                $last = $time;
            }
        }
        return $last;
    }

    private function rrmdir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    private function recursiveCopy(string $source, string $destination): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    @mkdir($targetPath, 0775, true);
                }
            } else {
                @copy($item->getPathname(), $targetPath);
            }
        }
    }

    private function humanBytes(int $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B','KB','MB','GB','TB'];
        $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
    }
}
