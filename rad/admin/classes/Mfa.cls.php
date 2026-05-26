<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;

class Mfa {
    private array $runData = [];
    private $db;
    private PrivilegeService $priv;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function dashboard() {
        $this->guard();
        $settings = $this->loadSettings();
        $twilioReady = $this->isTwilioReady($settings['twilio'] ?? []);
        $channels = $settings['channels'] ?? [];

        $this->runData['route']['h1'] = 'System MFA';
        $this->runData['route']['meta_title'] = 'System MFA';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'MFA' => '',
        ];
        $this->runData['route']['pagepart'] = 'mfa-dashboard';
        $this->runData['data']['mfa_settings'] = $settings;
        $this->runData['data']['mfa_summary'] = [
            'enforce_admin' => !empty($settings['enforce_admin_mfa']),
            'enforce_member' => !empty($settings['enforce_member_mfa']),
            'channels' => $channels,
            'twilio_ready' => $twilioReady,
            'email_fallback' => !empty($settings['email_fallback']),
        ];
        return $this->runData;
    }

    public function policy() {
        $this->guard();
        $settings = $this->loadSettings();
        if ($this->runData['request']->method === 'POST') {
            $settings['enforce_admin_mfa'] = !empty($this->runData['request']->post['enforce_admin_mfa']);
            $settings['enforce_member_mfa'] = !empty($this->runData['request']->post['enforce_member_mfa']);
            $this->saveSettings($settings);
            $this->runData['request']->setAlert('MFA policy saved.', 'success');
        }
        $this->setPageMeta('Policy', 'mfa-policy');
        $this->runData['data']['mfa_settings'] = $settings;
        return $this->runData;
    }

    public function channels() {
        $this->guard();
        $settings = $this->loadSettings();
        $twilioReady = $this->isTwilioReady($settings['twilio'] ?? []);
        if ($this->runData['request']->method === 'POST') {
            $channels = $settings['channels'] ?? [];
            $channels['totp'] = !empty($this->runData['request']->post['channels']['totp']);
            $channels['email'] = !empty($this->runData['request']->post['channels']['email']);

            $smsRequested = !empty($this->runData['request']->post['channels']['sms']);
            $whatsappRequested = !empty($this->runData['request']->post['channels']['whatsapp']);
            if (!$twilioReady && ($smsRequested || $whatsappRequested)) {
                $this->runData['request']->setAlert('Twilio is not configured. SMS/WhatsApp channels remain disabled.', 'warning');
                $channels['sms'] = false;
                $channels['whatsapp'] = false;
            } else {
                $channels['sms'] = $smsRequested;
                $channels['whatsapp'] = $whatsappRequested;
            }

            $settings['channels'] = $channels;
            $settings['email_fallback'] = !empty($this->runData['request']->post['email_fallback']);
            $priority = $this->runData['request']->post['delivery_priority'] ?? [];
            if (is_array($priority)) {
                $settings['delivery_priority'] = $this->normalizeDeliveryPriority($priority, $channels);
            }
            $this->saveSettings($settings);
            if (empty($this->runData['route']['alert'])) {
                $this->runData['request']->setAlert('MFA channels saved.', 'success');
            }
        }
        $this->setPageMeta('Channels', 'mfa-channels');
        $this->runData['data']['mfa_settings'] = $settings;
        $this->runData['data']['twilio_ready'] = $twilioReady;
        return $this->runData;
    }

    public function providers() {
        $this->guard();
        $settings = $this->loadSettings();
        if ($this->runData['request']->method === 'POST') {
            $twilio = $settings['twilio'] ?? ['account_sid' => '', 'auth_token' => '', 'from' => ''];
            $twilio['account_sid'] = trim((string)($this->runData['request']->post['twilio']['account_sid'] ?? $twilio['account_sid']));
            $twilio['from'] = trim((string)($this->runData['request']->post['twilio']['from'] ?? $twilio['from']));
            $newToken = trim((string)($this->runData['request']->post['twilio']['auth_token'] ?? ''));
            if ($newToken !== '') {
                $twilio['auth_token'] = $newToken;
            }
            $settings['twilio'] = $twilio;
            $this->saveSettings($settings);
            $this->runData['request']->setAlert('Provider settings saved.', 'success');
        }
        $this->setPageMeta('Providers', 'mfa-providers');
        $this->runData['data']['mfa_settings'] = $settings;
        $this->runData['data']['twilio_ready'] = $this->isTwilioReady($settings['twilio'] ?? []);
        return $this->runData;
    }

    public function security() {
        $this->guard();
        $settings = $this->loadSettings();
        if ($this->runData['request']->method === 'POST') {
            $settings['trusted_device_ttl_days'] = (int)($this->runData['request']->post['trusted_device_ttl_days'] ?? 30);
            $settings['otp_ttl_seconds'] = (int)($this->runData['request']->post['otp_ttl_seconds'] ?? 300);
            $settings['rate_limit'] = [
                'max_attempts' => (int)($this->runData['request']->post['rate_limit']['max_attempts'] ?? 5),
                'lockout_minutes' => (int)($this->runData['request']->post['rate_limit']['lockout_minutes'] ?? 15),
            ];
            $this->saveSettings($settings);
            $this->runData['request']->setAlert('Security settings saved.', 'success');
        }
        $this->setPageMeta('Security', 'mfa-security');
        $this->runData['data']['mfa_settings'] = $settings;
        return $this->runData;
    }

    public function ux() {
        $this->guard();
        $settings = $this->loadSettings();
        if ($this->runData['request']->method === 'POST') {
            $settings['ui'] = [
                'show_hint' => !empty($this->runData['request']->post['ui']['show_hint']),
            ];
            $this->saveSettings($settings);
            $this->runData['request']->setAlert('UX settings saved.', 'success');
        }
        $this->setPageMeta('UX', 'mfa-ux');
        $this->runData['data']['mfa_settings'] = $settings;
        return $this->runData;
    }

    private function guard(): void {
        if (!$this->priv->can('settings')) {
            throw new \Exception('Access denied.', 403);
        }
    }

    private function setPageMeta(string $label, string $pagepart): void {
        $this->runData['route']['h1'] = 'System MFA · ' . $label;
        $this->runData['route']['meta_title'] = 'System MFA · ' . $label;
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'MFA' => $this->runData['route']['rad_admin_url'] . '/mfa/dashboard',
            $label => '',
        ];
        $this->runData['route']['pagepart'] = $pagepart;
    }

    private function loadSettings(): array {
        if (!class_exists('Core\\Sys\\MfaSettings')) {
            $coreDir = rtrim($this->runData['config']['dir']['core'] ?? '', '/');
            if ($coreDir !== '') {
                $path = $coreDir . '/sys/MfaSettings.cls.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        }
        $existing = $this->db->select('s_config', ['s_config_handle' => 'mfa_settings'], true);
        $json = $existing[0]['s_config_value'] ?? '';
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $decoded = [];
            if ($json !== '') {
                $this->runData['request']->setAlert('MFA settings value is not valid JSON. Using defaults.', 'warning');
                if (empty($this->runData['route']['alert'])) {
                    $this->runData['route']['alert'] = 'warning';
                    $this->runData['route']['alert_message'] = 'MFA settings value is not valid JSON. Using defaults.';
                }
            }
        }
        $settings = (new \Core\Sys\MfaSettings($decoded))->all();
        $this->runData['data']['mfa_settings_raw'] = $decoded;
        return $settings;
    }

    private function saveSettings(array $settings): void {
        $jsonValue = json_encode($settings, JSON_PRETTY_PRINT);
        $existing = $this->db->select('s_config', ['s_config_handle' => 'mfa_settings'], true);
        if (!empty($existing)) {
            $this->db->update('s_config', ['s_config_value' => $jsonValue], ['s_config_handle' => 'mfa_settings']);
        } else {
            $this->db->insert('s_config', [
                's_config_handle' => 'mfa_settings',
                's_config_value' => $jsonValue,
                's_config_origin' => 'S',
                's_description' => 'System-wide MFA policy',
            ]);
        }
    }

    private function isTwilioReady(array $twilio): bool {
        return !empty($twilio['account_sid']) && !empty($twilio['auth_token']) && !empty($twilio['from']);
    }

    private function normalizeDeliveryPriority(array $priority, array $channels): array {
        $allowed = ['sms', 'whatsapp', 'email'];
        $priority = array_values(array_filter(array_map('strtolower', $priority), static function ($value) use ($allowed) {
            return in_array($value, $allowed, true);
        }));
        $priority = array_values(array_unique($priority));
        if (empty($priority)) {
            $priority = ['sms', 'email'];
        }
        return array_values(array_filter($priority, static function ($channel) use ($channels) {
            return !empty($channels[$channel]);
        }));
    }
}
