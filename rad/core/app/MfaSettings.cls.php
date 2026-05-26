<?php
namespace Core\App;

use InvalidArgumentException;

/**
 * MFA settings service for application developers.
 *
 * Reads/writes system MFA policy stored in s_config (handle: mfa_settings).
 * This class does not enforce permissions; callers must implement their own
 * access control before invoking update methods.
 */
class MfaSettings {
    private $db;
    private array $defaults = [
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

    public function __construct($db) {
        if (!$db) {
            throw new InvalidArgumentException('Database handle is required.');
        }
        $this->db = $db;
    }

    /**
     * Read the normalized MFA settings payload.
     *
     * @return array
     */
    public function get(): array {
        $row = $this->fetchConfigRow();
        $raw = $this->decodePayload($row['s_config_value'] ?? '');
        return $this->mergeDefaults($raw);
    }

    /**
     * Update MFA settings.
     *
     * Accepts a partial payload; unknown keys are ignored.
     * Values are normalized to expected types.
     *
     * Example:
     * $mfa = new \Core\App\MfaSettings($db);
     * $mfa->update([
     *   'enforce_admin_mfa' => true,
     *   'channels' => ['totp' => true, 'sms' => true],
     *   'delivery_priority' => ['sms', 'email'],
     * ]);
     *
     * @param array $payload
     * @return array Normalized settings after update
     */
    public function update(array $payload): array {
        $current = $this->get();
        $normalized = $this->mergeDefaults($this->filterPayload($payload, $current));
        $jsonValue = json_encode($normalized, JSON_PRETTY_PRINT);
        $row = $this->fetchConfigRow();
        if (!empty($row)) {
            $this->db->update('s_config', ['s_config_value' => $jsonValue], ['s_config_handle' => 'mfa_settings']);
        } else {
            $this->db->insert('s_config', [
                's_config_handle' => 'mfa_settings',
                's_config_value' => $jsonValue,
                's_config_origin' => 'S',
                's_description' => 'System-wide MFA policy',
            ]);
        }
        return $normalized;
    }

    /**
     * Replace the MFA settings payload with a full schema object.
     *
     * @param array $payload Full settings payload
     * @return array Normalized settings
     */
    public function replace(array $payload): array {
        $normalized = $this->mergeDefaults($payload);
        $jsonValue = json_encode($normalized, JSON_PRETTY_PRINT);
        $row = $this->fetchConfigRow();
        if (!empty($row)) {
            $this->db->update('s_config', ['s_config_value' => $jsonValue], ['s_config_handle' => 'mfa_settings']);
        } else {
            $this->db->insert('s_config', [
                's_config_handle' => 'mfa_settings',
                's_config_value' => $jsonValue,
                's_config_origin' => 'S',
                's_description' => 'System-wide MFA policy',
            ]);
        }
        return $normalized;
    }

    /**
     * Example usage:
     *
     * $mfa = new \Core\App\MfaSettings($runData['db']);
     * $settings = $mfa->get();
     * $mfa->update([
     *   'channels' => ['totp' => true, 'sms' => false, 'email' => true],
     *   'delivery_priority' => ['email'],
     * ]);
     */

    private function fetchConfigRow(): array {
        $rows = $this->db->select('s_config', ['s_config_handle' => 'mfa_settings'], true);
        return $rows[0] ?? [];
    }

    private function decodePayload(string $json): array {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function mergeDefaults(array $raw): array {
        $result = $this->defaults;
        foreach ($this->defaults as $key => $default) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            if (is_array($default)) {
                $result[$key] = $this->mergeArray($default, $raw[$key]);
            } else {
                $result[$key] = $this->sanitizeScalar($default, $raw[$key]);
            }
        }
        return $result;
    }

    private function mergeArray(array $default, $incoming): array {
        if (!is_array($incoming)) {
            return $default;
        }
        $merged = $default;
        foreach ($default as $k => $v) {
            if (!array_key_exists($k, $incoming)) {
                continue;
            }
            if (is_array($v)) {
                $merged[$k] = $this->mergeArray($v, $incoming[$k]);
            } else {
                $merged[$k] = $this->sanitizeScalar($v, $incoming[$k]);
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

    private function filterPayload(array $payload, array $current): array {
        $filtered = [];
        foreach ($this->defaults as $key => $default) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = $payload[$key];
            if (is_array($default)) {
                $filtered[$key] = $this->mergeArray($current[$key] ?? $default, $value);
            } else {
                $filtered[$key] = $this->sanitizeScalar($default, $value);
            }
        }
        return $filtered;
    }
}
