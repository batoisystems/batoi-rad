<?php
namespace Core\Sys;

/**
 * Class OpenAIApi
 *
 * @package Core
 */
class OpenAIApi
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private int $timeout;
    private string $defaultEndpointType;
    private string $fallbackModel;
    private string $fallbackEndpointType;
    private string $fallbackEndpoint;
    private ErrorHandler $errorHandler;
    private array $endpoints;
    private array $modelCompatibilities;

    public function __construct(array $openAIConfig, ErrorHandler $errorHandler)
    {
        $this->errorHandler = $errorHandler;

        $this->apiKey = $openAIConfig['apiKey'] ?? '';
        $this->model = $openAIConfig['model'] ?? 'gpt-5.1';
        $this->maxTokens = $openAIConfig['maxTokens'] ?? 100;
        $this->timeout = max(5, (int)($openAIConfig['timeout'] ?? 45));
        $this->defaultEndpointType = $openAIConfig['defaultEndpointType'] ?? 'chat';
        $this->fallbackModel = $openAIConfig['fallbackModel'] ?? '';
        $this->fallbackEndpointType = $openAIConfig['fallbackEndpointType'] ?? '';
        $this->fallbackEndpoint = $openAIConfig['fallbackEndpoint'] ?? '';
        $this->endpoints = $openAIConfig['endpoints'] ?? [];
        $this->modelCompatibilities = $openAIConfig['modelCompatibilities'] ?? [];

        if (!$this->apiKey) {
            throw new \RuntimeException("API key must be provided in the AI configuration.");
        }
    }

    public function getSuggestion(string $promptInput, string $endpointType = 'chat'): ?string
    {
        if (!isset($this->endpoints[$endpointType])) {
            if (isset($this->endpoints[$this->defaultEndpointType])) {
                $endpointType = $this->defaultEndpointType;
            } else {
                throw new \RuntimeException("Invalid endpoint type: {$endpointType}");
            }
        }

        $endpoint = $this->endpoints[$endpointType];

        try {
            return $this->requestSuggestion($promptInput, $endpointType, $endpoint, $this->model);
        } catch (\Throwable $e) {
            if (!$this->shouldAttemptFallback($endpointType, $endpoint, $this->model)) {
                throw $e;
            }
            return $this->requestSuggestion($promptInput, $this->fallbackEndpointType, $this->fallbackEndpoint, $this->fallbackModel);
        }
    }

    private function requestSuggestion(string $promptInput, string $endpointType, string $endpoint, string $model): string
    {
        if (!in_array($model, $this->modelCompatibilities[$endpoint] ?? [$model], true)) {
            throw new \RuntimeException("The model '{$model}' is not compatible with the endpoint '{$endpoint}'");
        }
        $ch = curl_init($endpoint);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $this->timeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->buildRequestPayload($promptInput, $endpointType, $model), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->apiKey}",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $errorMessage = 'cURL Error: ' . curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($errorMessage);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $errorMessage = 'Failed to get a response from AI with HTTP Code: ' . $httpCode;
            throw new \RuntimeException($errorMessage);
        }

        $responseParsed = json_decode($response, true);
        if (!is_array($responseParsed)) {
            throw new \RuntimeException('AI response was not valid JSON.');
        }
        $content = $this->extractSuggestionContent($responseParsed);
        if ($content === '') {
            throw new \RuntimeException('AI response did not contain suggestion content.');
        }

        return $content;
    }

    private function buildRequestPayload(string $promptInput, string $endpointType, string $model): array
    {
        if ($endpointType === 'responses') {
            return [
                'model' => $model,
                'input' => $promptInput,
                'max_output_tokens' => $this->maxTokens,
            ];
        }

        return [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $promptInput],
            ],
            'max_completion_tokens' => $this->maxTokens,
        ];
    }

    private function shouldAttemptFallback(string $endpointType, string $endpoint, string $model): bool
    {
        if ($this->fallbackModel === '' || $this->fallbackEndpoint === '') {
            return false;
        }
        if ($this->fallbackModel === $model && $this->fallbackEndpoint === $endpoint) {
            return false;
        }
        if ($this->fallbackEndpointType === '') {
            return false;
        }
        return true;
    }

    private function extractSuggestionContent(array $responseParsed): string
    {
        $content = $responseParsed['choices'][0]['message']['content'] ?? null;
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
                if (is_array($part)) {
                    $text = $part['text'] ?? $part['content'] ?? null;
                    if (is_string($text) && trim($text) !== '') {
                        $segments[] = trim($text);
                    }
                }
            }
            if (!empty($segments)) {
                return trim(implode("\n", $segments));
            }
        }

        $choiceText = $responseParsed['choices'][0]['text'] ?? null;
        if (is_string($choiceText) && trim($choiceText) !== '') {
            return trim($choiceText);
        }

        $outputText = $responseParsed['output_text'] ?? null;
        if (is_string($outputText) && trim($outputText) !== '') {
            return trim($outputText);
        }

        $outputContent = $responseParsed['output'][0]['content'] ?? null;
        if (is_array($outputContent)) {
            $segments = [];
            foreach ($outputContent as $part) {
                if (!is_array($part)) {
                    continue;
                }
                $text = $part['text'] ?? ($part['content'][0]['text'] ?? null);
                if (is_string($text) && trim($text) !== '') {
                    $segments[] = trim($text);
                }
            }
            if (!empty($segments)) {
                return trim(implode("\n", $segments));
            }
        }

        return '';
    }
}

// $openAIConfig = [
//     'apiKey' => 'YOUR_API_KEY',
//     'model' => 'gpt-3.5-turbo',
//     'maxTokens' => 100,
//     'endpoints' => [
//         'chat' => '/v1/chat/completions',
//         'completions' => '/v1/completions',
//         'audio_transcriptions' => '/v1/audio/transcriptions',
//         // ... more as required
//     ],
//     'modelCompatibilities' => [
//         '/v1/chat/completions' => [
//             'gpt-4', 'gpt-3.5-turbo', 'gpt-4-0613', // ... others
//         ],
//         '/v1/completions' => [
//             'text-davinci-003', 'text-curie-001', // ... others
//         ],
//         // ... more as required
//     ],
// ];
