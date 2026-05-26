<?php
namespace Core\Sys;

use RuntimeException;

class ClaudeClient implements AiProviderInterface {
    private string $apiKey;
    private string $endpoint;
    private string $model;
    private int $timeout;
    private ?ErrorHandler $errorHandler;

    public function __construct(array $config, ?ErrorHandler $errorHandler = null) {
        $this->apiKey = $config['api_key'] ?? '';
        $this->endpoint = $config['endpoint'] ?? 'https://api.anthropic.com/v1/messages';
        $this->model = $config['model'] ?? 'claude-3-haiku-20240307';
        $this->timeout = (int)($config['timeout'] ?? 60);
        $this->errorHandler = $errorHandler;
    }

    public function supportsCapability(string $capability): bool {
        return in_array($capability, [
            'chat',
            'completion',
            'vision',
        ], true);
    }

    public function chat(array $messages, array $options = []): string {
        [$systemPrompt, $normalizedMessages] = $this->splitMessages($messages);
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? 512,
            'messages' => $normalizedMessages,
        ];
        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }
        $data = $this->requestJson($this->endpoint, $payload);
        $choice = $data['content'][0]['text'] ?? '';
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
        [$systemPrompt, $normalizedMessages] = $this->splitMessages($messages);
        $imageParts = [];
        foreach ($images as $img) {
            $image = $this->normalizeImageInput($img);
            if ($image !== null) {
                $imageParts[] = $image;
            }
        }
        if (empty($imageParts)) {
            return $this->chat($messages, $options);
        }

        $lastIndex = count($normalizedMessages) - 1;
        if ($lastIndex >= 0 && ($normalizedMessages[$lastIndex]['role'] ?? '') === 'user') {
            $existingContent = $normalizedMessages[$lastIndex]['content'] ?? [];
            if (!is_array($existingContent)) {
                $existingContent = [['type' => 'text', 'text' => (string)$existingContent]];
            }
            $normalizedMessages[$lastIndex]['content'] = array_merge($existingContent, $imageParts);
        } else {
            $normalizedMessages[] = [
                'role' => 'user',
                'content' => $imageParts,
            ];
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? 512,
            'messages' => $normalizedMessages,
        ];
        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }
        $data = $this->requestJson($this->endpoint, $payload);
        $choice = $data['content'][0]['text'] ?? '';
        if ($choice === '') {
            throw new RuntimeException('AI response empty');
        }
        return (string)$choice;
    }

    public function generateImage(string $prompt, array $options = []): array {
        throw new RuntimeException('Image generation not supported for Claude client.');
    }

    public function embed($input, array $options = []): array {
        throw new RuntimeException('Embeddings not supported for Claude client.');
    }

    public function speechToText(string $filePath, array $options = []): string {
        throw new RuntimeException('Speech-to-text not supported for Claude client.');
    }

    public function textToSpeech(string $text, array $options = []): string {
        throw new RuntimeException('Text-to-speech not supported for Claude client.');
    }

    private function splitMessages(array $messages): array {
        $systemSegments = [];
        $normalized = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $this->normalizeContent($message['content'] ?? '');
            if ($role === 'system') {
                foreach ($content as $part) {
                    if (($part['type'] ?? null) === 'text' && trim((string)($part['text'] ?? '')) !== '') {
                        $systemSegments[] = trim((string)$part['text']);
                    }
                }
                continue;
            }
            $normalized[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }
        if (empty($normalized)) {
            $normalized[] = [
                'role' => 'user',
                'content' => [['type' => 'text', 'text' => '']],
            ];
        }
        return [implode("\n\n", $systemSegments), $normalized];
    }

    private function requestJson(string $url, array $payload): array {
        if ($this->apiKey === '') {
            throw new RuntimeException('AI provider API key missing.');
        }
        $payload = array_filter($payload, fn($v) => $v !== null);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('AI request failed: ' . $err);
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('AI HTTP ' . $code);
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('AI response not JSON');
        }
        return $data;
    }

    private function normalizeImageInput($image): ?array {
        if (is_string($image)) {
            if (strpos($image, 'http://') === 0 || strpos($image, 'https://') === 0) {
                return [
                    'type' => 'image',
                    'source' => [
                        'type' => 'url',
                        'url' => $image,
                    ],
                ];
            }
            if (is_file($image)) {
                $data = file_get_contents($image);
                if ($data === false) {
                    throw new RuntimeException('Unable to read image file: ' . $image);
                }
                $mime = mime_content_type($image) ?: 'image/png';
                return [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mime,
                        'data' => base64_encode($data),
                    ],
                ];
            }
        }
        if (is_array($image) && isset($image['type']) && $image['type'] === 'image') {
            return $image;
        }
        return null;
    }

    private function normalizeContent($content): array {
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part) && trim($part) !== '') {
                    $parts[] = ['type' => 'text', 'text' => $part];
                    continue;
                }
                if (!is_array($part)) {
                    continue;
                }
                if (($part['type'] ?? null) === 'text' && isset($part['text'])) {
                    $parts[] = ['type' => 'text', 'text' => (string)$part['text']];
                    continue;
                }
                if (($part['type'] ?? null) === 'image') {
                    $parts[] = $part;
                }
            }
            if (!empty($parts)) {
                return $parts;
            }
        }
        return [['type' => 'text', 'text' => (string)$content]];
    }
}
