<?php
namespace Core\Sys;

use RuntimeException;

class GeminiClient implements AiProviderInterface {
    private string $apiKey;
    private string $endpoint;
    private string $model;
    private int $timeout;
    private ?ErrorHandler $errorHandler;
    private string $embeddingModel;

    public function __construct(array $config, ?ErrorHandler $errorHandler = null) {
        $this->apiKey = $config['api_key'] ?? '';
        $endpoint = $config['endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models';
        $this->endpoint = rtrim($endpoint, '/');
        $this->model = $config['model'] ?? 'gemini-1.5-flash-latest';
        $this->timeout = (int)($config['timeout'] ?? 60);
        $this->embeddingModel = $config['embedding_model'] ?? 'text-embedding-004';
        $this->errorHandler = $errorHandler;
    }

    public function supportsCapability(string $capability): bool {
        return in_array($capability, [
            'chat',
            'completion',
            'vision',
            'embeddings',
        ], true);
    }

    public function chat(array $messages, array $options = []): string {
        $model = $options['model'] ?? $this->model;
        $pathModel = (strpos($model, 'models/') === 0) ? $model : 'models/' . $model;
        $url = $this->endpoint . '/' . $pathModel . ':generateContent?key=' . urlencode($this->apiKey);
        [$payload, $hasVisualParts] = $this->buildGenerateContentPayload($messages, [], $options);
        if ($hasVisualParts) {
            throw new RuntimeException('Use visionChat() when sending images to Gemini.');
        }
        $data = $this->requestJson($url, $payload);
        $choice = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($choice === '') {
            throw new RuntimeException('AI response empty');
        }
        return (string)$choice;
    }

    public function completion(string $prompt, array $options = []): string {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);
    }

    public function visionChat(array $messages, array $images = [], array $options = []): string {
        $model = $options['model'] ?? $this->model;
        $pathModel = (strpos($model, 'models/') === 0) ? $model : 'models/' . $model;
        $url = $this->endpoint . '/' . $pathModel . ':generateContent?key=' . urlencode($this->apiKey);
        [$payload] = $this->buildGenerateContentPayload($messages, $images, $options);
        $data = $this->requestJson($url, $payload);
        $choice = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($choice === '') {
            throw new RuntimeException('AI response empty');
        }
        return (string)$choice;
    }

    public function generateImage(string $prompt, array $options = []): array {
        throw new RuntimeException('Image generation not supported for Gemini client.');
    }

    public function embed($input, array $options = []): array {
        $model = $options['model'] ?? $this->embeddingModel;
        $url = $this->endpoint . '/' . $model . ':embedContent?key=' . urlencode($this->apiKey);
        $inputs = is_array($input) ? $input : [$input];
        $results = [];
        foreach ($inputs as $text) {
            $payload = [
                'model' => $model,
                'content' => [
                    'parts' => [
                        ['text' => (string)$text],
                    ],
                ],
            ];
            $data = $this->requestJson($url, $payload);
            if (isset($data['embedding']['values'])) {
                $results[] = $data['embedding']['values'];
            }
        }
        return $results;
    }

    public function speechToText(string $filePath, array $options = []): string {
        throw new RuntimeException('Speech-to-text not supported for Gemini client.');
    }

    public function textToSpeech(string $text, array $options = []): string {
        throw new RuntimeException('Text-to-speech not supported for Gemini client.');
    }

    private function requestJson(string $url, array $payload): array {
        if ($this->apiKey === '') {
            throw new RuntimeException('AI provider API key missing.');
        }
        $payload = array_filter($payload, function ($v) {
            return $v !== null;
        });
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $body = $response;
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('AI request failed: ' . $err);
        }
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            $snippet = $body ? ' Body: ' . substr($body, 0, 200) : '';
            throw new RuntimeException('AI HTTP ' . $code . $snippet);
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('AI response not JSON');
        }
        return $data;
    }

    private function normalizeImageInput($image): ?array {
        if (is_string($image)) {
            if (strpos($image, 'data:') === 0) {
                return $this->normalizeDataUriImage($image);
            }
            if (is_file($image)) {
                $data = file_get_contents($image);
                if ($data === false) {
                    throw new RuntimeException('Unable to read image file: ' . $image);
                }
                $mime = mime_content_type($image) ?: 'image/png';
                return [
                    'inline_data' => [
                        'mimeType' => $mime,
                        'data' => base64_encode($data),
                    ],
                ];
            }
        }
        if (is_array($image) && isset($image['inline_data'])) {
            return $image;
        }
        return null;
    }

    private function buildGenerateContentPayload(array $messages, array $images, array $options): array {
        $systemInstruction = '';
        $contents = [];
        foreach ($messages as $message) {
            $role = (string)($message['role'] ?? 'user');
            $content = $message['content'] ?? '';
            if ($role === 'system') {
                $systemInstruction = trim($systemInstruction . "\n\n" . $this->stringifyContent($content));
                continue;
            }
            $parts = $this->normalizeParts($content);
            if (!empty($parts)) {
                $contents[] = [
                    'role' => $role === 'assistant' ? 'model' : 'user',
                    'parts' => $parts,
                ];
            }
        }

        $imageParts = [];
        foreach ($images as $img) {
            $normalized = $this->normalizeImageInput($img);
            if ($normalized !== null) {
                $imageParts[] = $normalized;
            }
        }
        if (!empty($imageParts)) {
            $lastIndex = count($contents) - 1;
            if ($lastIndex >= 0 && ($contents[$lastIndex]['role'] ?? '') === 'user') {
                $contents[$lastIndex]['parts'] = array_merge($contents[$lastIndex]['parts'], $imageParts);
            } else {
                $contents[] = [
                    'role' => 'user',
                    'parts' => $imageParts,
                ];
            }
        }
        if (empty($contents)) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => '']],
            ];
        }

        $payload = ['contents' => $contents];
        if ($systemInstruction !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }
        if (!empty($options['max_tokens']) || array_key_exists('temperature', $options)) {
            $payload['generationConfig'] = [];
            if (!empty($options['max_tokens'])) {
                $payload['generationConfig']['maxOutputTokens'] = (int)$options['max_tokens'];
            }
            if (array_key_exists('temperature', $options) && $options['temperature'] !== null) {
                $payload['generationConfig']['temperature'] = (float)$options['temperature'];
            }
        }

        return [$payload, !empty($imageParts)];
    }

    private function normalizeParts($content): array {
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part) && trim($part) !== '') {
                    $parts[] = ['text' => $part];
                    continue;
                }
                if (!is_array($part)) {
                    continue;
                }
                if (($part['type'] ?? null) === 'text' && isset($part['text'])) {
                    $parts[] = ['text' => (string)$part['text']];
                    continue;
                }
                if (($part['type'] ?? null) === 'image_url' && isset($part['image_url']['url'])) {
                    $normalized = $this->normalizeImageInput($part['image_url']['url']);
                    if ($normalized !== null) {
                        $parts[] = $normalized;
                    }
                    continue;
                }
                if (isset($part['inline_data'])) {
                    $parts[] = $part;
                }
            }
            return $parts;
        }
        return [['text' => (string)$content]];
    }

    private function stringifyContent($content): string {
        if (is_array($content)) {
            $segments = [];
            foreach ($content as $part) {
                if (is_string($part) && trim($part) !== '') {
                    $segments[] = trim($part);
                    continue;
                }
                if (is_array($part) && ($part['type'] ?? null) === 'text' && trim((string)($part['text'] ?? '')) !== '') {
                    $segments[] = trim((string)$part['text']);
                }
            }
            return implode("\n", $segments);
        }
        return (string)$content;
    }

    private function normalizeDataUriImage(string $dataUri): ?array {
        if (!preg_match('/^data:(.*?);base64,(.*)$/', $dataUri, $matches)) {
            return null;
        }
        return [
            'inline_data' => [
                'mimeType' => $matches[1] !== '' ? $matches[1] : 'image/png',
                'data' => $matches[2],
            ],
        ];
    }
}
