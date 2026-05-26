<?php
namespace Core\Sys;

class AiCodeAssistService {
    private AiService $client;
    private ErrorHandler $errorHandler;

    public function __construct(AiService $client, ErrorHandler $errorHandler) {
        $this->client = $client;
        $this->errorHandler = $errorHandler;
    }

    public function suggest(string $context, string $variant = 'generic', array $metadata = []): array {
        $context = $this->sanitizeSnippet($context);
        if ($context === '') {
            return [
                'error' => 'Provide some code context before requesting suggestions.',
            ];
        }

        $prompt = $this->buildPrompt($context, $variant, $metadata);

        try {
            $response = $this->client->completion($prompt);
        } catch (\Throwable $e) {
            $this->errorHandler->logError('AI suggest error: ' . $e->getMessage());
            return ['error' => 'AI service is currently unavailable.'];
        }

        if (!$response) {
            return ['error' => 'AI returned an empty response.'];
        }

        return ['suggestion' => $this->cleanupResponse($response)];
    }

    private function sanitizeSnippet(string $snippet): string {
        $snippet = trim($snippet);
        if (strlen($snippet) > 6000) {
            $snippet = substr($snippet, -6000);
        }
        return $snippet;
    }

    private function buildPrompt(string $context, string $variant, array $metadata): string {
        $system = 'You are a senior RAD Framework engineer. Given the provided context, return the next lines of code that a developer would type. ';
        $system .= 'Do not wrap your response in code fences or explanations. Return only raw code.';

        switch ($variant) {
            case 'upgrade':
                $system .= ' The code relates to RAD database upgrade scripts (PHP + SQL).';
                break;
            case 'controller':
                $system .= ' The code is a RAD controller (PHP) using existing helper classes.';
                break;
            case 'route':
                $system .= ' The code is part of a RAD route definition.';
                break;
            case 'theme':
                $system .= ' The code is a RAD theme template (HTML/PHP).';
                break;
            default:
                break;
        }

        $metaSummary = '';
        if (!empty($metadata)) {
            foreach ($metadata as $key => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $metaSummary .= strtoupper($key) . ': ' . $value . "\n";
            }
        }

        return $system . "\n\nContext:\n" . $context . "\n" . $metaSummary . "\nContinue:";
    }

    private function cleanupResponse(string $response): string {
        $response = preg_replace('/^```[a-zA-Z0-9]*\s*/', '', $response);
        $response = preg_replace('/```$/', '', $response);
        return ltrim($response);
    }
}
