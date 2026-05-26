<?php
namespace Core\Sys;

class Logger {
    private $logDir;
    private $maxFileSize;
    private array $context = [];
    private array $activityContext = [];

    public function __construct($logDir) {
        $this->logDir = $logDir;
        $this->maxFileSize = 10 * 1024 * 1024; // 10 MB default cap per daily log
    }

    public function setContext(array $context): void {
        $this->context = $context;
    }

    public function setActivityContext(array $context): void {
        $this->activityContext = $context;
    }

    public function logError($message, array $extraContext = []) {
        $context = [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'host' => $_SERVER['HTTP_HOST'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
        if (!empty($_SESSION['entity_id'])) {
            $context['user_id'] = $_SESSION['entity_id'];
        }
        if (!empty($_SESSION['fullname'])) {
            $context['user_name'] = $_SESSION['fullname'];
        }
        if (!empty($extraContext)) {
            $context = array_merge($context, $extraContext);
        }
        $contextJson = json_encode($context);
        $this->log('error.log', $message . ' || ' . $contextJson);
    }

    public function logAccess($executionTime) {
        $dateTime = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
        $executionTime = round($executionTime, 3);
        $sessionKey = session_id();
        $message = [
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'uri' => $requestUri,
            'method' => $requestMethod,
            'execution_time' => $executionTime,
        ];
        if (!empty($sessionKey)) {
            $message['session_key'] = $sessionKey;
        }
        if (isset($_SESSION['entity_id'])) {
            $message['user_id'] = $_SESSION['entity_id'];
        }
        if (!empty($_SESSION['username'])) {
            $message['username'] = $_SESSION['username'];
        }
        if (!empty($_SESSION['fullname'])) {
            $message['user_fullname'] = $_SESSION['fullname'];
        }
        $activity = $this->resolveActivityMetadata();
        if (!empty($activity)) {
            $message = array_merge($message, $activity);
        }
        $message = json_encode($message);
        // ensure that the 

        // $message = "IP=$ipAddress, UA=$userAgent, URI=$requestUri, METHOD=$requestMethod, EXECUTION_TIME=$executionTime sec";
        $this->log('access.log', $message);
    }

    private function log($fileName, $message) {
        $date = date('Y-m-d H:i:s');
        $currentLogDir = $this->logDir . '/' . date('Y') . '/' . date('m') . '/' . date('d') . '/' . date('H');

        if (!file_exists($currentLogDir)) {
            mkdir($currentLogDir, 0777, true);
        }

        $logFile = $currentLogDir . '/' . $fileName;
        $this->rotateIfNeeded($logFile);

        $msg = sprintf("[%s]: %s\n", $date, $message);
        file_put_contents($logFile, $msg, FILE_APPEND);
    }

    public function logSql($sql) {
        if (is_array($sql)) {
            $sql = json_encode($sql, true);
        }
        $this->log('sql.log', $sql);
    }

    public function logQueue(string $message, array $context = []): void {
        $payload = [
            'message' => $message,
            'context' => $context,
        ];
        $this->log('queue.log', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function rotateIfNeeded(string $logFile): void {
        if (!is_file($logFile)) {
            return;
        }
        clearstatcache(true, $logFile);
        if (filesize($logFile) < $this->maxFileSize) {
            return;
        }
        $archiveName = $logFile . '.' . date('His');
        @rename($logFile, $archiveName);
    }

    private function resolveActivityMetadata(): array {
        $activity = $this->activityContext;
        if (empty($activity) && class_exists('\\Core\\Sys\\ActivityContext')) {
            $activity = \Core\Sys\ActivityContext::pull();
        }
        if (empty($activity)) {
            $activity = $this->context['activity'] ?? [];
        }

        if (empty($activity)) {
            return [];
        }

        $payload = [];
        $profiles = $activity['activity_profiles'] ?? $activity['profiles'] ?? null;
        $profileKey = $activity['activity_profile'] ?? $activity['profile'] ?? null;
        $profileMeta = null;
        if (is_array($profiles) && $profileKey && isset($profiles[$profileKey]) && is_array($profiles[$profileKey])) {
            $profileMeta = $profiles[$profileKey];
        }

        $label = trim((string)($activity['activity_label'] ?? $activity['label'] ?? ($profileMeta['label'] ?? '')));
        if ($label !== '') {
            $payload['activity_label'] = $label;
        }
        if (array_key_exists('activity_notify', $activity) || array_key_exists('notify', $activity) || isset($profileMeta['notify'])) {
            $payload['activity_notify'] = !empty($activity['activity_notify'] ?? $activity['notify'] ?? $profileMeta['notify']);
        }
        if (!empty($activity['activity_severity']) || !empty($activity['severity']) || !empty($profileMeta['severity'])) {
            $payload['activity_severity'] = strtolower((string)($activity['activity_severity'] ?? $activity['severity'] ?? $profileMeta['severity']));
        }
        if (!empty($activity['activity_profile'])) {
            $payload['activity_profile'] = (string)$activity['activity_profile'];
        }
        foreach (['space_id', 'ms_id', 'ms_name', 'route_id', 'route_uid', 'route_slug', 'route_name', 'activity_notified', 'event_type', 'activity_event'] as $key) {
            if (array_key_exists($key, $activity) && $activity[$key] !== null && $activity[$key] !== '') {
                $payload[$key] = $activity[$key];
            }
        }
        return $payload;
    }
}
