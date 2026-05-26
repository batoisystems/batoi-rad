<?php
namespace RadAdmin;

use Core\Sys\AiProviderFactory;

class Codexapi {
    private $runData = [];
    private $apiKey;
    private $endpoint;
    private $model;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $aiConfig = AiProviderFactory::loadConfig($runData['config'] ?? []);
        $profile = $aiConfig['profiles']['coding'] ?? [];
        $providerKey = strtolower(trim((string)($profile['provider'] ?? ($aiConfig['default_provider'] ?? 'openai'))));
        $provider = $aiConfig['providers'][$providerKey] ?? ($aiConfig['providers']['openai'] ?? []);
        $qualityModels = $profile['quality_models']['full'] ?? [];

        $this->apiKey = $provider['api_key'] ?? '';
        $this->endpoint = $profile['endpoint'] ?? ($provider['endpoint'] ?? 'https://api.openai.com/v1/responses');
        $this->model = $qualityModels['model'] ?? ($profile['fallback_model'] ?? $profile['model'] ?? ($provider['model'] ?? 'gpt-5.4'));
    }

    public function chat() {
        $this->enforceCsrf();
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
                'content' => 'You are Codex inside Batoi RAD Admin. Respond with concise actions.'
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
        $response = $this->callOpenAI(['messages' => $messages]);
        header('Content-Type: application/json');
        echo json_encode([
            'reply' => $this->extractTextContent($response) ?: 'No response.',
            'raw' => $response,
        ]);
        exit;
    }

    public function autocomplete() {
        $this->enforceCsrf();
        $payload = $this->decodeJson();
        $snippet = $payload['snippet'] ?? '';
        $response = $this->callOpenAI([
            'messages' => [
                ['role' => 'system', 'content' => 'Provide JSON completion items list.'],
                ['role' => 'user', 'content' => "Generate completion suggestions for:\n" . $snippet],
            ],
        ]);
        $raw = $this->extractTextContent($response);
        if ($raw === '') {
            $raw = '[]';
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        header('Content-Type: application/json');
        echo json_encode($decoded);
        exit;
    }

    public function fix() {
        $this->enforceCsrf();
        $payload = $this->decodeJson();
        $selection = $payload['selection'] ?? '';
        if ($selection === '') {
            $this->respondError('Selection is required.');
        }
        $response = $this->callOpenAI([
            'messages' => [
                ['role' => 'system', 'content' => 'Return unified diff patches to fix code.'],
                ['role' => 'user', 'content' => "Fix this code:\n" . $selection],
            ],
        ]);
        header('Content-Type: application/json');
        echo json_encode([
            'patch' => $this->extractTextContent($response),
        ]);
        exit;
    }

    public function read_file() {
        $this->enforceCsrf();
        $payload = $this->decodeJson();
        $path = $payload['path'] ?? '';
        $full = $this->sanitizePath($path);
        if (!$full || !is_file($full)) {
            $this->respondError('Invalid path.');
        }
        header('Content-Type: application/json');
        echo json_encode(['content' => file_get_contents($full)]);
        exit;
    }

    public function write_file() {
        $this->enforceCsrf();
        $payload = $this->decodeJson();
        $path = $payload['path'] ?? '';
        $content = $payload['content'] ?? '';
        $full = $this->sanitizePath($path);
        if (!$full) {
            $this->respondError('Invalid path.');
        }
        if (!is_dir(dirname($full))) {
            @mkdir(dirname($full), 0775, true);
        }
        if (file_put_contents($full, $content) === false) {
            $this->respondError('Failed to write file.');
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    public function apply_patch() {
        $this->enforceCsrf();
        $payload = $this->decodeJson();
        $path = $payload['path'] ?? '';
        $patch = $payload['patch'] ?? '';
        $full = $this->sanitizePath($path);
        if (!$full || !is_file($full)) {
            $this->respondError('Invalid file for patch.');
        }
        $original = file($full, FILE_IGNORE_NEW_LINES);
        $patched = $this->applyUnifiedDiff($original, $patch);
        if ($patched === null) {
            $this->respondError('Patch failed.');
        }
        file_put_contents($full, implode("\n", $patched));
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    public function search_files() {
        $this->enforceCsrf();
        $payload = $this->decodeJson();
        $query = $payload['query'] ?? '';
        $root = $this->runData['config']['dir']['rad'] ?? '';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        $matches = [];
        foreach ($iterator as $file) {
            if ($file->getSize() > 2_000_000) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (stripos($contents, $query) !== false) {
                $matches[] = str_replace($root . '/', '', $file->getPathname());
                if (count($matches) >= 20) break;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['results' => $matches]);
        exit;
    }

    public function run_sql() {
        $this->enforceCsrf();
        $payload = $this->decodeJson();
        $sql = trim($payload['sql'] ?? '');
        if ($sql === '') {
            $this->respondError('SQL cannot be empty.');
        }
        if (stripos($sql, 'select') !== 0) {
            $this->respondError('Only SELECT queries are allowed.');
        }
        /** @var \Core\Sys\Database $db */
        $db = $this->runData['db'];
        try {
            $result = $db->query($sql);
        } catch (\Throwable $e) {
            $this->respondError('SQL failed: ' . $e->getMessage());
        }
        header('Content-Type: application/json');
        echo json_encode([
            'result' => $result,
            'rollback' => 'SELECT queries do not change data; no rollback needed.',
        ]);
        exit;
    }

    public function run_php() {
        $this->enforceCsrf();
        $payload = $this->decodeJson();
        $code = $payload['code'] ?? '';
        if ($code === '') {
            $this->respondError('Code cannot be empty.');
        }
        $tempDir = rtrim($this->runData['config']['dir']['rad'], '/') . '/data/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }
        $tempFile = tempnam($tempDir, 'codex-') . '.php';
        file_put_contents($tempFile, "<?php\n" . $code);
        $cmd = escapeshellcmd(PHP_BINARY) . ' -d display_errors=1 ' . escapeshellarg($tempFile);
        $output = [];
        $status = 0;
        exec($cmd . ' 2>&1', $output, $status);
        unlink($tempFile);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'output' => implode("\n", $output),
        ]);
        exit;
    }

    private function decodeJson(): array {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $this->respondError('Invalid JSON payload.');
        }
        return $payload;
    }

    private function callOpenAI(array $body): array {
        if ($this->apiKey === '') {
            $this->respondError('OpenAI API key missing.');
        }
        $endpoint = $this->resolveTextEndpoint($this->model);
        $body = $this->buildTextPayload($body, $this->model, $endpoint);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $result = curl_exec($ch);
        if ($result === false) {
            $this->respondError('OpenAI request failed: ' . curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $decoded = json_decode($result, true);
        if ($status >= 400) {
            $this->respondError($decoded['error']['message'] ?? 'OpenAI error');
        }
        return $decoded;
    }

    private function resolveTextEndpoint(string $model): string {
        if ($this->isResponsesModel($model) && strpos($this->endpoint, '/chat/completions') !== false) {
            return str_replace('/chat/completions', '/responses', $this->endpoint);
        }
        return $this->endpoint;
    }

    private function isResponsesModel(string $model): bool {
        return preg_match('/^gpt-5([.-]|$)/i', $model) === 1;
    }

    private function isResponsesEndpoint(string $endpoint): bool {
        return strpos($endpoint, '/responses') !== false;
    }

    private function buildTextPayload(array $body, string $model, string $endpoint): array {
        $messages = $body['messages'] ?? [];
        if ($this->isResponsesEndpoint($endpoint)) {
            return [
                'model' => $model,
                'input' => $this->normalizeResponsesInput(is_array($messages) ? $messages : []),
                'max_output_tokens' => $body['max_output_tokens'] ?? null,
                'temperature' => $body['temperature'] ?? null,
            ];
        }

        return [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => $body['max_completion_tokens'] ?? ($body['max_tokens'] ?? null),
            'temperature' => $body['temperature'] ?? null,
        ];
    }

    private function normalizeResponsesInput(array $messages): array {
        $normalized = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $role = trim((string)($message['role'] ?? 'user'));
            if ($role === '') {
                $role = 'user';
            }
            $content = $this->normalizeResponsesContent($message['content'] ?? '');
            if ($content === '') {
                continue;
            }
            $normalized[] = [
                'type' => 'message',
                'role' => $role,
                'content' => $content,
            ];
        }
        return $normalized;
    }

    private function normalizeResponsesContent($content) {
        if (is_string($content)) {
            $content = trim($content);
            return $content === '' ? '' : $content;
        }
        if (!is_array($content)) {
            return '';
        }

        $normalized = [];
        foreach ($content as $part) {
            if (is_string($part)) {
                $text = trim($part);
                if ($text !== '') {
                    $normalized[] = ['type' => 'input_text', 'text' => $text];
                }
                continue;
            }
            if (!is_array($part)) {
                continue;
            }

            $type = (string)($part['type'] ?? '');
            if ($type === 'text' || $type === 'input_text') {
                $text = trim((string)($part['text'] ?? ''));
                if ($text !== '') {
                    $normalized[] = ['type' => 'input_text', 'text' => $text];
                }
                continue;
            }

            if ($type === 'image_url') {
                $imageUrl = $part['image_url']['url'] ?? $part['image_url'] ?? null;
                if (is_string($imageUrl) && trim($imageUrl) !== '') {
                    $normalized[] = [
                        'type' => 'input_image',
                        'image_url' => trim($imageUrl),
                        'detail' => 'auto',
                    ];
                }
            }
        }

        return empty($normalized) ? '' : $normalized;
    }

    private function extractTextContent(array $response): string {
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }
        if (is_array($content)) {
            $segments = [];
            foreach ($content as $part) {
                if (is_string($part) && trim($part) !== '') {
                    $segments[] = trim($part);
                    continue;
                }
                if (!is_array($part)) {
                    continue;
                }
                $text = $part['text'] ?? $part['content'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $segments[] = trim($text);
                }
            }
            if (!empty($segments)) {
                return trim(implode("\n", $segments));
            }
        }

        $choiceText = $response['choices'][0]['text'] ?? null;
        if (is_string($choiceText) && trim($choiceText) !== '') {
            return trim($choiceText);
        }

        $outputText = $response['output_text'] ?? null;
        if (is_string($outputText) && trim($outputText) !== '') {
            return trim($outputText);
        }

        $outputItems = $response['output'] ?? [];
        if (is_array($outputItems)) {
            $segments = [];
            foreach ($outputItems as $item) {
                if (!is_array($item) || !isset($item['content']) || !is_array($item['content'])) {
                    continue;
                }
                foreach ($item['content'] as $part) {
                    if (!is_array($part)) {
                        continue;
                    }
                    $text = $part['text'] ?? null;
                    if (is_array($text)) {
                        $text = $text['value'] ?? null;
                    }
                    if (is_string($text) && trim($text) !== '') {
                        $segments[] = trim($text);
                    }
                }
            }
            if (!empty($segments)) {
                return trim(implode("\n", $segments));
            }
        }

        return '';
    }

    private function sanitizePath(string $path): ?string {
        $base = $this->runData['config']['dir']['rad'] ?? '';
        $full = realpath($base . '/' . ltrim($path, '/'));
        if ($full === false) {
            $full = $base . '/' . ltrim($path, '/');
        }
        $realBase = realpath($base) ?: $base;
        if (strpos($full, $realBase) !== 0) {
            return null;
        }
        return $full;
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
        header('Content-Type: application/json', true, $code);
        echo json_encode(['error' => $message]);
        exit;
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
