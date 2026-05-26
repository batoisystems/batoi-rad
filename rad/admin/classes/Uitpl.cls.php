<?php
namespace RadAdmin;

use Core\Sys\FileVersionService;

class Uitpl {
    private const CHANNEL = 'uitpl';
    private $runData = [];
    private $db;
    private $errorHandler;
    private FileVersionService $versionService;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $this->versionService = new FileVersionService($runData['config'] ?? [], function () {
            return $this->runData['entity']['fullname'] ?? ($this->runData['entity']['username'] ?? 'Unknown');
        });
    }

    public function view() {
        $this->runData['route']['h1'] = 'UI Templates';
        $this->runData['route']['meta_title'] = 'UI Templates';
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'Manage embedded UI templates stored in rad/data/uitpl.';
        $filters = [
            'q' => trim($this->runData['request']->get['q'] ?? ''),
        ];
        $templates = $this->listTemplates();
        if ($filters['q'] !== '') {
            $needle = strtolower($filters['q']);
            $templates = array_values(array_filter($templates, function ($tpl) use ($needle) {
                return strpos(strtolower($tpl['relative'] ?? ''), $needle) !== false;
            }));
        }
        $this->runData['data']['templates'] = $templates;
        $this->runData['data']['filters'] = $filters;
        return $this->runData;
    }

    public function viewone() {
        $relative = $this->decodePath($this->runData['route']['pathparts'][3] ?? '');
        $relative = $this->sanitizeRelativePath($relative);
        if ($relative === '') {
            throw new \Exception('Invalid template path', 404);
        }
        $filePath = $this->resolveTemplatePath($relative);
        if (!is_file($filePath)) {
            throw new \Exception('Template not found', 404);
        }
        $stats = $this->collectStats($filePath);
        $versions = $this->versionService->listVersions(self::CHANNEL, $relative);
        $this->runData['route']['h1'] = 'Template: <code>' . $relative . '</code>';
        $this->runData['route']['meta_title'] = 'UI Template - ' . $relative;
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/uitpl/view';
        $this->runData['data']['template'] = [
            'relative' => $relative,
            'path' => $filePath,
            'stats' => $stats,
            'versions' => $versions,
        ];
        return $this->runData;
    }

    public function add() {
        $this->runData['route']['h1'] = 'Add UI Template';
        $this->runData['route']['meta_title'] = 'Add UI Template';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/uitpl/view';
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Create a new template under rad/data/uitpl.';
        }

        $request = $this->runData['request'];
        if (strtoupper($request->method) === 'POST') {
            $rawPath = trim($request->post['template_path'] ?? '');
            $content = $request->post['template_content'] ?? '';
            $relative = $this->sanitizeRelativePath($rawPath);
            if ($relative === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Template path is invalid.';
                return $this->runData;
            }
            $relative = $this->ensureExtension($relative);
            $filePath = $this->resolveTemplatePath($relative);
            if (file_exists($filePath)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Template already exists.';
                return $this->runData;
            }
            $dir = dirname($filePath);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Failed to create template folder.';
                return $this->runData;
            }
            if (file_put_contents($filePath, $content) === false) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Failed to create template.';
                return $this->runData;
            }
            $this->versionService->snapshot(self::CHANNEL, $relative, $content, [
                'note' => 'Template created',
            ]);
            $this->runData['request']->setAlert('Template created successfully.', 'success');
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/edit/' . $this->encodePath($relative);
            header("Location: {$redirectUrl}");
            exit;
        }

        return $this->runData;
    }

    public function edit() {
        $relative = $this->decodePath($this->runData['route']['pathparts'][3] ?? '');
        $relative = $this->sanitizeRelativePath($relative);
        if ($relative === '') {
            throw new \Exception('Invalid template path', 404);
        }
        $filePath = $this->resolveTemplatePath($relative);
        if (!is_file($filePath)) {
            throw new \Exception('Template not found', 404);
        }

        $request = $this->runData['request'];
        if (strtoupper($request->method) === 'POST') {
            $content = $request->post['template_content'] ?? '';
            if (file_put_contents($filePath, $content) === false) {
                $this->runData['request']->setAlert('Failed to save template.', 'danger');
            } else {
                $this->versionService->snapshot(self::CHANNEL, $relative, $content, [
                    'note' => 'Template updated',
                ]);
                $this->runData['request']->setAlert('Template saved.', 'success');
                $redirectUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/viewone/' . $this->encodePath($relative);
                header("Location: {$redirectUrl}");
                exit;
            }
        }

        $this->runData['route']['h1'] = 'Edit Template: <code>' . $relative . '</code>';
        $this->runData['route']['meta_title'] = 'Edit UI Template';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/uitpl/viewone/' . $this->encodePath($relative);
        $this->runData['data']['template'] = [
            'relative' => $relative,
            'path' => $filePath,
            'content' => file_get_contents($filePath) ?: '',
        ];
        return $this->runData;
    }

    public function downloadversion() {
        $relative = $this->decodePath($this->runData['route']['pathparts'][3] ?? '');
        $relative = $this->sanitizeRelativePath($relative);
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][4] ?? '');
        if ($relative === '' || $versionId === '') {
            throw new \Exception('Invalid version', 404);
        }
        $version = $this->versionService->fetchVersion(self::CHANNEL, $relative, $versionId);
        if (!$version) {
            throw new \Exception('Version not found', 404);
        }
        $fileName = basename($relative) . '-' . $versionId . '.php';
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo $version['content'] ?? '';
        exit;
    }

    public function restoreversion() {
        $relative = $this->decodePath($this->runData['route']['pathparts'][3] ?? '');
        $relative = $this->sanitizeRelativePath($relative);
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][4] ?? '');
        $redirect = $this->runData['route']['rad_admin_url'] . '/uitpl/viewone/' . $this->encodePath($relative);
        if ($relative === '' || $versionId === '' || strtoupper($this->runData['request']->method) !== 'POST') {
            header("Location: {$redirect}");
            exit;
        }
        $version = $this->versionService->fetchVersion(self::CHANNEL, $relative, $versionId);
        if (!$version) {
            $this->runData['request']->setAlert('Version not found.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $filePath = $this->resolveTemplatePath($relative);
        if (file_put_contents($filePath, $version['content'] ?? '') === false) {
            $this->runData['request']->setAlert('Failed to restore template.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $this->versionService->snapshot(self::CHANNEL, $relative, $version['content'] ?? '', [
            'note' => 'Restored from version ' . $versionId,
        ]);
        $this->runData['request']->setAlert('Template restored.', 'success');
        header("Location: {$redirect}");
        exit;
    }

    public function diffversion() {
        $relative = $this->decodePath($this->runData['route']['pathparts'][3] ?? '');
        $relative = $this->sanitizeRelativePath($relative);
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][4] ?? '');
        if ($relative === '' || $versionId === '') {
            throw new \Exception('Invalid version', 404);
        }
        $filePath = $this->resolveTemplatePath($relative);
        $current = is_file($filePath) ? (file_get_contents($filePath) ?: '') : '';
        $diff = $this->versionService->diff(self::CHANNEL, $relative, $versionId, $current);
        $this->runData['route']['h1'] = 'Diff: <code>' . $relative . '</code>';
        $this->runData['route']['meta_title'] = 'Template Diff';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/uitpl/viewone/' . $this->encodePath($relative);
        $this->runData['data']['diff'] = $diff;
        $this->runData['data']['template'] = [
            'relative' => $relative,
            'version' => $versionId,
        ];
        return $this->runData;
    }

    public function preview() {
        $relative = $this->decodePath($this->runData['route']['pathparts'][3] ?? '');
        $relative = $this->sanitizeRelativePath($relative);
        if ($relative === '') {
            throw new \Exception('Invalid template path', 404);
        }
        $filePath = $this->resolveTemplatePath($relative);
        if (!is_file($filePath)) {
            throw new \Exception('Template not found', 404);
        }
        $config = $this->runData['config'] ?? [];
        $data = $this->runData['data'] ?? [];
        $runData = $this->runData;
        ob_start();
        include $filePath;
        $output = ob_get_clean();
        header('Content-Type: text/html; charset=utf-8');
        echo $output;
        exit;
    }

    private function listTemplates(): array {
        $base = $this->baseDir();
        $templates = [];
        if (!is_dir($base)) {
            return $templates;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (!preg_match('/\\.php$/', $name)) {
                continue;
            }
            $full = $file->getPathname();
            $relative = ltrim(str_replace($base, '', $full), DIRECTORY_SEPARATOR);
            $templates[] = [
                'relative' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
            ];
        }
        usort($templates, function ($a, $b) {
            return ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0);
        });
        return $templates;
    }

    private function collectStats(string $filePath): array {
        $size = filesize($filePath);
        $modified = filemtime($filePath);
        return [
            'size_bytes' => $size,
            'size_human' => $this->formatBytes($size),
            'modified' => $modified,
        ];
    }

    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float)$bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return number_format($size, 1) . ' ' . $units[$i];
    }

    private function baseDir(): string {
        return rtrim($this->runData['config']['dir']['rad'] ?? '', '/') . '/data/uitpl';
    }

    private function resolveTemplatePath(string $relative): string {
        return rtrim($this->baseDir(), '/') . '/' . ltrim($relative, '/');
    }

    private function sanitizeRelativePath(string $path): string {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return '';
        }
        if (strpos($path, '..') !== false) {
            return '';
        }
        $path = ltrim($path, '/');
        if (!preg_match('/^[a-zA-Z0-9_\\-\\/\\.]+$/', $path)) {
            return '';
        }
        return $path;
    }

    private function ensureExtension(string $relative): string {
        if (preg_match('/\\.php$/', $relative)) {
            return $relative;
        }
        return $relative . '.php';
    }

    private function encodePath(string $relative): string {
        return rtrim(strtr(base64_encode($relative), '+/', '-_'), '=');
    }

    private function decodePath(string $encoded): string {
        $encoded = trim($encoded);
        if ($encoded === '') {
            return '';
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }

    private function sanitizeVersionId(string $versionId): string {
        $versionId = trim($versionId);
        if ($versionId === '') {
            return '';
        }
        return preg_match('/^[a-zA-Z0-9_\\-]+$/', $versionId) ? $versionId : '';
    }
}
