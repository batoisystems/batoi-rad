<?php
namespace Core\Sys;

final class AuthenticationThrottle
{
    private int $maxAttempts;
    private int $windowSeconds;
    private int $lockoutSeconds;

    public function __construct(private Database $db, array $config = [])
    {
        $this->maxAttempts = max(3, (int)($config['sys']['login_max_attempts'] ?? 5));
        $this->windowSeconds = max(60, (int)($config['sys']['login_attempt_window_seconds'] ?? 900));
        $this->lockoutSeconds = max(60, (int)($config['sys']['login_lockout_seconds'] ?? 900));
    }

    public function blocked(string $identity, string $ip): bool
    {
        try {
            $rows = $this->db->query(
                'SELECT blocked_until FROM s_auth_attempt WHERE bucket_hash = :bucket LIMIT 1',
                [':bucket' => $this->bucket($identity, $ip)]
            );
            if (empty($rows[0]['blocked_until'])) {
                return false;
            }
            return strtotime((string)$rows[0]['blocked_until']) > time();
        } catch (\Throwable) {
            // Preserve login availability during a rolling deployment before the
            // migration is applied. The release verifier requires this table.
            return false;
        }
    }

    public function failure(string $identity, string $ip): void
    {
        $now = date('Y-m-d H:i:s');
        $windowStart = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        $blockedUntil = date('Y-m-d H:i:s', time() + $this->lockoutSeconds);
        try {
            $this->db->query(
                "INSERT INTO s_auth_attempt
                    (bucket_hash, identity_hash, ip_hash, failed_count, window_started_at, blocked_until, last_attempt_at)
                 VALUES (:bucket, :identity, :ip, 1, :now_insert, NULL, :last_attempt_insert)
                 ON DUPLICATE KEY UPDATE
                    failed_count = IF(window_started_at < :window_for_count, 1, failed_count + 1),
                    window_started_at = IF(window_started_at < :window_for_start, :now_for_start, window_started_at),
                    blocked_until = IF(failed_count >= :max_attempts, :blocked_until, blocked_until),
                    last_attempt_at = :now_for_attempt",
                [
                    ':bucket' => $this->bucket($identity, $ip),
                    ':identity' => hash('sha256', strtolower(trim($identity))),
                    ':ip' => hash('sha256', trim($ip)),
                    ':now_insert' => $now,
                    ':last_attempt_insert' => $now,
                    ':window_for_count' => $windowStart,
                    ':window_for_start' => $windowStart,
                    ':now_for_start' => $now,
                    ':max_attempts' => $this->maxAttempts,
                    ':blocked_until' => $blockedUntil,
                    ':now_for_attempt' => $now,
                ]
            );
        } catch (\Throwable) {
        }
    }

    public function success(string $identity, string $ip): void
    {
        try {
            $this->db->query(
                'DELETE FROM s_auth_attempt WHERE bucket_hash = :bucket',
                [':bucket' => $this->bucket($identity, $ip)]
            );
        } catch (\Throwable) {
        }
    }

    private function bucket(string $identity, string $ip): string
    {
        return hash('sha256', strtolower(trim($identity)) . "\0" . trim($ip));
    }
}
