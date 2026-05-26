<?php
namespace Core\Sys;

class MfaSettings {
    private array $raw;
    private array $settings;

    public function __construct(array $raw) {
        $this->raw = $raw;
        $this->settings = $this->normalize($raw);
    }

    public function all(): array {
        return $this->settings;
    }

    public function channels(): array {
        return $this->settings['channels'];
    }

    public function enforceAdmin(): bool {
        return (bool)$this->settings['enforce_admin_mfa'];
    }

    public function enforceMember(): bool {
        return (bool)$this->settings['enforce_member_mfa'];
    }

    public function trustedDeviceTtlDays(): int {
        return (int)$this->settings['trusted_device_ttl_days'];
    }

    public function otpTtlSeconds(): int {
        return (int)$this->settings['otp_ttl_seconds'];
    }

    public function rateLimit(): array {
        return $this->settings['rate_limit'];
    }

    public function twilio(): array {
        return $this->settings['twilio'];
    }

    public function emailFallback(): bool {
        return (bool)$this->settings['email_fallback'];
    }

    public function ui(): array {
        return $this->settings['ui'];
    }

    public function deliveryPriority(): array {
        return $this->settings['delivery_priority'] ?? [];
    }

    private function normalize(array $raw): array {
        $defaults = [
            'enforce_admin_mfa' => false,
            'enforce_member_mfa' => false,
            'channels' => [
                'totp' => true,
                'sms' => false,
                'whatsapp' => false,
                'email' => false,
            ],
            'trusted_device_ttl_days' => 30,
            'otp_ttl_seconds' => 300,
            'rate_limit' => [
                'max_attempts' => 5,
                'lockout_minutes' => 15,
            ],
            'twilio' => [
                'account_sid' => '',
                'auth_token' => '',
                'from' => '',
            ],
            'email_fallback' => false,
            'delivery_priority' => ['sms', 'whatsapp', 'email'],
            'ui' => [
                'show_hint' => false,
            ],
        ];
        $settings = $defaults;
        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            if (is_array($default)) {
                $settings[$key] = $this->mergeArray($default, $raw[$key]);
            } else {
                $settings[$key] = $this->sanitizeScalar($default, $raw[$key]);
            }
        }
        return $settings;
    }

    private function mergeArray(array $default, $incoming): array {
        if (!is_array($incoming)) {
            return $default;
        }
        $merged = $default;
        foreach ($default as $k => $v) {
            if (array_key_exists($k, $incoming)) {
                if (is_array($v)) {
                    $merged[$k] = $this->mergeArray($v, $incoming[$k]);
                } else {
                    $merged[$k] = $this->sanitizeScalar($v, $incoming[$k]);
                }
            }
        }
        return $merged;
    }

    private function sanitizeScalar($default, $value) {
        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
        }
        if (is_int($default)) {
            return (int)$value;
        }
        if (is_string($default)) {
            return (string)$value;
        }
        return $default;
    }
}
