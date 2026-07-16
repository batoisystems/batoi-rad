<?php
namespace RadAdmin;

use Batoi\Aif\Rad\RadAifService;
use Batoi\Aif\Rad\RadCodeAssistService;
use Batoi\Aif\Rad\RadSuggestionClient;
use Core\Sys\DeveloperToolPolicy;

trait AiAssistAware {
    private array $aiAssistServicesByProfile = [];

    protected function getAiAssistService(string $profile = 'general', ?string $quality = null): RadCodeAssistService {
        $this->assertAiAssistAllowed();
        $cacheKey = $profile . ':' . ($quality ?? 'default');
        if (isset($this->aiAssistServicesByProfile[$cacheKey])) {
            return $this->aiAssistServicesByProfile[$cacheKey];
        }
        $errorHandler = $this->errorHandler ?? (($this->runData ?? [])['errorHandler'] ?? null);
        if (!$errorHandler) {
            throw new \RuntimeException('Error handler is required for Batoi AIF assistance.');
        }
        $service = new RadCodeAssistService(
            new RadAifService(($this->runData ?? [])['config'] ?? [], $profile, $quality),
            $errorHandler
        );
        return $this->aiAssistServicesByProfile[$cacheKey] = $service;
    }

    protected function getAiAssistClient(?int $maxTokensOverride = null, ?int $timeoutOverride = null, string $profile = 'general', ?string $quality = null): RadSuggestionClient {
        $this->assertAiAssistAllowed();
        return new RadSuggestionClient(
            new RadAifService(($this->runData ?? [])['config'] ?? [], $profile, $quality),
            $maxTokensOverride
        );
    }

    protected function assertAiAssistAllowed(string $capability = 'code_assist_chat'): void {
        $runData = $this->runData ?? [];
        $policy = new DeveloperToolPolicy($runData['config'] ?? [], $runData['entity'] ?? []);
        if (!$policy->allowsAi($capability)) {
            throw new \Exception('AI code assistance is disabled or this account lacks the required capability.', 403);
        }
    }

    protected function assertDeveloperToolAllowed(string $capability): void {
        $runData = $this->runData ?? [];
        $policy = new DeveloperToolPolicy($runData['config'] ?? [], $runData['entity'] ?? []);
        if (!$policy->allowsTool($capability)) {
            throw new \Exception('Developer tools are disabled or this account lacks the required capability.', 403);
        }
    }

    protected function assertCsrfPayload(array $payload): void {
        $request = ($this->runData ?? [])['request'] ?? null;
        $token = (string)($payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!$request || !$request->checkCSRFToken($token)) {
            throw new \Exception('Invalid CSRF token.', 419);
        }
    }
}
