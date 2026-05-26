<?php
namespace Core\Sys;

class CodeCompletion {
    private AiService $openAi;

    public function __construct(AiService $openAi) {
        $this->openAi = $openAi;
    }

    public function getPHPCompletion(string $prompt) {
        $completion = $this->openAi->completion($prompt);
        // Further processing if needed
        return $completion;
    }

    public function getJSCompletion(string $prompt) {
        $completion = $this->openAi->completion($prompt);
        // Further processing if needed
        return $completion;
    }
    
    // Add other methods for HTML, CSS, etc.
}
