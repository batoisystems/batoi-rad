<?php
namespace Core\Sys;

use RuntimeException;

class CopilotClient implements AiProviderInterface {
    private string $apiKey;
    private string $chatEndpoint;
    private string $imageEndpoint;
    private string $embeddingsEndpoint;
    private string $audioTranscribeEndpoint;
    private string $audioSpeechEndpoint;
    private string $model;
    private string $imageModel;
    private string $embeddingModel;
    private string $sttModel;
    private string $ttsModel;
    private int $timeout;
    private ?ErrorHandler $errorHandler;

    public function __construct(array $config, ?ErrorHandler $errorHandler = null) {
        $this->apiKey = $config['api_key'] ?? '';
        $this->chatEndpoint = $config['endpoint'] ?? 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/chat/completions';
        $this->imageEndpoint = $config['image_endpoint'] ?? 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/images/generations';
        $this->embeddingsEndpoint = $config['embeddings_endpoint'] ?? 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/embeddings';
        $this->audioTranscribeEndpoint = $config['audio_transcribe_endpoint'] ?? 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/audio/transcriptions';
        $this->audioSpeechEndpoint = $config['audio_speech_endpoint'] ?? 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/audio/speech';
        $this->model = $config['model'] ?? 'gpt-4o-mini';
        $this->imageModel = $config['image_model'] ?? 'gpt-image-1';
        $this->embeddingModel = $config['embedding_model'] ?? 'text-embedding-3-small';
        $this->sttModel = $config['stt_model'] ?? 'whisper-1';
        $this->ttsModel = $config['tts_model'] ?? 'gpt-4o-mini-tts';
        $this->timeout = (int)($config['timeout'] ?? 60);
        $this->errorHandler = $errorHandler;
    }

    public function supportsCapability(string $capability): bool {
        return in_array($capability, [
            'chat',
            'completion',
            'vision',
            'image_generation',
            'embeddings',
            'speech_to_text',
            'text_to_speech',
        ], true);
    }

    public function chat(array $messages, array $options = []): string {
        $model = (string)($options['model'] ?? $this->model);
        $endpoint = $this->resolveTextEndpoint($model);
        $payload = $this->buildTextPayload($messages, $options, $model, $endpoint);
        $response = $this->postJson($endpoint, $payload);
        $content = $this->extractTextContent($response);
        if ($content === '') {
            throw new RuntimeException('AI response empty');
        }
        return $content;
    }

    public function completion(string $prompt, array $options = []): string {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);
    }

    public function visionChat(array $messages, array $images = [], array $options = []): string {
        $contentBlocks = [];
        foreach ($images as $img) {
            $imageContent = $this->normalizeImageInput($img);
            if ($imageContent !== null) {
                $contentBlocks[] = ['type' => 'image_url', 'image_url' => $imageContent];
            }
        }
        if (!empty($contentBlocks)) {
            $messages[] = [
                'role' => 'user',
                'content' => $contentBlocks,
            ];
        }
        return $this->chat($messages, $options);
    }

    public function generateImage(string $prompt, array $options = []): array {
        $payload = [
            'model' => $options['model'] ?? $this->imageModel,
            'prompt' => $prompt,
            'size' => $options['size'] ?? null,
            'quality' => $options['quality'] ?? null,
            'response_format' => $options['response_format'] ?? 'b64_json',
            'style' => $options['style'] ?? null,
        ];
        $response = $this->postJson($this->imageEndpoint, $payload, false);
        return $response['data'] ?? [];
    }

    public function embed($input, array $options = []): array {
        $payload = [
            'model' => $options['model'] ?? $this->embeddingModel,
            'input' => $input,
        ];
        $response = $this->postJson($this->embeddingsEndpoint, $payload, false);
        return $response['data'] ?? [];
    }

    public function speechToText(string $filePath, array $options = []): string {
        $payload = [
            'model' => $options['model'] ?? $this->sttModel,
            'language' => $options['language'] ?? null,
            'temperature' => $options['temperature'] ?? null,
            'prompt' => $options['prompt'] ?? null,
        ];
        $response = $this->postMultipart($this->audioTranscribeEndpoint, $payload, $filePath, 'file');
        return $response['text'] ?? '';
    }

    public function textToSpeech(string $text, array $options = []): string {
        $payload = [
            'model' => $options['model'] ?? $this->ttsModel,
            'input' => $text,
            'voice' => $options['voice'] ?? 'alloy',
            'format' => $options['format'] ?? null,
        ];
        $binary = $this->postBinary($this->audioSpeechEndpoint, $payload);
        return base64_encode($binary);
    }

    private function postJson(string $url, array $payload, bool $unused = false): array {
        $data = $this->sendRequest('json', $url, $payload);
        return $data;
    }

    private function postBinary(string $url, array $payload): string {
        return $this->sendRequest('json', $url, $payload, true);
    }

    private function postMultipart(string $url, array $fields, string $filePath, string $fileField): array {
        if (!is_file($filePath)) {
            throw new RuntimeException('File not found: ' . $filePath);
        }
        $payload = array_filter($fields, fn($v) => $v !== null);
        $payload[$fileField] = new \CURLFile($filePath);
        return $this->sendRequest('multipart', $url, $payload);
    }

    private function sendRequest(string $type, string $url, array $payload, bool $returnRaw = false) {
        if ($this->apiKey === '') {
            throw new RuntimeException('AI provider API key missing.');
        }
        $payload = array_filter($payload, fn($v) => $v !== null);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        if ($type === 'multipart') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $headers = ['api-key: ' . $this->apiKey];
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $headers = [
                'api-key: ' . $this->apiKey,
                'Content-Type: application/json',
            ];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
        if ($returnRaw) {
            return $response;
        }
        $data = json_decode((string)$response, true);
        if (!is_array($data)) {
            throw new RuntimeException('AI response not JSON');
        }
        return $data;
    }

    private function normalizeImageInput($image): ?array {
        if (is_string($image)) {
            if (strpos($image, 'http://') === 0 || strpos($image, 'https://') === 0 || strpos($image, 'data:') === 0) {
                return ['url' => $image];
            }
            if (is_file($image)) {
                $data = file_get_contents($image);
                if ($data === false) {
                    throw new RuntimeException('Unable to read image file: ' . $image);
                }
                $mime = mime_content_type($image) ?: 'image/png';
                return ['url' => 'data:' . $mime . ';base64,' . base64_encode($data)];
            }
        }
        if (is_array($image) && isset($image['url'])) {
            return $image;
        }
        return null;
    }

    private function resolveTextEndpoint(string $model): string {
        if ($this->isResponsesModel($model) && strpos($this->chatEndpoint, '/chat/completions') !== false) {
            return str_replace('/chat/completions', '/responses', $this->chatEndpoint);
        }
        return $this->chatEndpoint;
    }

    private function isResponsesEndpoint(string $endpoint): bool {
        return strpos($endpoint, '/responses') !== false;
    }

    private function isResponsesModel(string $model): bool {
        return preg_match('/^gpt-5([.-]|$)/i', $model) === 1;
    }

    private function buildTextPayload(array $messages, array $options, string $model, string $endpoint): array {
        if ($this->isResponsesEndpoint($endpoint)) {
            return [
                'model' => $model,
                'input' => $this->normalizeResponsesInput($messages),
                'max_output_tokens' => $options['max_tokens'] ?? null,
                'temperature' => $options['temperature'] ?? null,
            ];
        }

        return [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => $options['max_tokens'] ?? null,
            'temperature' => $options['temperature'] ?? null,
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
}
