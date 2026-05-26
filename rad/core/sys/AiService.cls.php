<?php
namespace Core\Sys;

use RuntimeException;

class AiService {
    private AiProviderInterface $provider;

    public function __construct(array $config = [], ?ErrorHandler $errorHandler = null, string $profile = 'general', ?string $quality = null) {
        $this->provider = AiProviderFactory::build($config, $errorHandler, $profile, $quality);
    }

    public function chat(array $messages, array $options = []): string {
        $this->requireCapability('chat');
        return $this->provider->chat($messages, $options);
    }

    public function completion(string $prompt, array $options = []): string {
        $this->requireCapability('completion');
        return $this->provider->completion($prompt, $options);
    }

    public function visionChat(array $messages, array $images = [], array $options = []): string {
        $this->requireCapability('vision');
        return $this->provider->visionChat($messages, $images, $options);
    }

    public function generateImage(string $prompt, array $options = []): array {
        $this->requireCapability('image_generation');
        return $this->provider->generateImage($prompt, $options);
    }

    public function embed($input, array $options = []): array {
        $this->requireCapability('embeddings');
        return $this->provider->embed($input, $options);
    }

    public function speechToText(string $filePath, array $options = []): string {
        $this->requireCapability('speech_to_text');
        if (!is_file($filePath)) {
            throw new RuntimeException('Audio file not found: ' . $filePath);
        }
        return $this->provider->speechToText($filePath, $options);
    }

    public function textToSpeech(string $text, array $options = []): string {
        $this->requireCapability('text_to_speech');
        return $this->provider->textToSpeech($text, $options);
    }

    public function supportsCapability(string $capability): bool {
        return $this->provider->supportsCapability($capability);
    }

    private function requireCapability(string $capability): void {
        if ($this->provider->supportsCapability($capability)) {
            return;
        }
        throw new RuntimeException('AI provider does not support capability: ' . $capability);
    }
}
