<?php
namespace Core\Sys;

use RuntimeException;

class TelemetryService {
    private Database $db;
    private ErrorHandler $errorHandler;
    private array $config;

    public function __construct(Database $db, ErrorHandler $errorHandler = null, array $config = []) {
        $this->db = $db;
        $this->errorHandler = $errorHandler;
        $this->config = $config;
    }

    public function recordEvent(array $payload): int {
        $params = [
            ':uid' => $this->db->generateUuidV4(),
            ':created_at' => $payload['created_at'] ?? date('Y-m-d H:i:s'),
            ':component_type' => $payload['component_type'] ?? 'custom',
            ':component_ref' => $payload['component_ref'] ?? 'unknown',
            ':severity' => $payload['severity'] ?? 'info',
            ':status_code' => $payload['status_code'] ?? null,
            ':duration_ms' => $payload['duration_ms'] ?? null,
            ':message' => $payload['message'] ?? null,
            ':context_json' => !empty($payload['context']) ? json_encode($payload['context']) : null,
            ':user_id' => $payload['user_id'] ?? null,
            ':space_id' => $payload['space_id'] ?? null,
            ':correlation_id' => $payload['correlation_id'] ?? null,
        ];
        $sql = "INSERT INTO s_telemetry_event
            (uid, created_at, component_type, component_ref, severity, status_code, duration_ms, message, context_json, user_id, space_id, correlation_id)
            VALUES (:uid, :created_at, :component_type, :component_ref, :severity, :status_code, :duration_ms, :message, :context_json, :user_id, :space_id, :correlation_id)";
        try {
            $this->db->query($sql, $params);
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->logSql(['query' => $sql, 'params' => $params]);
            }
            return 0;
        }
        return 1;
    }

    public function listEvents(array $filters = [], int $limit = 100): array {
        $criteria = [];
        if (!empty($filters['component_type'])) {
            $criteria['component_type'] = $filters['component_type'];
        }
        if (!empty($filters['component_ref'])) {
            $criteria['component_ref'] = $filters['component_ref'];
        }
        if (!empty($filters['severity'])) {
            $criteria['severity'] = $filters['severity'];
        }
        $order = ['created_at' => 'DESC'];
        $limitStr = $limit > 0 ? $limit : null;
        return $this->db->select('s_telemetry_event', $criteria, true, $order, $limitStr);
    }

    public function listRollups(array $filters = [], int $limit = 100): array {
        $criteria = [];
        if (!empty($filters['component_type'])) {
            $criteria['component_type'] = $filters['component_type'];
        }
        if (!empty($filters['component_ref'])) {
            $criteria['component_ref'] = $filters['component_ref'];
        }
        if (!empty($filters['period_granularity'])) {
            $criteria['period_granularity'] = $filters['period_granularity'];
        }
        $order = ['period_start' => 'DESC'];
        $limitStr = $limit > 0 ? $limit : null;
        return $this->db->select('s_telemetry_rollup', $criteria, true, $order, $limitStr);
    }

    public function summary(): array {
        $counts = [
            'events' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];
        try {
            $rows = $this->db->query("SELECT severity, COUNT(*) as cnt FROM s_telemetry_event GROUP BY severity");
            foreach ($rows as $row) {
                $sev = strtolower($row['severity'] ?? '');
                $counts['events'] += (int)$row['cnt'];
                if (isset($counts[$sev])) {
                    $counts[$sev] = (int)$row['cnt'];
                }
            }
        } catch (\Throwable $e) {
            // Ignore; return zeros
        }
        return $counts;
    }

    public function getConfig(): array {
        $rows = $this->db->select('s_telemetry_config', [], true, ['id' => 'DESC'], 1);
        if (!empty($rows)) {
            return $rows[0];
        }
        return [
            'enabled' => 'Y',
            'sampling_rate' => 100,
            'retention_days' => 30,
            'collect_requests' => 'Y',
            'collect_errors' => 'Y',
            'collect_jobs' => 'Y',
        ];
    }

    public function saveConfig(array $payload): void {
        $existing = $this->db->select('s_telemetry_config', [], true, ['id' => 'DESC'], 1);
        $data = [
            ':enabled' => $payload['enabled'] ?? 'Y',
            ':sampling_rate' => (int)($payload['sampling_rate'] ?? 100),
            ':retention_days' => (int)($payload['retention_days'] ?? 30),
            ':collect_requests' => $payload['collect_requests'] ?? 'Y',
            ':collect_errors' => $payload['collect_errors'] ?? 'Y',
            ':collect_jobs' => $payload['collect_jobs'] ?? 'Y',
        ];
        if (!empty($existing)) {
            $data[':id'] = $existing[0]['id'];
            $sql = "UPDATE s_telemetry_config SET enabled = :enabled, sampling_rate = :sampling_rate, retention_days = :retention_days, collect_requests = :collect_requests, collect_errors = :collect_errors, collect_jobs = :collect_jobs, updated_at = NOW() WHERE id = :id";
        } else {
            $sql = "INSERT INTO s_telemetry_config (enabled, sampling_rate, retention_days, collect_requests, collect_errors, collect_jobs, updated_at) VALUES (:enabled, :sampling_rate, :retention_days, :collect_requests, :collect_errors, :collect_jobs, NOW())";
        }
        $this->db->query($sql, $data);
    }

    public function listTokens(): array {
        return $this->db->select('s_telemetry_token', [], true, ['created_at' => 'DESC']);
    }

    public function createToken(array $scopes, ?string $expiresAt = null): array {
        $tokenPlain = bin2hex(random_bytes(16));
        $hash = hash('sha256', $tokenPlain);
        $data = [
            ':uid' => $this->db->generateUuidV4(),
            ':token_hash' => $hash,
            ':scopes' => implode(',', array_unique($scopes)),
            ':expires_at' => $expiresAt,
            ':status' => 'active',
            ':created_at' => date('Y-m-d H:i:s'),
        ];
        $sql = "INSERT INTO s_telemetry_token (uid, token_hash, scopes, expires_at, status, created_at) VALUES (:uid, :token_hash, :scopes, :expires_at, :status, :created_at)";
        $this->db->query($sql, $data);
        return ['token' => $tokenPlain, 'scopes' => $data[':scopes'], 'expires_at' => $expiresAt];
    }

    public function revokeToken(int $id): void {
        $this->db->update('s_telemetry_token', ['status' => 'revoked'], ['id' => $id]);
    }

    public function ingestFromLogs(?string $date = null, int $limit = 1000): array {
        $logDir = $this->config['dir']['log'] ?? dirname(__DIR__, 2) . '/log';
        $date = $date ?: date('Y/m/d');
        $parts = explode('/', $date);
        if (count($parts) === 1) {
            $datePath = date('Y/m/d', strtotime($date));
        } else {
            $datePath = sprintf('%s/%s/%s', $parts[0], $parts[1], $parts[2]);
        }

        $accessFile = rtrim($logDir, '/') . '/' . $datePath . '/access.log';
        $errorFile = rtrim($logDir, '/') . '/' . $datePath . '/error.log';

        $ingested = 0;
            $limit = max(1, $limit);

        if (is_file($accessFile)) {
            $lines = @file($accessFile, FILE_IGNORE_NEW_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    if ($ingested >= $limit) { break; }
                    $parsed = $this->parseAccessLogLine($line);
                    if ($parsed === null) { continue; }
                    $uri = $parsed['uri'] ?? '';
                    if ($uri === '' || strpos($uri, '/rad-admin') === 0) { continue; }
                    $this->recordEvent([
                        'component_type' => 'route',
                        'component_ref' => $uri,
                        'severity' => 'info',
                        'duration_ms' => isset($parsed['execution_time']) ? (int)round($parsed['execution_time'] * 1000) : null,
                        'message' => ($parsed['method'] ?? 'GET') . ' ' . $uri,
                        'context' => ['ip' => $parsed['ip'] ?? null],
                        'created_at' => $parsed['timestamp'] ?? null,
                    ]);
                    $ingested++;
                }
            }
        }

        if (is_file($errorFile)) {
            $lines = @file($errorFile, FILE_IGNORE_NEW_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    if ($ingested >= $limit) { break; }
                    $parsed = $this->parseErrorLogLine($line);
                    if ($parsed === null) { continue; }
                    $file = $parsed['file'] ?? '';
                    if ($file !== '' && strpos($file, '/rad/admin/') !== false) { continue; }
                    $this->recordEvent([
                        'component_type' => 'error',
                        'component_ref' => $file ?: 'application',
                        'severity' => 'high',
                        'message' => $parsed['message'] ?? 'Error',
                        'context' => ['line' => $parsed['line'] ?? null, 'reference' => $parsed['reference'] ?? null],
                        'created_at' => $parsed['timestamp'] ?? null,
                    ]);
                    $ingested++;
                }
            }
        }

        return ['ingested' => $ingested];
    }

    private function parseAccessLogLine(string $line): ?array {
        if (!preg_match('/^\\[(.*?)\\]:\\s*(\\{.*\\})$/', $line, $m)) {
            return null;
        }
        $timestamp = trim($m[1]);
        $json = $m[2];
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        $data['timestamp'] = $timestamp;
        return $data;
    }

    private function parseErrorLogLine(string $line): ?array {
        if (!preg_match('/^\\[(.*?)\\]:\\s*(.+)$/', $line, $m)) {
            return null;
        }
        $timestamp = trim($m[1]);
        $rest = $m[2];
        $file = null;
        $lineNo = null;
        if (preg_match('/in\\s+(.*)\\((\\d+)\\)/', $rest, $fm)) {
            $file = $fm[1];
            $lineNo = (int)$fm[2];
        }
        return [
            'timestamp' => $timestamp,
            'message' => $rest,
            'file' => $file,
            'line' => $lineNo,
        ];
    }
}
