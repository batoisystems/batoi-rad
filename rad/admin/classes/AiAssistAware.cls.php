<?php
namespace RadAdmin;

use Core\Sys\AiCodeAssistService;
use Core\Sys\OpenAIApi;

trait AiAssistAware {
    private $aiAssistService = null;
    private array $aiAssistServicesByProfile = [];

    protected function getAiAssistService(string $profile = 'general', ?string $quality = null): AiCodeAssistService {
        $cacheKey = $profile . ':' . ($quality ?? 'default');
        if (isset($this->aiAssistServicesByProfile[$cacheKey]) && $this->aiAssistServicesByProfile[$cacheKey] instanceof AiCodeAssistService) {
            return $this->aiAssistServicesByProfile[$cacheKey];
        }

        $client = $this->buildAiAssistClient(null, null, $profile, $quality);
        $errorHandler = $this->errorHandler ?? (($this->runData ?? [])['errorHandler'] ?? null);
        $service = new AiCodeAssistService($client, $errorHandler);
        $this->aiAssistServicesByProfile[$cacheKey] = $service;
        return $service;
    }

    protected function getAiAssistClient(?int $maxTokensOverride = null, ?int $timeoutOverride = null, string $profile = 'general', ?string $quality = null): OpenAIApi {
        return $this->buildAiAssistClient($maxTokensOverride, $timeoutOverride, $profile, $quality);
    }

    private function buildAiAssistClient(?int $maxTokensOverride = null, ?int $timeoutOverride = null, string $profile = 'general', ?string $quality = null): OpenAIApi {
        $runData = $this->runData ?? [];
        $aiConfig = $runData['config']['ai'] ?? ($runData['config']['rad']['ai'] ?? []);
        $profileConfig = $this->resolveAiProfileConfig($aiConfig, $profile);
        $endpoint = $profileConfig['endpoint'] ?? ($aiConfig['endpoint'] ?? '');
        $endpointType = $profileConfig['endpoint_type'] ?? $this->inferAiEndpointType((string)$endpoint);
        $model = $this->resolveAiModel($aiConfig, $profileConfig, $quality, 'model', 'default_quality');
        $fallbackModel = $this->resolveAiModel($aiConfig, $profileConfig, $quality, 'fallback_model', 'fallback_quality');
        $apiKey = $aiConfig['api_key'] ?? '';

        $errorHandler = $this->errorHandler ?? ($runData['errorHandler'] ?? null);
        if (!$errorHandler) {
            throw new \RuntimeException('Error handler is required for AI assistance.');
        }
        if (trim((string)$endpoint) === '') {
            throw new \RuntimeException('AI Assist endpoint is not configured.');
        }
        if (trim((string)$apiKey) === '') {
            throw new \RuntimeException('AI Assist API key is not configured.');
        }

        $client = new OpenAIApi([
            'apiKey' => $apiKey,
            'model' => $model,
            'fallbackModel' => $fallbackModel,
            'maxTokens' => $maxTokensOverride !== null ? $maxTokensOverride : (int)($profileConfig['max_tokens'] ?? $aiConfig['max_tokens'] ?? 256),
            'timeout' => $timeoutOverride !== null ? $timeoutOverride : (int)($profileConfig['timeout'] ?? $aiConfig['timeout'] ?? 45),
            'defaultEndpointType' => $endpointType,
            'fallbackEndpointType' => $profileConfig['fallback_endpoint_type'] ?? $this->inferAiEndpointType((string)($profileConfig['fallback_endpoint'] ?? $endpoint)),
            'fallbackEndpoint' => $profileConfig['fallback_endpoint'] ?? $endpoint,
            'endpoints' => [$endpointType => $endpoint],
            'modelCompatibilities' => [
                $endpoint => array_values(array_unique(array_filter([
                    $model,
                    $fallbackModel,
                ]))),
                ($profileConfig['fallback_endpoint'] ?? $endpoint) => array_values(array_unique(array_filter([
                    $fallbackModel,
                    $model,
                ]))),
            ],
        ], $errorHandler);

        return $client;
    }

    private function resolveAiProfileConfig(array $aiConfig, string $profile): array {
        $profiles = $aiConfig['profiles'] ?? [];
        if (isset($profiles[$profile]) && is_array($profiles[$profile])) {
            return $profiles[$profile];
        }
        return [];
    }

    private function resolveAiModel(array $aiConfig, array $profileConfig, ?string $quality, string $modelKey, string $defaultQualityKey): string {
        $resolvedQuality = $this->normalizeAiQuality(
            $quality
            ?? ($profileConfig[$defaultQualityKey] ?? null)
            ?? ($aiConfig[$defaultQualityKey] ?? null)
        );

        if ($resolvedQuality !== null) {
            $profileQualityModels = $profileConfig['quality_models'] ?? [];
            if (isset($profileQualityModels[$resolvedQuality][$modelKey]) && trim((string)$profileQualityModels[$resolvedQuality][$modelKey]) !== '') {
                return trim((string)$profileQualityModels[$resolvedQuality][$modelKey]);
            }

            $globalQualityModels = $aiConfig['quality_models'] ?? [];
            if (isset($globalQualityModels[$resolvedQuality][$modelKey]) && trim((string)$globalQualityModels[$resolvedQuality][$modelKey]) !== '') {
                return trim((string)$globalQualityModels[$resolvedQuality][$modelKey]);
            }
        }

        $directModel = trim((string)($profileConfig[$modelKey] ?? $aiConfig[$modelKey] ?? ''));
        if ($directModel !== '') {
            return $directModel;
        }

        return $modelKey === 'fallback_model' ? '' : 'gpt-5.1';
    }

    private function normalizeAiQuality(?string $quality): ?string {
        if ($quality === null) {
            return null;
        }
        $quality = strtolower(trim($quality));
        if ($quality === '') {
            return null;
        }
        if (in_array($quality, ['mini', 'full'], true)) {
            return $quality;
        }
        return null;
    }

    private function inferAiEndpointType(string $endpoint): string {
        $endpoint = strtolower($endpoint);
        if (str_contains($endpoint, '/v1/responses')) {
            return 'responses';
        }
        return 'chat';
    }
}
