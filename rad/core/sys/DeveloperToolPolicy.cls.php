<?php
namespace Core\Sys;

final class DeveloperToolPolicy
{
    private PrivilegeService $privileges;

    public function __construct(private array $config, array $entity)
    {
        $this->privileges = new PrivilegeService($config, $entity);
    }

    public function allowsAi(string $capability): bool
    {
        return $this->flag('ai_code_assist_enabled') && $this->privileges->can($capability);
    }

    public function allowsTool(string $capability): bool
    {
        return $this->flag('developer_tools_enabled') && $this->privileges->can($capability);
    }

    private function flag(string $name): bool
    {
        $value = $this->config['sys'][$name] ?? $this->config['app'][$name] ?? false;
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string)$value)), ['1', 'y', 'yes', 'true', 'on', 'enabled'], true);
    }
}
