<?php
namespace RadAdmin;

use Core\Sys\AiProviderFactory;
use Core\Sys\PrivilegeService;
use RuntimeException;

class Aiconfig {
    private array $runData = [];
    private string $configFile;
    private PrivilegeService $privileges;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->configFile = rtrim($runData['config']['dir']['rad'] ?? dirname(__DIR__, 2), '/') . '/config/ai-config.php';
        $this->privileges = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function view() {
        if (!$this->privileges->can('aiconfig_manage')) {
            throw new RuntimeException('Access denied.');
        }
        $config = $this->loadConfig();
        $this->runData['data']['ai_config'] = $this->maskConfig($config);
        $this->runData['data']['ai_provider_definitions'] = $this->getProviderDefinitions();
        $this->runData['data']['ai_profile_definitions'] = $this->getProfileDefinitions();
        $this->runData['data']['ai_quality_definitions'] = $this->getQualityDefinitions();
        $this->runData['route']['h1'] = 'AI Settings';
        $this->runData['route']['meta_title'] = 'AI Settings';
        $this->runData['route']['breadcrumb'] = ['AI Settings' => ''];

        if ($this->runData['request']->method === 'POST') {
            $this->save();
        }

        return $this->runData;
    }

    private function save(): void {
        $post = $this->runData['request']->post;
        $current = $this->loadConfig();
        $providers = $current['providers'] ?? [];
        $providerDefinitions = $this->getProviderDefinitions();
        $profileDefinitions = $this->getProfileDefinitions();
        $default = $this->normalizeProviderKey($post['default_provider'] ?? ($current['default_provider'] ?? 'openai'));
        $defaultProfile = $this->normalizeProfileKey($post['default_profile'] ?? ($current['default_profile'] ?? 'general'));
        $defaultQuality = $this->normalizeQualityKey($post['default_quality'] ?? ($current['default_quality'] ?? 'mini'));
        $fallbackQuality = $this->normalizeQualityKey($post['fallback_quality'] ?? ($current['fallback_quality'] ?? 'full'));

        foreach ($providerDefinitions as $key => $definition) {
            $existing = $providers[$key] ?? [];
            $prefix = $key . '_';
            $apiKey = trim((string)($post[$prefix . 'api_key'] ?? ''));
            if ($apiKey === '' || $apiKey === '********') {
                $apiKey = (string)($existing['api_key'] ?? '');
            }

            $providerConfig = [
                'api_key' => $apiKey,
                'endpoint' => trim((string)($post[$prefix . 'endpoint'] ?? ($existing['endpoint'] ?? ''))),
                'model' => trim((string)($post[$prefix . 'model'] ?? ($existing['model'] ?? ''))),
                'timeout' => max(1, min(240, (int)($post[$prefix . 'timeout'] ?? ($existing['timeout'] ?? 60)))),
            ];

            foreach ($definition['advanced_fields'] as $field => $fieldMeta) {
                $providerConfig[$field] = trim((string)($post[$prefix . $field] ?? ($existing[$field] ?? '')));
            }

            $providers[$key] = $this->sanitizeProviderConfig($providerConfig);
        }

        $profiles = [];
        foreach ($profileDefinitions as $key => $definition) {
            $existing = $current['profiles'][$key] ?? [];
            $prefix = 'profile_' . $key . '_';
            $profileDefaultQuality = $this->normalizeQualityKey($post[$prefix . 'default_quality'] ?? ($existing['default_quality'] ?? $defaultQuality));
            $profileFallbackQuality = $this->normalizeQualityKey($post[$prefix . 'fallback_quality'] ?? ($existing['fallback_quality'] ?? $fallbackQuality));
            $miniModel = trim((string)($post[$prefix . 'mini_model'] ?? ($existing['quality_models']['mini']['model'] ?? $existing['model'] ?? '')));
            $fullModel = trim((string)($post[$prefix . 'full_model'] ?? ($existing['quality_models']['full']['model'] ?? $existing['fallback_model'] ?? '')));
            $provider = $this->normalizeProviderKey($post[$prefix . 'provider'] ?? ($existing['provider'] ?? $default));
            $endpoint = trim((string)($post[$prefix . 'endpoint'] ?? ($existing['endpoint'] ?? '')));
            $endpointType = $this->normalizeEndpointType($post[$prefix . 'endpoint_type'] ?? ($existing['endpoint_type'] ?? 'responses'));

            $profiles[$key] = $this->sanitizeProfileConfig([
                'provider' => array_key_exists($provider, $providerDefinitions) ? $provider : $default,
                'endpoint_type' => $endpointType,
                'endpoint' => $endpoint,
                'max_tokens' => max(1, min(32000, (int)($post[$prefix . 'max_tokens'] ?? ($existing['max_tokens'] ?? $definition['default_max_tokens'])))),
                'timeout' => max(1, min(240, (int)($post[$prefix . 'timeout'] ?? ($existing['timeout'] ?? 45)))),
                'default_quality' => $profileDefaultQuality,
                'fallback_quality' => $profileFallbackQuality,
                'model' => $profileDefaultQuality === 'full' ? $fullModel : $miniModel,
                'fallback_model' => $profileFallbackQuality === 'full' ? $fullModel : $miniModel,
                'quality_models' => [
                    'mini' => [
                        'model' => $miniModel,
                        'fallback_model' => $fullModel,
                    ],
                    'full' => [
                        'model' => $fullModel,
                        'fallback_model' => $miniModel,
                    ],
                ],
            ]);
        }

        $newConfig = [
            'default_provider' => array_key_exists($default, $providerDefinitions) ? $default : 'openai',
            'default_profile' => array_key_exists($defaultProfile, $profileDefinitions) ? $defaultProfile : 'general',
            'default_quality' => $defaultQuality,
            'fallback_quality' => $fallbackQuality,
            'providers' => $providers,
            'profiles' => $profiles,
        ];

        $this->writeConfig($newConfig);
        $this->runData['request']->setAlert('AI settings saved.', 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/aiconfig/view');
        exit;
    }

    private function loadConfig(): array {
        if (file_exists($this->configFile)) {
            $data = include $this->configFile;
            if (is_array($data)) {
                return $data;
            }
        }
        return AiProviderFactory::loadConfig($this->runData['config'] ?? []);
    }

    private function maskConfig(array $config): array {
        foreach ($config['providers'] ?? [] as $key => &$provider) {
            if (!empty($provider['api_key'])) {
                $provider['api_key'] = '********';
            }
        }
        unset($provider);
        return $config;
    }

    private function writeConfig(array $config): void {
        $export = "<?php\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents($this->configFile, $export) === false) {
            throw new RuntimeException('Unable to write ai-config.php');
        }
    }

    private function sanitizeProviderConfig(array $providerConfig): array {
        foreach ($providerConfig as $key => $value) {
            if ($key === 'timeout') {
                continue;
            }
            if ($value === '') {
                unset($providerConfig[$key]);
            }
        }
        return $providerConfig;
    }

    private function sanitizeProfileConfig(array $profileConfig): array {
        foreach (['provider', 'endpoint_type', 'endpoint', 'model', 'fallback_model', 'default_quality', 'fallback_quality'] as $key) {
            if (!isset($profileConfig[$key])) {
                continue;
            }
            $profileConfig[$key] = trim((string)$profileConfig[$key]);
        }

        $qualityModels = [];
        foreach (($profileConfig['quality_models'] ?? []) as $quality => $qualityConfig) {
            $qualityKey = $this->normalizeQualityKey($quality);
            $qualityModels[$qualityKey] = [
                'model' => trim((string)($qualityConfig['model'] ?? '')),
                'fallback_model' => trim((string)($qualityConfig['fallback_model'] ?? '')),
            ];
        }
        $profileConfig['quality_models'] = $qualityModels;
        return $profileConfig;
    }

    private function normalizeProviderKey($provider): string {
        $provider = strtolower(trim((string)$provider));
        if (in_array($provider, ['microsoft', 'ms_copilot'], true)) {
            return 'copilot';
        }
        return $provider;
    }

    private function normalizeProfileKey($profile): string {
        $profile = strtolower(trim((string)$profile));
        return $profile === 'coding' ? 'coding' : 'general';
    }

    private function normalizeQualityKey($quality): string {
        $quality = strtolower(trim((string)$quality));
        return $quality === 'full' ? 'full' : 'mini';
    }

    private function normalizeEndpointType($endpointType): string {
        $endpointType = strtolower(trim((string)$endpointType));
        return $endpointType === 'chat' ? 'chat' : 'responses';
    }

    private function getProfileDefinitions(): array {
        return [
            'general' => [
                'label' => 'General',
                'summary' => 'Assistant, chat, help, explain, and summarize flows.',
                'default_max_tokens' => 256,
            ],
            'coding' => [
                'label' => 'Coding',
                'summary' => 'Code completion, patch generation, route/controller editing, and upgrade workflows.',
                'default_max_tokens' => 1800,
            ],
        ];
    }

    private function getQualityDefinitions(): array {
        return [
            'mini' => ['label' => 'Mini'],
            'full' => ['label' => 'Full'],
        ];
    }

    private function getProviderDefinitions(): array {
        return [
            'openai' => [
                'label' => 'OpenAI',
                'summary' => 'Full feature support: chat, vision, images, embeddings, speech-to-text, and text-to-speech.',
                'notes' => 'Best default when you want the broadest capability coverage.',
                'capabilities' => ['Chat', 'Completion', 'Vision', 'Images', 'Embeddings', 'Speech-to-text', 'Text-to-speech'],
                'advanced_fields' => [
                    'image_model' => ['label' => 'Image model', 'placeholder' => 'gpt-image-1'],
                    'embedding_model' => ['label' => 'Embedding model', 'placeholder' => 'text-embedding-3-small'],
                    'stt_model' => ['label' => 'STT model', 'placeholder' => 'whisper-1'],
                    'tts_model' => ['label' => 'TTS model', 'placeholder' => 'gpt-4o-mini-tts'],
                    'image_endpoint' => ['label' => 'Image endpoint', 'placeholder' => 'https://api.openai.com/v1/images/generations'],
                    'embeddings_endpoint' => ['label' => 'Embeddings endpoint', 'placeholder' => 'https://api.openai.com/v1/embeddings'],
                    'audio_transcribe_endpoint' => ['label' => 'Audio transcribe endpoint', 'placeholder' => 'https://api.openai.com/v1/audio/transcriptions'],
                    'audio_speech_endpoint' => ['label' => 'Audio speech endpoint', 'placeholder' => 'https://api.openai.com/v1/audio/speech'],
                ],
            ],
            'copilot' => [
                'label' => 'Microsoft Copilot / Azure OpenAI',
                'summary' => 'Configured here as an Azure OpenAI-compatible provider using Microsoft-hosted endpoints.',
                'notes' => 'Use your Azure resource URLs and API key. This is not a separate public Copilot Chat API.',
                'capabilities' => ['Chat', 'Completion', 'Vision', 'Images', 'Embeddings', 'Speech-to-text', 'Text-to-speech'],
                'advanced_fields' => [
                    'image_model' => ['label' => 'Image model', 'placeholder' => 'gpt-image-1'],
                    'embedding_model' => ['label' => 'Embedding model', 'placeholder' => 'text-embedding-3-small'],
                    'stt_model' => ['label' => 'STT model', 'placeholder' => 'whisper-1'],
                    'tts_model' => ['label' => 'TTS model', 'placeholder' => 'gpt-4o-mini-tts'],
                    'image_endpoint' => ['label' => 'Image endpoint', 'placeholder' => 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/images/generations'],
                    'embeddings_endpoint' => ['label' => 'Embeddings endpoint', 'placeholder' => 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/embeddings'],
                    'audio_transcribe_endpoint' => ['label' => 'Audio transcribe endpoint', 'placeholder' => 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/audio/transcriptions'],
                    'audio_speech_endpoint' => ['label' => 'Audio speech endpoint', 'placeholder' => 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/audio/speech'],
                ],
            ],
            'claude' => [
                'label' => 'Claude',
                'summary' => 'Strong text and vision support through the Anthropic messages API.',
                'notes' => 'This layer currently supports chat, completion, and vision for Claude. Image generation, embeddings, and audio are not exposed here.',
                'capabilities' => ['Chat', 'Completion', 'Vision'],
                'advanced_fields' => [],
            ],
            'gemini' => [
                'label' => 'Gemini',
                'summary' => 'Supports chat, vision, and embeddings through Google Gemini.',
                'notes' => 'This layer currently supports chat, completion, vision, and embeddings for Gemini. Image generation and audio are not exposed here.',
                'capabilities' => ['Chat', 'Completion', 'Vision', 'Embeddings'],
                'advanced_fields' => [
                    'embedding_model' => ['label' => 'Embedding model', 'placeholder' => 'text-embedding-004'],
                ],
            ],
        ];
    }
}
