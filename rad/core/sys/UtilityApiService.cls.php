<?php
namespace Core\Sys;

use RuntimeException;

class UtilityApiService {
    private array $config;

    public function __construct(array $config = []) {
        $this->config = $config['utility_callables'] ?? [];
    }

    public function execute(array $endpoint, array $payload, array $apiAccount): array {
        $callableKey = $endpoint['s_target'] ?? '';
        if ($callableKey === '' || !isset($this->config[$callableKey])) {
            throw new RuntimeException('Utility callable not registered: ' . $callableKey);
        }
        $definition = $this->config[$callableKey];
        $callable = $definition['callable'] ?? null;
        if (!$callable || !is_callable($callable)) {
            throw new RuntimeException('Callable is not available for utility endpoint.');
        }
        $allowedParams = $definition['allowed_params'] ?? [];
        $arguments = [];
        if (empty($allowedParams)) {
            $arguments[] = $payload;
        } else {
            foreach ($allowedParams as $param) {
                $arguments[] = $payload[$param] ?? null;
            }
        }
        $result = call_user_func_array($callable, $arguments);
        return ['result' => $result];
    }
}
