<?php
namespace Core\Sys;

use RuntimeException;

class AiApiGatewayService {
    private AiService $client;
    private ErrorHandler $errorHandler;

    public function __construct(AiService $client, ErrorHandler $errorHandler) {
        $this->client = $client;
        $this->errorHandler = $errorHandler;
    }

    public function execute(array $endpoint, array $payload): array {
        $definition = $endpoint['definition'] ?? [];
        $prompt = $this->buildPrompt($definition, $payload);

        try {
            $response = $this->executePrompt($prompt, (string)($definition['endpoint'] ?? 'chat'));
        } catch (\Throwable $e) {
            $this->errorHandler->logError('AI endpoint error: ' . $e->getMessage());
            throw new RuntimeException('AI service unavailable.');
        }

        return ['result' => $response];
    }

    private function buildPrompt(array $definition, array $payload): string {
        $template = $definition['prompt_template'] ?? '';
        if ($template === '') {
            throw new RuntimeException('AI endpoint missing prompt template.');
        }
        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace('{{' . $key . '}}', (string)$value, $template);
            }
        }
        return $template;
    }

    private function executePrompt(string $prompt, string $endpointType): string {
        switch ($endpointType) {
            case 'vision':
            case 'vision_chat':
                return $this->client->visionChat([
                    ['role' => 'user', 'content' => $prompt],
                ]);
            case 'completion':
            case 'chat':
            default:
                return $this->client->completion($prompt);
        }
    }
}
