<?php
namespace Core\Sys;

class AiProviderFactory {
    private const DEFAULT_OPENAI_PROVIDER = [
        'api_key' => '',
        'endpoint' => 'https://api.openai.com/v1/responses',
        'model' => 'gpt-5.4-mini',
        'image_endpoint' => 'https://api.openai.com/v1/images/generations',
        'embeddings_endpoint' => 'https://api.openai.com/v1/embeddings',
        'audio_transcribe_endpoint' => 'https://api.openai.com/v1/audio/transcriptions',
        'audio_speech_endpoint' => 'https://api.openai.com/v1/audio/speech',
        'image_model' => 'gpt-image-1',
        'embedding_model' => 'text-embedding-3-small',
        'stt_model' => 'whisper-1',
        'tts_model' => 'gpt-4o-mini-tts',
        'timeout' => 60,
    ];

    private const DEFAULT_COPILOT_PROVIDER = [
        'api_key' => '',
        'endpoint' => '',
        'model' => 'gpt-4o-mini',
        'timeout' => 60,
    ];

    public static function loadConfig(array $config): array {
        $siteRoot = $config['dir']['site'] ?? dirname(__DIR__, 2);
        $customConfig = $siteRoot . '/rad/config/ai-config.php';
        $aiConfig = null;
        if (file_exists($customConfig)) {
            $data = include $customConfig;
            if (is_array($data)) {
                $aiConfig = $data;
            }
        }
        if ($aiConfig === null && isset($config['ai']) && is_array($config['ai'])) {
            $legacyAi = $config['ai'];
            $aiConfig = [
                'default_provider' => 'openai',
                'default_profile' => 'general',
                'default_quality' => self::normalizeQuality($legacyAi['default_quality'] ?? null) ?? 'mini',
                'fallback_quality' => self::normalizeQuality($legacyAi['fallback_quality'] ?? null) ?? 'full',
                'providers' => [
                    'openai' => [
                        'api_key' => $legacyAi['api_key'] ?? '',
                        'endpoint' => $legacyAi['endpoint'] ?? self::DEFAULT_OPENAI_PROVIDER['endpoint'],
                        'model' => $legacyAi['model'] ?? ($legacyAi['ai_model'] ?? self::DEFAULT_OPENAI_PROVIDER['model']),
                        'image_endpoint' => $legacyAi['image_endpoint'] ?? self::DEFAULT_OPENAI_PROVIDER['image_endpoint'],
                        'embeddings_endpoint' => $legacyAi['embeddings_endpoint'] ?? self::DEFAULT_OPENAI_PROVIDER['embeddings_endpoint'],
                        'audio_transcribe_endpoint' => $legacyAi['audio_transcribe_endpoint'] ?? self::DEFAULT_OPENAI_PROVIDER['audio_transcribe_endpoint'],
                        'audio_speech_endpoint' => $legacyAi['audio_speech_endpoint'] ?? self::DEFAULT_OPENAI_PROVIDER['audio_speech_endpoint'],
                        'image_model' => $legacyAi['image_model'] ?? self::DEFAULT_OPENAI_PROVIDER['image_model'],
                        'embedding_model' => $legacyAi['embedding_model'] ?? self::DEFAULT_OPENAI_PROVIDER['embedding_model'],
                        'stt_model' => $legacyAi['stt_model'] ?? self::DEFAULT_OPENAI_PROVIDER['stt_model'],
                        'tts_model' => $legacyAi['tts_model'] ?? self::DEFAULT_OPENAI_PROVIDER['tts_model'],
                        'timeout' => 60,
                    ],
                    'copilot' => self::DEFAULT_COPILOT_PROVIDER,
                ],
                'profiles' => self::normalizeProfilesFromLegacyAi($legacyAi),
            ];
        }
        if ($aiConfig === null) {
            $aiConfig = [
                'default_provider' => 'openai',
                'default_profile' => 'general',
                'default_quality' => 'mini',
                'fallback_quality' => 'full',
                'providers' => [
                    'openai' => self::DEFAULT_OPENAI_PROVIDER,
                    'copilot' => self::DEFAULT_COPILOT_PROVIDER,
                ],
                'profiles' => self::defaultProfiles(),
            ];
        }

        $aiConfig['default_provider'] = $aiConfig['default_provider'] ?? 'openai';
        $aiConfig['default_profile'] = self::normalizeProfileKey($aiConfig['default_profile'] ?? 'general');
        $aiConfig['default_quality'] = self::normalizeQuality($aiConfig['default_quality'] ?? null) ?? 'mini';
        $aiConfig['fallback_quality'] = self::normalizeQuality($aiConfig['fallback_quality'] ?? null) ?? 'full';
        $aiConfig['providers'] = $aiConfig['providers'] ?? [];
        $aiConfig['providers']['openai'] = array_merge(self::DEFAULT_OPENAI_PROVIDER, $aiConfig['providers']['openai'] ?? []);
        $aiConfig['providers']['copilot'] = array_merge(self::DEFAULT_COPILOT_PROVIDER, $aiConfig['providers']['copilot'] ?? []);
        $aiConfig['profiles'] = self::mergeProfiles($aiConfig);
        return $aiConfig;
    }

    public static function build(array $config, ?ErrorHandler $errorHandler = null, string $profile = 'general', ?string $quality = null): AiProviderInterface {
        $aiConfig = self::loadConfig($config);
        return self::buildForProfile($config, $profile, $quality, $errorHandler);
    }

    public static function buildForProvider(array $config, string $providerKey, ?ErrorHandler $errorHandler = null): AiProviderInterface {
        $aiConfig = self::loadConfig($config);
        $providerKey = strtolower(trim($providerKey));
        if ($providerKey === '') {
            $providerKey = 'openai';
        }
        $providers = $aiConfig['providers'] ?? [];
        $providerConfig = $providers[$providerKey] ?? [];

        switch ($providerKey) {
            case 'claude':
                return new ClaudeClient($providerConfig, $errorHandler);
            case 'gemini':
                return new GeminiClient($providerConfig, $errorHandler);
            case 'copilot':
                return new CopilotClient($providerConfig, $errorHandler);
            case 'openai':
            default:
                return new OpenAiClient($providerConfig, $errorHandler);
        }
    }

    public static function buildForProfile(array $config, string $profile = 'general', ?string $quality = null, ?ErrorHandler $errorHandler = null): AiProviderInterface {
        $aiConfig = self::loadConfig($config);
        $profileKey = self::normalizeProfileKey($profile !== '' ? $profile : ($aiConfig['default_profile'] ?? 'general'));
        $profiles = $aiConfig['profiles'] ?? [];
        $profileConfig = $profiles[$profileKey] ?? $profiles['general'] ?? [];
        $providerKey = strtolower(trim((string)($profileConfig['provider'] ?? ($aiConfig['default_provider'] ?? 'openai'))));
        if ($providerKey === '') {
            $providerKey = 'openai';
        }

        $providers = $aiConfig['providers'] ?? [];
        $providerConfig = $providers[$providerKey] ?? [];
        $resolvedQuality = self::normalizeQuality($quality)
            ?? self::normalizeQuality($profileConfig['default_quality'] ?? null)
            ?? self::normalizeQuality($aiConfig['default_quality'] ?? null)
            ?? 'mini';
        $resolvedFallbackQuality = self::normalizeQuality($profileConfig['fallback_quality'] ?? null)
            ?? self::normalizeQuality($aiConfig['fallback_quality'] ?? null)
            ?? 'full';

        $mergedProviderConfig = array_merge($providerConfig, self::profileProviderOverrides($profileConfig, $resolvedQuality, $resolvedFallbackQuality));
        return self::buildProviderInstance($providerKey, $mergedProviderConfig, $errorHandler);
    }

    private static function buildProviderInstance(string $providerKey, array $providerConfig, ?ErrorHandler $errorHandler = null): AiProviderInterface {
        switch ($providerKey) {
            case 'claude':
                return new ClaudeClient($providerConfig, $errorHandler);
            case 'gemini':
                return new GeminiClient($providerConfig, $errorHandler);
            case 'copilot':
                return new CopilotClient($providerConfig, $errorHandler);
            case 'openai':
            default:
                return new OpenAiClient($providerConfig, $errorHandler);
        }
    }

    private static function profileProviderOverrides(array $profileConfig, string $quality, string $fallbackQuality): array {
        $resolvedModel = self::resolveProfileModel($profileConfig, $quality, 'model');
        $resolvedFallbackModel = self::resolveProfileModel($profileConfig, $fallbackQuality, 'fallback_model');
        if ($resolvedFallbackModel === '') {
            $resolvedFallbackModel = self::resolveProfileModel($profileConfig, $quality, 'fallback_model');
        }

        $overrides = [];
        foreach (['endpoint', 'image_endpoint', 'embeddings_endpoint', 'audio_transcribe_endpoint', 'audio_speech_endpoint', 'timeout'] as $key) {
            if (array_key_exists($key, $profileConfig) && $profileConfig[$key] !== '') {
                $overrides[$key] = $profileConfig[$key];
            }
        }
        if ($resolvedModel !== '') {
            $overrides['model'] = $resolvedModel;
        }
        if ($resolvedFallbackModel !== '') {
            $overrides['fallback_model'] = $resolvedFallbackModel;
        }
        return $overrides;
    }

    private static function resolveProfileModel(array $profileConfig, string $quality, string $key): string {
        $qualityModels = $profileConfig['quality_models'] ?? [];
        if (isset($qualityModels[$quality][$key]) && trim((string)$qualityModels[$quality][$key]) !== '') {
            return trim((string)$qualityModels[$quality][$key]);
        }
        return trim((string)($profileConfig[$key] ?? ''));
    }

    private static function mergeProfiles(array $aiConfig): array {
        $profiles = $aiConfig['profiles'] ?? [];
        $defaultProvider = $aiConfig['default_provider'] ?? 'openai';
        $defaultQuality = self::normalizeQuality($aiConfig['default_quality'] ?? null) ?? 'mini';
        $fallbackQuality = self::normalizeQuality($aiConfig['fallback_quality'] ?? null) ?? 'full';
        $defaults = self::defaultProfiles($defaultProvider, $defaultQuality, $fallbackQuality);
        foreach ($defaults as $profileKey => $defaultProfileConfig) {
            $profileConfig = $profiles[$profileKey] ?? [];
            $profiles[$profileKey] = self::mergeProfileConfig($defaultProfileConfig, is_array($profileConfig) ? $profileConfig : []);
        }
        return $profiles;
    }

    private static function mergeProfileConfig(array $defaultProfileConfig, array $profileConfig): array {
        $merged = array_merge($defaultProfileConfig, $profileConfig);
        $merged['provider'] = strtolower(trim((string)($merged['provider'] ?? $defaultProfileConfig['provider'] ?? 'openai')));
        $merged['default_quality'] = self::normalizeQuality($merged['default_quality'] ?? null) ?? ($defaultProfileConfig['default_quality'] ?? 'mini');
        $merged['fallback_quality'] = self::normalizeQuality($merged['fallback_quality'] ?? null) ?? ($defaultProfileConfig['fallback_quality'] ?? 'full');
        $merged['quality_models'] = self::mergeQualityModels($defaultProfileConfig['quality_models'] ?? [], $profileConfig['quality_models'] ?? []);
        return $merged;
    }

    private static function mergeQualityModels(array $defaultQualityModels, array $qualityModels): array {
        $merged = $defaultQualityModels;
        foreach ($qualityModels as $quality => $qualityConfig) {
            if (!is_array($qualityConfig)) {
                continue;
            }
            $qualityKey = self::normalizeQuality((string)$quality);
            if ($qualityKey === null) {
                continue;
            }
            $merged[$qualityKey] = array_merge($merged[$qualityKey] ?? [], $qualityConfig);
        }
        return $merged;
    }

    private static function defaultProfiles(string $defaultProvider = 'openai', string $defaultQuality = 'mini', string $fallbackQuality = 'full'): array {
        $base = [
            'provider' => $defaultProvider,
            'endpoint_type' => 'responses',
            'endpoint' => self::DEFAULT_OPENAI_PROVIDER['endpoint'],
            'default_quality' => $defaultQuality,
            'fallback_quality' => $fallbackQuality,
            'quality_models' => [
                'mini' => [
                    'model' => 'gpt-5.4-mini',
                    'fallback_model' => 'gpt-5.4',
                ],
                'full' => [
                    'model' => 'gpt-5.4',
                    'fallback_model' => 'gpt-5.4-mini',
                ],
            ],
        ];
        return [
            'general' => array_merge($base, [
                'max_tokens' => 256,
                'timeout' => 45,
                'model' => 'gpt-5.4-mini',
                'fallback_model' => 'gpt-5.4',
            ]),
            'coding' => array_merge($base, [
                'max_tokens' => 1800,
                'timeout' => 45,
                'model' => 'gpt-5.4-mini',
                'fallback_model' => 'gpt-5.4',
            ]),
        ];
    }

    private static function normalizeProfilesFromLegacyAi(array $legacyAi): array {
        $profiles = [];
        foreach (($legacyAi['profiles'] ?? []) as $profileKey => $profileConfig) {
            if (!is_array($profileConfig)) {
                continue;
            }
            $normalizedKey = self::normalizeProfileKey((string)$profileKey);
            $profiles[$normalizedKey] = [
                'provider' => 'openai',
                'endpoint_type' => $profileConfig['endpoint_type'] ?? self::inferEndpointType((string)($profileConfig['endpoint'] ?? '')),
                'endpoint' => $profileConfig['endpoint'] ?? ($legacyAi['endpoint'] ?? self::DEFAULT_OPENAI_PROVIDER['endpoint']),
                'model' => $profileConfig['model'] ?? ($legacyAi['model'] ?? 'gpt-5.4-mini'),
                'fallback_model' => $profileConfig['fallback_model'] ?? ($legacyAi['fallback_model'] ?? 'gpt-5.4'),
                'max_tokens' => (int)($profileConfig['max_tokens'] ?? 256),
                'timeout' => (int)($profileConfig['timeout'] ?? 45),
                'default_quality' => self::normalizeQuality($profileConfig['default_quality'] ?? null) ?? self::normalizeQuality($legacyAi['default_quality'] ?? null) ?? 'mini',
                'fallback_quality' => self::normalizeQuality($profileConfig['fallback_quality'] ?? null) ?? self::normalizeQuality($legacyAi['fallback_quality'] ?? null) ?? 'full',
                'quality_models' => self::mergeQualityModels([], $profileConfig['quality_models'] ?? ($legacyAi['quality_models'] ?? [])),
            ];
        }
        return $profiles;
    }

    private static function inferEndpointType(string $endpoint): string {
        return str_contains(strtolower($endpoint), '/responses') ? 'responses' : 'chat';
    }

    private static function normalizeProfileKey(string $profile): string {
        $profile = strtolower(trim($profile));
        return $profile === 'coding' ? 'coding' : 'general';
    }

    private static function normalizeQuality($quality): ?string {
        $quality = strtolower(trim((string)$quality));
        if ($quality === '') {
            return null;
        }
        return in_array($quality, ['mini', 'full'], true) ? $quality : null;
    }
}
