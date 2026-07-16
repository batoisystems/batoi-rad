<?php
namespace RadAdmin;

use Batoi\Rad\Http\CsrfToken;
use Batoi\Rad\Http\JsonRequest;
use Batoi\Rad\Http\JsonResponse;
use Core\Sys\DeveloperToolPolicy;
use Core\Sys\SafePath;

class Filemanager {
    private $runData = [];
    private DeveloperToolPolicy $policy;
    private SafePath $paths;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->policy = new DeveloperToolPolicy($runData['config'] ?? [], $runData['entity'] ?? []);
        $this->paths = new SafePath((string)($runData['config']['dir']['rad'] ?? ''));
    }

    public function tree() {
        $this->enforceCsrf();
        $this->enforcePrivilege('source_read');
        $rootKey = trim($this->runData['request']->get['root'] ?? 'ms');
        $relativePath = trim($this->runData['request']->get['path'] ?? '');
        $baseDir = $this->resolveBaseDir($rootKey);
        $targetDir = $this->resolveTargetDir($baseDir, $relativePath);
        if (!$targetDir || !is_dir($targetDir)) {
            $this->respondError('Invalid directory.');
        }
        $tree = $this->scanDirectory($baseDir, $targetDir);
        JsonResponse::send([
            'root' => $rootKey,
            'path' => $relativePath,
            'tree' => $tree,
        ]);
    }

    public function read() {
        $this->enforceCsrf();
        $this->enforcePrivilege('source_read');
        $path = trim($this->runData['request']->post['path'] ?? '');
        $fullPath = $this->sanitizePath($path);
        if (!$fullPath || !is_file($fullPath)) {
            $this->respondError('File not found.');
        }
        JsonResponse::send([
            'path' => $path,
            'content' => file_get_contents($fullPath),
        ]);
    }

    public function write() {
        $this->enforceCsrf();
        $this->enforcePrivilege('source_write');
        try {
            $payload = JsonRequest::decode((string)($this->runData['request']->body ?? ''));
        } catch (\UnexpectedValueException $exception) {
            $this->respondError($exception->getMessage());
        }
        if (empty($payload['path']) || !isset($payload['content'])) {
            $this->respondError('Invalid payload.');
        }
        $fullPath = $this->sanitizePath($payload['path'], true);
        if (!$fullPath) {
            $this->respondError('Invalid path.');
        }
        if (!is_dir(dirname($fullPath))) {
            @mkdir(dirname($fullPath), 0775, true);
        }
        if (file_put_contents($fullPath, $payload['content']) === false) {
            $this->respondError('Failed to write file.');
        }
        JsonResponse::send(['success' => true]);
    }

    private function respondError(string $message, int $code = 400) {
        JsonResponse::error($message, $code);
    }

    private function enforcePrivilege(string $capability): void {
        if (!$this->policy->allowsTool($capability)) {
            $this->respondError('Developer tools are disabled or access is denied.', 403);
        }
    }

    private function enforceCsrf() {
        $request = $this->runData['request'] ?? null;
        if (!$request) {
            $this->respondError('Unable to verify CSRF token.', 419);
        }
        if (!CsrfToken::isValid($request, $_SERVER)) {
            $this->respondError('Invalid CSRF token.', 419);
        }
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
        if ($relative === '') {
            return realpath($base) ?: null;
        }
        try {
            return (new SafePath($base))->existing($relative);
        } catch (\InvalidArgumentException) {
            return null;
        }
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

    private function sanitizePath(string $relative, bool $forWrite = false): ?string {
        $relative = str_replace('\\', '/', ltrim(trim($relative), '/'));
        $allowed = false;
        foreach (['core/', 'admin/classes/', 'admin/ui/', 'ms/', 'theme/', 'upgrades/'] as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return null;
        }
        if ($forWrite && !in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), [
            'php', 'js', 'css', 'json', 'md', 'sql', 'txt',
        ], true)) {
            return null;
        }
        return $forWrite ? $this->paths->forWrite($relative) : $this->paths->existing($relative);
    }
}
