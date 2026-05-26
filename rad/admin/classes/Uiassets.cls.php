<?php
namespace RadAdmin;
use Core\Sys\PrivilegeService;
use Core\Sys\TimeHelper;
class Uiassets{
    use AiAssistAware;
    private $runData = [];
    private $db;
    private $errorHandler;
    private PrivilegeService $priv;
    private $textEditableExtensions = [
        'css','scss','less','js','ts','json','xml','svg','txt','md','html','htm','php','ini','yml','yaml'
    ];
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }
    
    /**
     * View asset browser
     */
    public function view() {
        if (!is_dir($this->runData['config']['dir']['assets'])) {
            throw new \Exception('Invalid Theme', 404);
        }

        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
        ];

        $relativePath = $this->getRelativeAssetPath();
        $pathSegments = $relativePath === '' ? [] : explode('/', $relativePath);
        $assetBaseDir = $this->getAssetRoot();
        $currentDir = $relativePath === '' ? $assetBaseDir : $assetBaseDir . '/' . $relativePath;
        if (!is_dir($currentDir)) {
            throw new \Exception('Invalid UI Assets Directory', 404);
        }

        $this->runData['data']['innerAssetDirectory'] = $relativePath;
        $this->runData['data']['assetStats'] = $this->summarizeDirectory($currentDir);
        $this->runData['data']['filters'] = $filters;

        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'Browse folders, upload assets, and preview files from this workspace.';
        $this->runData['route']['h1'] = $relativePath ? 'UI Assets / ' . $relativePath : 'UI Assets';
        $this->runData['route']['meta_title'] = 'UI Assets';

        $breadcrumb = [
            'UI Assets' => $this->runData['route']['rad_admin_url'] . '/uiassets/view',
        ];
        $buildPath = '';
        foreach ($pathSegments as $segment) {
            $buildPath .= '/' . $segment;
            $breadcrumb[$segment] = $this->runData['route']['rad_admin_url'] . '/uiassets/view' . $buildPath;
        }
        $this->runData['route']['breadcrumb'] = $breadcrumb;

        if (!empty($pathSegments)) {
            $parentSegments = $pathSegments;
            array_pop($parentSegments);
            $parentPath = implode('/', $parentSegments);
            $encodedParent = $parentPath ? '/' . $this->encodePathSegments($parentPath) : '';
            $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/uiassets/view' . $encodedParent;
        } else {
            $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/home/view';
        }

        return $this->runData;
    }    

    /**
     * View trashed/archived assets
     */
    public function trash() {
        $items = $this->collectArchivedItems();
        $totalSize = 0;
        $latest = null;
        foreach ($items as $entry) {
            $totalSize += (int)($entry['size'] ?? 0);
            if ($latest === null || (($entry['archived_at'] ?? 0) > $latest)) {
                $latest = $entry['archived_at'] ?? null;
            }
        }
        $stats = [
            'count' => count($items),
            'size' => $totalSize,
            'size_readable' => $this->formatBytes($totalSize),
            'latest' => $latest,
        ];
        $this->runData['data']['archived_stats'] = $stats;

        $this->runData['route']['h1'] = 'Archived Assets';
        $this->runData['route']['meta_title'] = 'Archived Assets';
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'Restore files from trash or permanently delete them. Items stay here until purged.';

        $this->runData['route']['breadcrumb'] = [
            'UI Assets' => $this->runData['route']['rad_admin_url'] . '/uiassets/view',
            'Trash' => ''
        ];
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/uiassets/view';

        return $this->runData;
    }

    /**
     * Fetch files and folders from the theme assets folder
     */
    public function fetchfiles() {
        header('Content-Type: application/json');
        try {
            $assetRoot = $this->getAssetRoot();
            $subPath = $this->getRelativeAssetPath();
            $targetDir = $subPath === '' ? $assetRoot : $assetRoot . '/' . $subPath;
            if (!is_dir($targetDir)) {
                throw new \Exception('Invalid UI Assets Directory', 404);
            }

            $realTargetDir = realpath($targetDir);
            if ($realTargetDir === false || strpos($realTargetDir, $assetRoot) !== 0) {
                throw new \Exception('Invalid asset path', 400);
            }

            $files = [];
            $radAdminBase = rtrim($this->runData['route']['rad_admin_url'], '/');
            $publicBase = rtrim($this->runData['config']['sys']['base_url'], '/') . '/assets';

            foreach (new \DirectoryIterator($realTargetDir) as $file) {
                if ($file->isDot() || preg_match('/^[._]/', $file->getFilename())) {
                    continue;
                }

                $isDir = $file->isDir();
                $extension = $isDir ? '' : strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                $relative = ltrim(($subPath ? $subPath . '/' : '') . $file->getFilename(), '/');
                $encodedRelative = $this->encodePathSegments($relative);
                $size = $isDir ? null : $file->getSize();
                $lastUpdatedRaw = $file->getMTime();
                $lastUpdated = $this->formatTimestamp($lastUpdatedRaw);
                $isEditable = !$isDir && $this->isTextEditable($extension);

                if ($isDir) {
                    $link = $radAdminBase . '/uiassets/view' . ($encodedRelative ? '/' . $encodedRelative : '');
                    $publicUrl = null;
                } else {
                    $link = $isEditable
                        ? $radAdminBase . '/uiassets/editfile?path=' . rawurlencode($relative)
                        : $publicBase . ($encodedRelative ? '/' . $encodedRelative : '');
                    $publicUrl = $publicBase . ($encodedRelative ? '/' . $encodedRelative : '');
                }

                $files[] = [
                    'name' => $file->getFilename(),
                    'icon' => $this->getFileIcon($isDir ? 'folder' : $extension),
                    'isDir' => $isDir,
                    'extension' => $extension,
                    'type' => $isDir ? 'folder' : $extension,
                    'relative' => $relative,
                    'relativePath' => $relative,
                    'link' => $link,
                    'publicUrl' => $publicUrl,
                    'size' => $size,
                    'sizeReadable' => $size !== null ? $this->formatBytes($size) : null,
                    'lastUpdated' => $lastUpdated,
                    'lastUpdatedRaw' => $lastUpdatedRaw,
                    'isTextEditable' => $isEditable
                ];
            }

            usort($files, function ($a, $b) {
                if ($a['isDir'] === $b['isDir']) {
                    return strcasecmp($a['name'], $b['name']);
                }
                return $a['isDir'] ? -1 : 1;
            });

            echo json_encode($files);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Upload files to the theme assets folder
     */
    public function uploadfiles() {
        if (!$this->priv->can('asset_upload')) {
            http_response_code(403);
            echo 'Access denied.';
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            if (!isset($_FILES['files'])) {
                throw new \Exception('No files uploaded.', 400);
            }

            $targetSubPath = $this->getRelativeAssetPath();
            $target_dir = $targetSubPath === '' ? $this->getAssetRoot() : $this->resolveAssetPath($targetSubPath, false);
            if (!is_dir($target_dir) && !mkdir($target_dir, 0777, true) && !is_dir($target_dir)) {
                throw new \Exception('Unable to create target directory.', 500);
            }

            foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) {
                    throw new \Exception('File upload error: ' . $_FILES['files']['error'][$index], 400);
                }

                $originalName = basename($_FILES['files']['name'][$index]);
                $target_file = rtrim($target_dir, '/') . '/' . $originalName;

                if (!move_uploaded_file($tmpName, $target_file)) {
                    throw new \Exception('Failed to move uploaded file.', 500);
                }
            }

            http_response_code(200);
            echo 'Success';
        } catch (\Exception $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo $e->getMessage();
        }
    }        

    /**
     * Find file bootstrap icons based on extension
     */
    private function getFileIcon($file_type) {
        switch($file_type) {
            case 'folder':
                return '<i class="bi bi-folder"></i>';
            case 'png':
            case 'jpg':
            case 'jpeg':
            case 'gif':
            case 'svg':
                return '<i class="bi bi-image"></i>';
            case 'pdf':
                return '<i class="bi bi-file-earmark-pdf"></i>';
            case 'doc':
            case 'docx':
                return '<i class="bi bi-file-earmark-word"></i>';
            case 'xls':
            case 'xlsx':
                return '<i class="bi bi-file-earmark-excel"></i>';
            case 'ppt':
            case 'pptx':
                return '<i class="bi bi-file-earmark-ppt"></i>';
            case 'zip':
            case 'rar':
            case 'tar':
            case 'gz':
                return '<i class="bi bi-file-earmark-zip"></i>';
            case 'txt':
                return '<i class="bi bi-file-earmark-text"></i>';
            default:
                return '<i class="bi bi-file-earmark"></i>';
        }
    }

    private function formatBytes($bytes) {
        if ($bytes === null || $bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int)floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        return round($bytes / pow(1024, $power), 1) . ' ' . $units[$power];
    }

    private function formatTimestamp(int $timestamp): string {
        if ($timestamp <= 0) {
            return '';
        }
        $timezone = TimeHelper::resolveTimezone(
            $this->runData['entity']['timezone'] ?? null,
            $this->runData['config']['sys']['timezone_default'] ?? null
        );
        return TimeHelper::formatUtc($timestamp, $timezone, 'M j, Y H:i') ?? '';
    }

    private function encodePathSegments(string $path): string {
        if ($path === '') {
            return '';
        }
        $segments = array_map('rawurlencode', array_filter(explode('/', $path), function ($segment) {
            return $segment !== '';
        }));
        return implode('/', $segments);
    }

    private function isTextEditable(?string $extension): bool {
        if ($extension === null || $extension === '') {
            return false;
        }
        return in_array(strtolower($extension), $this->textEditableExtensions, true);
    }

    private function getAssetRoot(): string {
        $root = realpath($this->runData['config']['dir']['assets']);
        if ($root === false || !is_dir($root)) {
            throw new \Exception('Invalid UI Assets Directory', 404);
        }
        return rtrim($root, '/');
    }

    private function sanitizeRelativePath(?string $path): string {
        if ($path === null) {
            return '';
        }
        $path = trim(str_replace("\0", '', $path));
        if ($path === '') {
            return '';
        }
        $segments = array_filter(explode('/', $path), function ($segment) {
            return $segment !== '' && $segment !== '.' && $segment !== '..';
        });
        return implode('/', $segments);
    }

    private function getRelativeAssetPath(int $offset = 3): string {
        $routeParts = $this->runData['route']['pathparts'] ?? [];
        if (isset($routeParts[$offset]) && $routeParts[$offset] !== '') {
            $segments = array_slice($routeParts, $offset);
            $segments = array_values(array_filter($segments, function ($segment) {
                return $segment !== '';
            }));
            if (!empty($segments)) {
                return $this->sanitizeRelativePath(implode('/', $segments));
            }
        }
        return $this->sanitizeRelativePath($this->runData['request']->get['path'] ?? '');
    }

    private function resolveAssetPath(string $relativePath, bool $mustExist = true): string {
        $assetRoot = $this->getAssetRoot();
        $relativePath = $this->sanitizeRelativePath($relativePath);
        $targetPath = $relativePath === '' ? $assetRoot : $assetRoot . '/' . $relativePath;

        if ($mustExist) {
            $real = realpath($targetPath);
            if ($real === false || strpos($real, $assetRoot) !== 0) {
                throw new \Exception('Asset not found or inaccessible', 404);
            }
            return $real;
        }

        $parent = $relativePath === '' ? $assetRoot : dirname($targetPath);
        if (!is_dir($parent)) {
            if (!mkdir($parent, 0777, true) && !is_dir($parent)) {
                throw new \Exception('Unable to prepare directory for asset', 500);
            }
        }
        $parentReal = realpath($parent);
        if ($parentReal === false || strpos($parentReal, $assetRoot) !== 0) {
            throw new \Exception('Invalid asset path', 400);
        }

        return $targetPath;
    }

    private function buildAssetBreadcrumb(string $relativePath): array {
        $breadcrumb = [
            'UI Assets' => $this->runData['route']['rad_admin_url'] . '/uiassets/view',
        ];
        if ($relativePath === '') {
            return $breadcrumb;
        }
        $segments = explode('/', $relativePath);
        $path = '';
        foreach ($segments as $segment) {
            $path .= '/' . $segment;
            $breadcrumb[$segment] = $this->runData['route']['rad_admin_url'] . '/uiassets/view' . $path;
        }
        return $breadcrumb;
    }

    private function buildAssetBacklink(string $relativePath): string {
        if ($relativePath === '') {
            return $this->runData['route']['rad_admin_url'] . '/uiassets/view';
        }
        $segments = explode('/', $relativePath);
        array_pop($segments);
        $parentPath = implode('/', $segments);
        $encoded = $parentPath ? '/' . $this->encodePathSegments($parentPath) : '';
        return $this->runData['route']['rad_admin_url'] . '/uiassets/view' . $encoded;
    }

    private function getArchiveDir(): string {
        $dir = $this->getAssetRoot() . '/.archive';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function deleteNode(string $path): void {
        if (is_dir($path)) {
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $this->deleteNode($path . DIRECTORY_SEPARATOR . $item);
            }
            rmdir($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    private function calculateNodeSize(string $path): int {
        if (is_file($path)) {
            return filesize($path) ?: 0;
        }
        if (!is_dir($path)) {
            return 0;
        }
        $size = 0;
        foreach (scandir($path) as $node) {
            if ($node === '.' || $node === '..') {
                continue;
            }
            $size += $this->calculateNodeSize($path . DIRECTORY_SEPARATOR . $node);
        }
        return $size;
    }

    private function collectArchivedItems(): array {
        $archiveRoot = $this->getArchiveDir();
        $items = [];
        foreach (new \DirectoryIterator($archiveRoot) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }
            $metaFile = $entry->getPathname() . '/meta.json';
            if (!is_file($metaFile)) {
                continue;
            }
            $meta = json_decode(file_get_contents($metaFile), true);
            if (!$meta) {
                continue;
            }
            $meta['id'] = $entry->getFilename();
            $items[] = $meta;
        }
        usort($items, function ($a, $b) {
            return ($b['archived_at'] ?? 0) <=> ($a['archived_at'] ?? 0);
        });
        return $items;
    }

    private function loadArchiveMeta(string $id): array {
        $archiveRoot = $this->getArchiveDir();
        $entryDir = $archiveRoot . '/' . $this->sanitizeRelativePath($id);
        $metaFile = $entryDir . '/meta.json';
        if (!is_dir($entryDir) || !is_file($metaFile)) {
            throw new \Exception('Archive entry not found.', 404);
        }
        $meta = json_decode(file_get_contents($metaFile), true);
        if (!$meta) {
            throw new \Exception('Archive metadata is invalid.', 500);
        }
        $meta['id'] = basename($entryDir);
        $meta['payload_path'] = $entryDir . '/payload';
        return $meta;
    }

    public function download() {
        try {
            $relative = $this->getRelativeAssetPath(3);
            if ($relative === '') {
                throw new \Exception('Invalid file reference', 404);
            }

            $filePath = $this->resolveAssetPath($relative, true);
            if (!is_file($filePath)) {
                throw new \Exception('File not found', 404);
            }

            $mime = mime_content_type($filePath) ?: 'application/octet-stream';
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename=' . basename($filePath));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function rename() {
        header('Content-Type: application/json');
        try {
            if (!$this->priv->can('asset_upload')) {
                throw new \Exception('Access denied.', 403);
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new \Exception('Invalid payload', 400);
            }

            $oldRelative = $this->sanitizeRelativePath($data['path'] ?? $data['oldName'] ?? '');
            $newRelative = $this->sanitizeRelativePath($data['newPath'] ?? $data['newName'] ?? '');

            if ($oldRelative === '' || $newRelative === '') {
                throw new \Exception('Both source and destination are required.', 400);
            }

            $oldPath = $this->resolveAssetPath($oldRelative, true);
            $newPath = $this->resolveAssetPath($newRelative, false);

            if (file_exists($newPath)) {
                throw new \Exception('Target already exists.', 409);
            }

            if (!@rename($oldPath, $newPath)) {
                throw new \Exception('Error renaming file.', 500);
            }

            echo json_encode(['success' => true, 'path' => $newRelative]);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function move() {
        header('Content-Type: application/json');
        try {
            if (!$this->priv->can('asset_upload')) {
                throw new \Exception('Access denied.', 403);
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new \Exception('Invalid payload', 400);
            }

            $sourceRelative = $this->sanitizeRelativePath($data['path'] ?? $data['fileName'] ?? '');
            $destinationFolder = $this->sanitizeRelativePath($data['destination'] ?? $data['newLocation'] ?? '');
            if ($sourceRelative === '') {
                throw new \Exception('Source path required.', 400);
            }
            $filename = basename($sourceRelative);
            $targetRelative = ($destinationFolder ? $destinationFolder . '/' : '') . $filename;

            $sourcePath = $this->resolveAssetPath($sourceRelative, true);
            $targetPath = $this->resolveAssetPath($targetRelative, false);

            if (!@rename($sourcePath, $targetPath)) {
                throw new \Exception('Error moving file.', 500);
            }

            echo json_encode(['success' => true, 'path' => $targetRelative]);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete() {
        header('Content-Type: application/json');
        // system_admin only
        if ($this->priv->role() !== 'system_admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            return;
        }
        echo json_encode(['success' => false, 'message' => 'Direct delete is disabled. Archive the asset and use the trash to purge files.']);
    }

    public function uncompress() {
        header('Content-Type: application/json');
        try {
        if (!$this->priv->can('asset_upload')) {
            throw new \Exception('Access denied.', 403);
        }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new \Exception('Invalid payload', 400);
            }

            $relative = $this->sanitizeRelativePath($data['path'] ?? $data['fileName'] ?? '');
            if ($relative === '') {
                throw new \Exception('Archive path required.', 400);
            }

            $filePath = $this->resolveAssetPath($relative, true);
            if (!is_file($filePath)) {
                throw new \Exception('File not found', 404);
            }

            $zip = new \ZipArchive;
            if ($zip->open($filePath) !== true) {
                throw new \Exception('Error opening archive.', 500);
            }
            $zip->extractTo($this->getAssetRoot());
            $zip->close();

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function archive() {
        header('Content-Type: application/json');
        try {
            if ($this->priv->role() !== 'system_admin') {
                throw new \Exception('Access denied.', 403);
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new \Exception('Invalid payload', 400);
            }

            $relative = $this->sanitizeRelativePath($data['path'] ?? $data['fileName'] ?? '');
            if ($relative === '') {
                throw new \Exception('File path required for archive.', 400);
            }

            $sourcePath = $this->resolveAssetPath($relative, true);
            $isDir = is_dir($sourcePath);

            $archiveId = date('YmdHis') . '_' . bin2hex(random_bytes(3));
            $archiveEntryDir = $this->getArchiveDir() . '/' . $archiveId;
            if (!mkdir($archiveEntryDir, 0777, true) && !is_dir($archiveEntryDir)) {
                throw new \Exception('Unable to prepare archive directory.', 500);
            }

            $payloadPath = $archiveEntryDir . '/payload';
            if (!@rename($sourcePath, $payloadPath)) {
                rmdir($archiveEntryDir);
                throw new \Exception('Unable to move file into archive.', 500);
            }

            $size = $this->calculateNodeSize($payloadPath);
            $meta = [
                'original' => $relative,
                'name' => basename($relative),
                'is_dir' => $isDir,
                'size' => $size,
                'size_readable' => $size ? $this->formatBytes($size) : '0 B',
                'archived_at' => time(),
            ];
            file_put_contents($archiveEntryDir . '/meta.json', json_encode($meta));

            echo json_encode(['success' => true, 'id' => $archiveId]);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function preview() {
        try {
            $relative = $this->getRelativeAssetPath(3);
            if ($relative === '') {
                throw new \Exception('Invalid file reference', 404);
            }

            $filePath = $this->resolveAssetPath($relative, true);
            if (!is_file($filePath)) {
                throw new \Exception('File not found', 404);
            }

            $mime = mime_content_type($filePath) ?: 'application/octet-stream';
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo $e->getMessage();
        }
    }

    public function fetcharchived() {
        header('Content-Type: application/json');
        try {
            $items = $this->collectArchivedItems();
            echo json_encode($items);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function unarchive() {
        header('Content-Type: application/json');
        try {
            if ($this->priv->role() !== 'system_admin') {
                throw new \Exception('Access denied.', 403);
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id'])) {
                throw new \Exception('Archive entry id is required.', 400);
            }
            $entry = $this->loadArchiveMeta($data['id']);
            $payloadPath = $entry['payload_path'];
            $originalPath = $this->resolveAssetPath($entry['original'], false);

            if (file_exists($originalPath)) {
                throw new \Exception('Destination already exists. Please rename or remove the existing file before restoring.', 409);
            }

            $parentDir = dirname($originalPath);
            if (!is_dir($parentDir) && !mkdir($parentDir, 0777, true) && !is_dir($parentDir)) {
                throw new \Exception('Unable to prepare destination directory.', 500);
            }

            if (!@rename($payloadPath, $originalPath)) {
                throw new \Exception('Unable to restore asset from archive.', 500);
            }

            $entryDir = dirname($payloadPath);
            $this->deleteNode($entryDir);

            echo json_encode(['success' => true, 'path' => $entry['original']]);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function purgearchive() {
        header('Content-Type: application/json');
        try {
            if ($this->priv->role() !== 'system_admin') {
                throw new \Exception('Access denied.', 403);
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id'])) {
                throw new \Exception('Archive entry id is required.', 400);
            }
            $entry = $this->loadArchiveMeta($data['id']);
            $entryDir = dirname($entry['payload_path']);
            $this->deleteNode($entryDir);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function emptytrash() {
        header('Content-Type: application/json');
        try {
            if ($this->priv->role() !== 'system_admin') {
                throw new \Exception('Access denied.', 403);
            }
            $archiveRoot = $this->getArchiveDir();
            foreach (scandir($archiveRoot) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $this->deleteNode($archiveRoot . '/' . $item);
            }
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    /**
     * Edit an asset file via the Monaco editor
     */
    public function editfile() {
        if (!$this->priv->can('asset_upload')) {
            throw new \Exception('Access denied.', 403);
        }
        $relative = $this->getRelativeAssetPath();
        if ($relative === '') {
            throw new \Exception('Invalid asset selection', 404);
        }

        $filePath = $this->resolveAssetPath($relative, true);
        if (!is_file($filePath)) {
            throw new \Exception('Asset file not found', 404);
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!$this->isTextEditable($extension)) {
            throw new \Exception('This file type cannot be edited from RAD Admin.', 400);
        }

        $encodedPath = $this->encodePathSegments($relative);
        $publicUrl = rtrim($this->runData['config']['sys']['base_url'], '/') . '/assets' . ($encodedPath ? '/' . $encodedPath : '');
        $this->runData['data']['asset_relative'] = $relative;
        $this->runData['data']['asset_filename'] = basename($filePath);
        $this->runData['data']['asset_extension'] = $extension;
        $this->runData['data']['asset_content'] = file_get_contents($filePath);
        $this->runData['data']['asset_public_url'] = $publicUrl;

        $this->runData['route']['h1'] = 'Asset Editor - <code>' . basename($filePath) . '</code>';
        $this->runData['route']['meta_title'] = 'Edit Asset - ' . basename($filePath);
        $breadcrumb = $this->buildAssetBreadcrumb($this->sanitizeRelativePath(dirname($relative) === '.' ? '' : dirname($relative)));
        $breadcrumb[basename($filePath)] = '';
        $this->runData['route']['breadcrumb'] = $breadcrumb;
        $this->runData['route']['backlink'] = $this->buildAssetBacklink($relative);
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'Changes are saved directly to the asset inside <code>/assets</code>. Download a copy before editing if you need a backup.';

        return $this->runData;
    }

    /**
     * Persist changes made in the asset editor
     */
    public function savefile() {
        header('Content-Type: application/json');
        try {
            if (!$this->priv->can('asset_upload')) {
                throw new \Exception('Access denied.', 403);
            }
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!$payload || !isset($payload['path']) || !array_key_exists('content', $payload)) {
                throw new \Exception('Invalid data provided', 400);
            }

            $relative = $this->sanitizeRelativePath($payload['path']);
            if ($relative === '') {
                throw new \Exception('Missing asset path.', 400);
            }

            $filePath = $this->resolveAssetPath($relative, true);
            if (!is_writable($filePath)) {
                throw new \Exception('File is not writable.', 403);
            }

            if (file_put_contents($filePath, $payload['content']) === false) {
                throw new \Exception('Failed to save the content.', 500);
            }

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }    
    
    /**
     * AI Assist code for a Route
     */
    public function aiassist() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || !isset($data['content'])) {
            echo json_encode(['error' => 'Invalid data provided']);
            return;
        }
        if (!$this->priv->can('code_edit')) {
            echo json_encode(['error' => 'Access denied.']);
            return;
        }

        $relative = $this->sanitizeRelativePath($data['path'] ?? $this->runData['request']->get['path'] ?? '');
        if ($relative === '') {
            echo json_encode(['error' => 'Missing asset context for AI assist.']);
            return;
        }

        try {
            $service = $this->getAiAssistService('coding', 'full');
        } catch (\Throwable $e) {
            echo json_encode(['error' => 'AI service is unavailable.']);
            return;
        }

        $result = $service->suggest($data['content'], 'uiassets', [
            'asset' => $relative,
        ]);

        echo json_encode($result);
    }

    private function summarizeDirectory(string $dir): array {
        $stats = [
            'files' => 0,
            'folders' => 0,
            'size' => 0,
            'latest_name' => null,
            'latest_time' => 0,
        ];

        if (!is_dir($dir)) {
            return $stats;
        }

        foreach (new \DirectoryIterator($dir) as $node) {
            if ($node->isDot() || preg_match('/^[._]/', $node->getFilename())) {
                continue;
            }
            if ($node->isDir()) {
                $stats['folders']++;
            } else {
                $stats['files']++;
                $stats['size'] += $node->getSize();
            }
            if ($node->getMTime() > $stats['latest_time']) {
                $stats['latest_time'] = $node->getMTime();
                $stats['latest_name'] = $node->getFilename();
            }
        }

        return $stats;
    }
}
