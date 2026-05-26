<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;

class Filemanager {
    private $runData = [];
    private PrivilegeService $priv;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function tree() {
        $this->enforceCsrf();
        $this->enforcePrivilege();
        $rootKey = trim($this->runData['request']->get['root'] ?? 'ms');
        $relativePath = trim($this->runData['request']->get['path'] ?? '');
        $baseDir = $this->resolveBaseDir($rootKey);
        $targetDir = $this->resolveTargetDir($baseDir, $relativePath);
        if (!$targetDir || !is_dir($targetDir)) {
            $this->respondError('Invalid directory.');
        }
        $tree = $this->scanDirectory($baseDir, $targetDir);
        header('Content-Type: application/json');
        echo json_encode([
            'root' => $rootKey,
            'path' => $relativePath,
            'tree' => $tree,
        ]);
        exit;
    }

    public function read() {
        $this->enforceCsrf();
        $this->enforcePrivilege();
        $path = trim($this->runData['request']->post['path'] ?? '');
        $fullPath = $this->sanitizePath($path);
        if (!$fullPath || !is_file($fullPath)) {
            $this->respondError('File not found.');
        }
        header('Content-Type: application/json');
        echo json_encode([
            'path' => $path,
            'content' => file_get_contents($fullPath),
        ]);
        exit;
    }

    public function write() {
        $this->enforceCsrf();
        $this->enforcePrivilege();
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload || empty($payload['path']) || !isset($payload['content'])) {
            $this->respondError('Invalid payload.');
        }
        $fullPath = $this->sanitizePath($payload['path']);
        if (!$fullPath) {
            $this->respondError('Invalid path.');
        }
        if (!is_dir(dirname($fullPath))) {
            @mkdir(dirname($fullPath), 0775, true);
        }
        if (file_put_contents($fullPath, $payload['content']) === false) {
            $this->respondError('Failed to write file.');
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    private function respondError(string $message, int $code = 400) {
        header('Content-Type: application/json', true, $code);
        echo json_encode(['error' => $message]);
        exit;
    }

    private function enforcePrivilege(): void {
        if (!$this->priv->can('asset_upload')) {
            $this->respondError('Access denied.', 403);
        }
    }

    private function enforceCsrf() {
        $request = $this->runData['request'] ?? null;
        if (!$request) {
            $this->respondError('Unable to verify CSRF token.', 419);
        }
        $token = $this->extractCsrfToken($request);
        if (!$token || !$request->checkCSRFToken($token)) {
            $this->respondError('Invalid CSRF token.', 419);
        }
    }

    private function extractCsrfToken($request): string {
        $headers = array_change_key_case($request->headers ?? [], CASE_LOWER);
        if (!empty($headers['x-csrf-token'])) {
            return $headers['x-csrf-token'];
        }
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        if (!empty($request->post['csrf_token'])) {
            return $request->post['csrf_token'];
        }
        if (!empty($request->get['csrf_token'])) {
            return $request->get['csrf_token'];
        }
        return '';
    }

    private function resolveBaseDir(string $root): string {
        $allowedRoots = [
            'ms' => $this->runData['config']['dir']['ms'] ?? '',
            'theme' => $this->runData['config']['dir']['theme'] ?? '',
            'upgrade' => rtrim($this->runData['config']['dir']['rad'] ?? '', '/') . '/upgrades',
        ];
        if (isset($allowedRoots[$root]) && $allowedRoots[$root] !== '') {
            return $allowedRoots[$root];
        }
        return $allowedRoots['ms'];
    }

    private function resolveTargetDir(string $base, string $relative): ?string {
        $relative = trim($relative, '/');
        $path = $relative === '' ? $base : $base . '/' . $relative;
        $real = realpath($path);
        if ($real === false) {
            $real = $path;
        }
        $realBase = realpath($base) ?: $base;
        if (strpos($real, $realBase) !== 0) {
            return null;
        }
        return $real;
    }

    private function scanDirectory(string $root, string $path): array {
        $items = [];
        foreach (glob($path . '/*') ?: [] as $full) {
            $name = basename($full);
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (is_dir($full)) {
                $items[] = [
                    'type' => 'directory',
                    'name' => $name,
                    'path' => ltrim(str_replace($root, '', $full), '/'),
                    'children' => $this->scanDirectory($root, $full),
                ];
            } else {
                $items[] = [
                    'type' => 'file',
                    'name' => $name,
                    'path' => ltrim(str_replace($root, '', $full), '/'),
                ];
            }
        }
        return $items;
    }

    private function sanitizePath(string $relative): ?string {
        $relative = trim($relative, '/');
        $base = $this->runData['config']['dir']['rad'] ?? '';
        $full = realpath($base . '/' . $relative);
        if ($full === false) {
            $full = $base . '/' . $relative;
        }
        $realBase = realpath($base) ?: $base;
        if (strpos($full, $realBase) !== 0) {
            return null;
        }
        return $full;
    }
}
