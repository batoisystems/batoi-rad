<?php
namespace Core\Sys;

interface AiProviderInterface {
    /**
     * Whether the provider supports the named capability.
     */
    public function supportsCapability(string $capability): bool;

    /**
     * Chat-style completion.
     *
     * @param array $messages Array of ['role' => 'system|user|assistant', 'content' => string]
     * @param array $options Optional provider-specific overrides (model, max_tokens, temperature, etc.)
     * @return string Generated content
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Text completion (single prompt).
     *
     * @param string $prompt
     * @param array $options
     * @return string
     */
    public function completion(string $prompt, array $options = []): string;

    /**
     * Chat that can include image inputs (vision).
     *
     * @param array $messages Standard chat messages
     * @param array $images   List of image payloads (file paths, data URLs, or base64) to append
     * @param array $options  Provider-specific options
     * @return string
     */
    public function visionChat(array $messages, array $images = [], array $options = []): string;

    /**
     * Generate an image.
     *
     * @param string $prompt
     * @param array  $options
     * @return array Array with image data (e.g., base64 or URL) depending on provider options
     */
    public function generateImage(string $prompt, array $options = []): array;

    /**
     * Create embeddings for one or many inputs.
     *
     * @param string|array $input
     * @param array        $options
     * @return array Embedding vectors
     */
    public function embed($input, array $options = []): array;

    /**
     * Speech to text.
     *
     * @param string $filePath
     * @param array  $options
     * @return string Transcript
     */
    public function speechToText(string $filePath, array $options = []): string;

    /**
     * Text to speech.
     *
     * @param string $text
     * @param array  $options
     * @return string Binary audio contents (e.g., MP3) encoded as base64
     */
    public function textToSpeech(string $text, array $options = []): string;
}
