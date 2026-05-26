<?php
namespace Core\App;

use Core\Sys\AiService;
use Core\Sys\ErrorHandler;

/**
 * AiClient
 *
 * Convenience wrapper around Core\Sys\AiService for common AI operations.
 */
class AiClient {
    private AiService $service;

    public function __construct(array $config, ?ErrorHandler $errorHandler = null, string $profile = 'general', ?string $quality = null) {
        $this->service = new AiService($config, $errorHandler, $profile, $quality);
    }

    /**
     * Send a chat-style message list to the AI provider.
     *
     * @param array $messages Array of message objects compatible with AiService
     * @param array $options  Provider-specific options (e.g., model, temperature)
     * @return string Provider response text
     */
    public function chat(array $messages, array $options = []): string {
        return $this->service->chat($messages, $options);
    }

    /**
     * Send a plain completion request to the AI provider.
     *
     * @param string $prompt  Prompt text
     * @param array  $options Provider-specific options (e.g., model, max_tokens)
     * @return string Provider response text
     */
    public function completion(string $prompt, array $options = []): string {
        return $this->service->completion($prompt, $options);
    }

    /**
     * Vision-enabled chat; accepts image paths/URLs/base64 strings.
     *
     * @param array $messages
     * @param array $images
     * @param array $options
     * @return string Provider response text
     */
    public function visionChat(array $messages, array $images = [], array $options = []): string {
        return $this->service->visionChat($messages, $images, $options);
    }

    /**
     * Generate an image from a prompt.
     *
     * @param string $prompt
     * @param array  $options
     * @return array Provider response payload (e.g., URLs/base64 strings)
     */
    public function generateImage(string $prompt, array $options = []): array {
        return $this->service->generateImage($prompt, $options);
    }

    /**
     * Create embeddings.
     *
     * @param string|array $input
     * @param array        $options
     * @return array Embedding response payload
     */
    public function embed($input, array $options = []): array {
        return $this->service->embed($input, $options);
    }

    /**
     * Speech-to-text.
     *
     * @param string $filePath
     * @param array  $options
     * @return string Transcript text
     */
    public function speechToText(string $filePath, array $options = []): string {
        return $this->service->speechToText($filePath, $options);
    }

    /**
     * Text-to-speech; returns base64-encoded audio (e.g., mp3).
     *
     * @param string $text
     * @param array  $options
     */
    public function textToSpeech(string $text, array $options = []): string {
        return $this->service->textToSpeech($text, $options);
    }
}
