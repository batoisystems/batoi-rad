<?php
namespace Core\Sys;

class IpAccessService {
    public function getClientIp(): string {
        $candidates = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['HTTP_CLIENT_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate === '') {
                continue;
            }
            if (strpos($candidate, ',') !== false) {
                $candidate = trim((string)explode(',', $candidate)[0]);
            }
            if ($candidate !== '') {
                return $candidate;
            }
        }
        return '';
    }

    public function normalizeIpList($raw): array {
        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }
        $raw = str_replace(["\r\n", "\r", "\n", ";"], ',', (string)$raw);
        $parts = array_map('trim', explode(',', $raw));
        $valid = [];
        $invalid = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (filter_var($part, FILTER_VALIDATE_IP) === false) {
                $invalid[] = $part;
                continue;
            }
            $valid[] = $part;
        }
        $valid = array_values(array_unique($valid));
        $invalid = array_values(array_unique($invalid));
        return [
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }

    public function normalizeRule(bool $enabled, $rawIps): array {
        $list = $this->normalizeIpList($rawIps);
        return [
            'enabled' => $enabled,
            'ips' => $list['valid'],
            'invalid' => $list['invalid'],
            'raw' => implode(', ', $list['valid']),
        ];
    }

    public function extractRuleFromDefinition($definition): array {
        if (is_string($definition)) {
            $definition = json_decode($definition, true);
        }
        if (!is_array($definition)) {
            $definition = [];
        }
        $ipAccess = $definition['ip_access'] ?? [];
        if (!is_array($ipAccess)) {
            $ipAccess = [];
        }
        $enabled = !empty($ipAccess['enabled']);
        if (is_string($ipAccess['enabled'] ?? null)) {
            $enabled = in_array(strtoupper((string)$ipAccess['enabled']), ['1', 'Y', 'YES', 'TRUE', 'ON'], true);
        }
        return $this->normalizeRule($enabled, $ipAccess['ips'] ?? '');
    }

    public function mergeRuleIntoDefinition($definition, bool $enabled, $rawIps): array {
        if (is_string($definition)) {
            $definition = json_decode($definition, true);
        }
        if (!is_array($definition)) {
            $definition = [];
        }
        $rule = $this->normalizeRule($enabled, $rawIps);
        if ($rule['enabled'] && !empty($rule['ips'])) {
            $definition['ip_access'] = [
                'enabled' => true,
                'ips' => implode(', ', $rule['ips']),
            ];
        } else {
            unset($definition['ip_access']);
        }
        return [
            'definition' => $definition,
            'rule' => $rule,
        ];
    }

    public function evaluate(array $rule, ?int $entityId = null, ?string $clientIp = null): array {
        $clientIp = trim((string)($clientIp ?? $this->getClientIp()));
        if ((int)$entityId === 1) {
            return [
                'allowed' => true,
                'reason' => 'immutable_entity',
                'client_ip' => $clientIp,
                'matched_ip' => null,
            ];
        }
        if (empty($rule['enabled'])) {
            return [
                'allowed' => true,
                'reason' => 'disabled',
                'client_ip' => $clientIp,
                'matched_ip' => null,
            ];
        }
        $ips = $rule['ips'] ?? [];
        if (empty($ips)) {
            return [
                'allowed' => false,
                'reason' => 'empty_allowlist',
                'client_ip' => $clientIp,
                'matched_ip' => null,
            ];
        }
        if ($clientIp === '') {
            return [
                'allowed' => false,
                'reason' => 'missing_client_ip',
                'client_ip' => $clientIp,
                'matched_ip' => null,
            ];
        }
        if (in_array($clientIp, $ips, true)) {
            return [
                'allowed' => true,
                'reason' => 'matched',
                'client_ip' => $clientIp,
                'matched_ip' => $clientIp,
            ];
        }
        return [
            'allowed' => false,
            'reason' => 'not_listed',
            'client_ip' => $clientIp,
            'matched_ip' => null,
        ];
    }
}
