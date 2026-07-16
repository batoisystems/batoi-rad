<?php
namespace RadAdmin;

use Batoi\Aif\Rad\RadAifService;
use Batoi\Rad\Http\CsrfToken;
use Batoi\Rad\Http\JsonRequest;
use Batoi\Rad\Http\JsonResponse;
use Core\Sys\DeveloperToolPolicy;
use Core\Sys\ReadOnlySqlPolicy;
use Core\Sys\SafePath;

class Codeassistapi {
    private $runData = [];
    private RadAifService $aif;
    private DeveloperToolPolicy $policy;
    private SafePath $paths;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->aif = new RadAifService($runData['config'] ?? [], 'coding', 'full');
        $this->policy = new DeveloperToolPolicy($runData['config'] ?? [], $runData['entity'] ?? []);
        $this->paths = new SafePath((string)($runData['config']['dir']['rad'] ?? ''));
    }

    public function chat() {
        $this->enforceCsrf();
        $this->enforceAiCapability('code_assist_chat');
        $payload = $this->decodeJson();
        $prompt = trim($payload['prompt'] ?? '');
        if ($prompt === '') {
            $this->respondError('Prompt cannot be empty.');
        }
        $history = $this->normalizeHistory($payload['history'] ?? []);
        $code = trim($payload['code'] ?? '');

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are the AI coding assistant inside Batoi RAD Admin. Respond with concise actions.'
            ],
        ];
        foreach ($history as $message) {
            $messages[] = $message;
        }
        $context = "File: " . ($payload['file'] ?? 'N/A');
        if ($code !== '') {
            $snippet = mb_substr($code, 0, 8000);
            $context .= "\n\nFile contents:\n```\n{$snippet}\n```";
        }
        $messages[] = [
            'role' => 'user',
            'content' => $prompt . "\n\nContext:\n" . $context,
        ];
        $response = $this->callAif($messages);
        JsonResponse::send([
            'reply' => $response ?: 'No response.',
        ]);
    }

    public function autocomplete() {
        $this->enforceCsrf();
        $this->enforceAiCapability('code_assist_chat');
        $payload = $this->decodeJson();
        $snippet = $payload['snippet'] ?? '';
        $raw = $this->callAif([
            ['role' => 'system', 'content' => 'Provide JSON completion items list.'],
            ['role' => 'user', 'content' => "Generate completion suggestions for:\n" . $snippet],
        ]);
        if ($raw === '') {
            $raw = '[]';
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        JsonResponse::send($decoded);
    }

    public function fix() {
        $this->enforceCsrf();
        $this->enforceAiCapability('code_assist_chat');
        $payload = $this->decodeJson();
        $selection = $payload['selection'] ?? '';
        if ($selection === '') {
            $this->respondError('Selection is required.');
        }
        $response = $this->callAif([
            ['role' => 'system', 'content' => 'Return unified diff patches to fix code.'],
            ['role' => 'user', 'content' => "Fix this code:\n" . $selection],
        ]);
        JsonResponse::send([
            'patch' => $response,
        ]);
    }

    public function read_file() {
        $this->enforceCsrf();
        $this->enforceToolCapability('source_read');
        $payload = $this->decodeJson();
        $path = $payload['path'] ?? '';
        $full = $this->existingSourcePath($path);
        if (!$full || !is_file($full)) {
            $this->respondError('Invalid path.');
        }
        JsonResponse::send(['content' => file_get_contents($full)]);
    }

    public function write_file() {
        $this->enforceCsrf();
        $this->enforceToolCapability('source_write');
        $payload = $this->decodeJson();
        $path = $payload['path'] ?? '';
        $content = $payload['content'] ?? '';
        $full = $this->writableSourcePath($path);
        if (!$full) {
            $this->respondError('Invalid path.');
        }
        if (!is_dir(dirname($full))) {
            @mkdir(dirname($full), 0775, true);
        }
        if (file_put_contents($full, $content) === false) {
            $this->respondError('Failed to write file.');
        }
        JsonResponse::send(['success' => true]);
    }

    public function apply_patch() {
        $this->enforceCsrf();
        $this->enforceToolCapability('source_write');
        $payload = $this->decodeJson();
        $path = $payload['path'] ?? '';
        $patch = $payload['patch'] ?? '';
        $full = $this->existingSourcePath($path);
        if (!$full || !is_file($full)) {
            $this->respondError('Invalid file for patch.');
        }
        $original = file($full, FILE_IGNORE_NEW_LINES);
        $patched = $this->applyUnifiedDiff($original, $patch);
        if ($patched === null) {
            $this->respondError('Patch failed.');
        }
        file_put_contents($full, implode("\n", $patched));
        JsonResponse::send(['success' => true]);
    }

    public function search_files() {
        $this->enforceCsrf();
        $this->enforceToolCapability('source_read');
        $payload = $this->decodeJson();
        $query = $payload['query'] ?? '';
        $matches = [];
        $rad = rtrim((string)($this->runData['config']['dir']['rad'] ?? ''), '/');
        foreach (['core', 'admin/classes', 'admin/ui', 'ms', 'theme', 'upgrades'] as $relativeRoot) {
            $root = $this->paths->existing($relativeRoot);
            if ($root === null || !is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getSize() > 2_000_000) {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());
                if ($contents !== false && stripos($contents, $query) !== false) {
                    $matches[] = ltrim(str_replace($rad, '', $file->getPathname()), '/');
                    if (count($matches) >= 20) {
                        break 2;
                    }
                }
            }
        }
        JsonResponse::send(['results' => $matches]);
    }

    public function run_sql() {
        $this->enforceCsrf();
        $this->enforceToolCapability('sql_read');
        $payload = $this->decodeJson();
        $sql = trim($payload['sql'] ?? '');
        if ($sql === '') {
            $this->respondError('SQL cannot be empty.');
        }
        try {
            $sql = ReadOnlySqlPolicy::assertSafe($sql);
        } catch (\InvalidArgumentException $exception) {
            $this->respondError($exception->getMessage());
        }
        /** @var \Core\Sys\Database $db */
        $db = $this->runData['db'];
        try {
            $result = $db->query($sql);
        } catch (\Throwable $e) {
            $this->respondError('SQL failed: ' . $e->getMessage());
        }
        JsonResponse::send([
            'result' => $result,
            'rollback' => 'SELECT queries do not change data; no rollback needed.',
        ]);
    }

    private function decodeJson(): array {
        try {
            return JsonRequest::decode((string)($this->runData['request']->body ?? ''));
        } catch (\UnexpectedValueException $exception) {
            $this->respondError('Invalid JSON payload.');
        }
    }

    private function callAif(array $messages): string {
        try {
            return $this->aif->chat($messages);
        } catch (\Throwable $exception) {
            $this->respondError('Batoi AIF request failed: ' . $exception->getMessage(), 503);
        }
        return '';
    }

    private function existingSourcePath(string $path): ?string {
        if (!$this->isAllowedSourcePath($path)) {
            return null;
        }
        return $this->paths->existing($path);
    }

    private function writableSourcePath(string $path): ?string {
        if (!$this->isAllowedSourcePath($path) || !$this->isAllowedSourceExtension($path)) {
            return null;
        }
        return $this->paths->forWrite($path);
    }

    private function isAllowedSourcePath(string $path): bool {
        $path = str_replace('\\', '/', ltrim(trim($path), '/'));
        foreach (['core/', 'admin/classes/', 'admin/ui/', 'ms/', 'theme/', 'upgrades/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function isAllowedSourceExtension(string $path): bool {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), [
            'php', 'js', 'css', 'json', 'md', 'sql', 'txt',
        ], true);
    }

    private function applyUnifiedDiff(array $original, string $patchText): ?array {
        $patched = $original;
        $lines = explode("\n", $patchText);
        $i = 0;
        $lineCount = count($lines);
        while ($i < $lineCount) {
            $line = $lines[$i];
            if (!preg_match('/^@@ -(\\d+),(\\d+) \\+(\\d+),(\\d+) @@/', $line, $matches)) {
                $i++;
                continue;
            }
            $startOld = (int)$matches[1] - 1;
            $lengthOld = (int)$matches[2];
            $startNew = (int)$matches[3] - 1;
            $i++;
            $chunkOld = [];
            $chunkNew = [];
            while ($i < $lineCount && isset($lines[$i][0]) && $lines[$i][0] !== '@') {
                $prefix = $lines[$i][0];
                $content = substr($lines[$i], 1);
                if ($prefix === ' ') {
                    $chunkOld[] = $content;
                    $chunkNew[] = $content;
                } elseif ($prefix === '-') {
                    $chunkOld[] = $content;
                } elseif ($prefix === '+') {
                    $chunkNew[] = $content;
                }
                $i++;
            }
            array_splice($patched, $startOld, $lengthOld, $chunkNew);
        }
        return $patched;
    }

    private function respondError(string $message, int $code = 400) {
        JsonResponse::error($message, $code);
    }

    private function enforceAiCapability(string $capability): void {
        if (!$this->policy->allowsAi($capability)) {
            $this->respondError('AI Code Assist is disabled or access is denied.', 403);
        }
    }

    private function enforceToolCapability(string $capability): void {
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

    private function normalizeHistory($history): array {
        if (!is_array($history)) {
            return [];
        }
        $normalized = [];
        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $role = $entry['role'] ?? '';
            $content = trim((string)($entry['content'] ?? ''));
            if ($content === '' || !in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $normalized[] = [
                'role' => $role,
                'content' => $content,
            ];
            if (count($normalized) >= 12) {
                break;
            }
        }
        return $normalized;
    }
}
