<?php
namespace Core\Sys;

use Twilio\Rest\Client;

class MfaService {
    private array $config;
    private ErrorHandler $errorHandler;
    private ?MfaSettings $settings = null;

    public function __construct(array $config, ErrorHandler $errorHandler) {
        $this->config = $config;
        $this->errorHandler = $errorHandler;
        $this->settings = $config['mfa_settings_obj'] ?? null;
    }

    public function totpVerify(string $secret, string $code, int $window = 1): bool {
        if ($secret === '' || $code === '') {
            return false;
        }
        $code = preg_replace('/[^0-9]/', '', $code);
        if (strlen($code) < 6) {
            return false;
        }
        $timeSlice = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $calc = $this->totpCode($secret, $timeSlice + $i);
            if (hash_equals($calc, $code)) {
                return true;
            }
        }
        return false;
    }

    public function totpCode(string $secret, int $timeSlice = null): string {
        $timeSlice = $timeSlice ?? floor(time() / 30);
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[19]) & 0xf;
        $truncatedHash = substr($hash, $offset, 4);
        $value = unpack('N', $truncatedHash)[1] & 0x7fffffff;
        $modulo = 10 ** 6;
        return str_pad((string)($value % $modulo), 6, '0', STR_PAD_LEFT);
    }

    public function sendOtpViaTwilio(string $to, string $message): bool {
        $tw = $this->settings ? $this->settings->twilio() : [];
        $sid = $tw['account_sid'] ?? ($this->config['app']['twilio_account_sid'] ?? '');
        $token = $tw['auth_token'] ?? ($this->config['app']['twilio_auth_token'] ?? '');
        $from = $tw['from'] ?? ($this->config['app']['twilio_from'] ?? '');
        if ($sid === '' || $token === '' || $from === '' || $to === '') {
            return false;
        }
        try {
            $client = new Client($sid, $token);
            $client->messages->create($to, ['from' => $from, 'body' => $message]);
            return true;
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Twilio MFA send failed: ' . $e->getMessage());
            return false;
        }
    }

    private function base32Decode(string $b32): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper($b32);
        $l = strlen($b32);
        $n = 0;
        $j = 0;
        $binary = '';
        for ($i = 0; $i < $l; $i++) {
            $n = $n << 5;
            $n = $n + strpos($alphabet, $b32[$i]);
            $j = $j + 5;
            if ($j >= 8) {
                $j = $j - 8;
                $binary .= chr(($n & (0xFF << $j)) >> $j);
            }
        }
        return $binary;
    }
}
