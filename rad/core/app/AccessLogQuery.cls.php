<?php
namespace Core\App;

class AccessLogQuery {
    private $db;
    private $config;
    private $logDir;

    public function __construct($db, array $config = []) {
        $this->db = $db;
        $this->config = $config;
        $this->logDir = rtrim($config['dir']['log'] ?? dirname(__DIR__, 2) . '/log', '/');
    }

    public function getRecycleFile(int $viewerEntityId = 0): string {
        $suffix = $viewerEntityId > 0 ? '_' . $viewerEntityId : '_0';
        return $this->logDir . '/accesslog_recycle' . $suffix . '.json';
    }

    public function readRecycleIds(int $viewerEntityId = 0): array {
        $file = $this->getRecycleFile($viewerEntityId);
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        $data = json_decode((string)$raw, true);
        return is_array($data) ? array_values(array_unique($data)) : [];
    }

    public function writeRecycleIds(int $viewerEntityId, array $ids): array {
        $file = $this->getRecycleFile($viewerEntityId);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return is_string($id) || is_numeric($id);
        })));
        @file_put_contents($file, json_encode($ids));
        return $ids;
    }

    public function query(array $filters, int $page = 1, int $limit = 50, array $options = []): array {
        $page = max(1, $page);
        $limit = max(1, min(200, $limit));
        $offset = ($page - 1) * $limit;
        $viewerEntityId = (int)($options['viewer_entity_id'] ?? 0);
        $recycleIds = $options['recycle_ids'] ?? $this->readRecycleIds($viewerEntityId);

        $entries = [];
        foreach ($this->buildFilesList($filters['from'] ?? null, $filters['to'] ?? null) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach (array_reverse($lines) as $line) {
                $parsed = $this->parseAccessLine($line);
                if (!$parsed) {
                    continue;
                }
                if (in_array($parsed['id'], $recycleIds, true)) {
                    continue;
                }
                $entries[] = $parsed;
            }
        }

        if (!empty($entries)) {
            $this->enrichUserDisplayNames($entries);
        }

        $filtered = [];
        foreach ($entries as $entry) {
            if ($this->matchesFilters($entry, $filters, $options)) {
                $filtered[] = $entry;
            }
        }

        $stats = $this->buildStats($filtered);
        $hasMore = count($filtered) > ($offset + $limit);

        return [
            'entries' => array_slice($filtered, $offset, $limit),
            'stats' => $stats,
            'has_more' => $hasMore,
            'total_filtered' => count($filtered),
            'recycle_count' => count($recycleIds),
        ];
    }

    public function purgeOlderThanYears(int $years): int {
        $cutoff = strtotime('-' . $years . ' years');
        $removed = 0;
        foreach ($this->buildFilesList(null, null) as $file) {
            $fileDate = $this->resolveFileDate($file);
            if ($fileDate !== null && $fileDate < $cutoff) {
                @unlink($file);
                $removed++;
            }
        }
        return $removed;
    }

    private function parseAccessLine(string $line): ?array {
        if (!preg_match('/^\[(.*?)\]:\s*(\{.*\})$/', trim($line), $matches)) {
            return null;
        }
        $timestamp = trim($matches[1]);
        $json = $matches[2];
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        if (empty($data['ip'])) {
            $data['ip'] = $data['ip_address'] ?? ($data['remote_addr'] ?? ($data['client_ip'] ?? ''));
        }
        if (empty($data['user_fullname'])) {
            $data['user_fullname'] = $data['user_name'] ?? '';
        }
        if (empty($data['username'])) {
            $data['username'] = $data['user'] ?? ($data['auth_user'] ?? '');
        }
        $data['timestamp'] = $timestamp;
        $data['id'] = sha1($timestamp . '|' . $json);
        return $data;
    }

    private function buildFilesList(?string $from, ?string $to): array {
        $files = array_merge(
            glob($this->logDir . '/*/*/*/access.log') ?: [],
            glob($this->logDir . '/*/*/*/*/access.log') ?: []
        );
        if (empty($files)) {
            return [];
        }
        $fromTs = $from ? strtotime($from . ' 00:00:00') : null;
        $toTs = $to ? strtotime($to . ' 23:59:59') : null;
        $files = array_filter(array_values(array_unique($files)), function ($file) use ($fromTs, $toTs) {
            $fileDate = $this->resolveFileDate($file);
            if ($fileDate === null) {
                return false;
            }
            if ($fromTs && $fileDate < $fromTs) {
                return false;
            }
            if ($toTs && $fileDate > $toTs) {
                return false;
            }
            return true;
        });
        rsort($files);
        return $files;
    }

    private function resolveFileDate(string $file): ?int {
        $parts = explode('/', trim($file, '/'));
        $count = count($parts);
        if ($count < 5) {
            return null;
        }
        $fileName = $parts[$count - 1];
        if (strcasecmp($fileName, 'access.log') !== 0) {
            return null;
        }
        $ddIndex = $count - 2;
        $mmIndex = $count - 3;
        $yyIndex = $count - 4;

        if ($count >= 6) {
            $hourPart = $parts[$count - 2];
            if (ctype_digit((string)$hourPart) && (int)$hourPart >= 0 && (int)$hourPart <= 23) {
                $ddIndex = $count - 3;
                $mmIndex = $count - 4;
                $yyIndex = $count - 5;
            }
        }

        $year = $parts[$yyIndex] ?? '';
        $month = $parts[$mmIndex] ?? '';
        $day = $parts[$ddIndex] ?? '';
        if (!ctype_digit((string)$year) || !ctype_digit((string)$month) || !ctype_digit((string)$day)) {
            return null;
        }
        $dateStr = sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day);
        $ts = strtotime($dateStr);
        return $ts ?: null;
    }

    private function enrichUserDisplayNames(array &$entries): void {
        $userMap = [];
        $ids = [];
        $idents = [];
        foreach ($entries as $entry) {
            $userKey = $entry['user'] ?? ($entry['auth_user'] ?? ($entry['username'] ?? ($entry['user_id'] ?? '')));
            if ($userKey === '' || $userKey === null) {
                continue;
            }
            if (ctype_digit((string)$userKey)) {
                $ids[] = (int)$userKey;
            } else {
                $idents[] = (string)$userKey;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $idents = array_values(array_unique(array_filter($idents)));

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $this->db->query("SELECT id, s_name, s_identity FROM s_entity WHERE id IN ($placeholders)", $ids);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $name = (string)($row['s_name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    if (isset($row['id'])) {
                        $userMap[(string)$row['id']] = $name;
                    }
                    if (!empty($row['s_identity'])) {
                        $userMap[(string)$row['s_identity']] = $name;
                    }
                }
            }
        }

        if (!empty($idents)) {
            $placeholders = implode(',', array_fill(0, count($idents), '?'));
            $rows = $this->db->query("SELECT id, s_name, s_identity FROM s_entity WHERE s_identity IN ($placeholders)", $idents);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $name = (string)($row['s_name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    if (!empty($row['s_identity'])) {
                        $userMap[(string)$row['s_identity']] = $name;
                    }
                    if (isset($row['id'])) {
                        $userMap[(string)$row['id']] = $name;
                    }
                }
            }
        }

        foreach ($entries as &$entry) {
            $userKey = (string)($entry['user'] ?? ($entry['auth_user'] ?? ($entry['username'] ?? ($entry['user_id'] ?? ''))));
            $entry['user_display'] = (string)($entry['user_fullname'] ?? '');
            if ($entry['user_display'] === '' && $userKey !== '' && isset($userMap[$userKey])) {
                $entry['user_display'] = $userMap[$userKey];
            }
            if ($entry['user_display'] === '') {
                $entry['user_display'] = (string)($entry['user'] ?? ($entry['auth_user'] ?? ($entry['username'] ?? '')));
            }
        }
        unset($entry);
    }

    private function matchesFilters(array $entry, array $filters, array $options): bool {
        $ts = isset($entry['timestamp']) ? strtotime((string)$entry['timestamp']) : null;
        if (!empty($filters['from_ts']) && $ts && $ts < (int)$filters['from_ts']) {
            return false;
        }
        if (!empty($filters['to_ts']) && $ts && $ts > (int)$filters['to_ts']) {
            return false;
        }

        $path = (string)($entry['uri'] ?? $entry['path'] ?? '');
        if ($path !== '' && strpos($path, '/rad-admin/') === 0) {
            return false;
        }

        if (!empty($options['force_space'])) {
            if (!$this->matchesSpace($entry, $path, $options['force_space'])) {
                return false;
            }
        }

        if (!empty($filters['space'])) {
            if (!$this->matchesSpace($entry, $path, $filters['space'])) {
                return false;
            }
        }

        if (!empty($filters['ip']) && stripos((string)($entry['ip'] ?? ''), (string)$filters['ip']) === false) {
            return false;
        }

        $userFields = [
            (string)($entry['user_display'] ?? ''),
            (string)($entry['user_fullname'] ?? ''),
            (string)($entry['user'] ?? ''),
            (string)($entry['auth_user'] ?? ''),
            (string)($entry['username'] ?? ''),
            (string)($entry['user_id'] ?? ''),
        ];
        if (!empty($filters['user'])) {
            $match = false;
            foreach ($userFields as $value) {
                if ($value !== '' && stripos($value, (string)$filters['user']) !== false) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                return false;
            }
        }

        if (!empty($filters['method']) && strcasecmp((string)($entry['method'] ?? ''), (string)$filters['method']) !== 0) {
            return false;
        }
        if (($filters['status'] ?? '') !== '' && (string)($entry['status'] ?? '') !== (string)$filters['status']) {
            return false;
        }
        if (!empty($filters['term'])) {
            $blob = json_encode($entry);
            if ($blob === false || stripos($blob, (string)$filters['term']) === false) {
                return false;
            }
        }
        if (array_key_exists('hour', $filters) && $filters['hour'] !== null && $filters['hour'] !== '') {
            if (!$ts) {
                return false;
            }
            if ((int)date('G', $ts) !== (int)$filters['hour']) {
                return false;
            }
        }
        if (!empty($filters['only_unauth']) && !$this->isUnauthorized($entry)) {
            return false;
        }
        if (!empty($filters['show_api']) && stripos($path, '/api') !== 0) {
            return false;
        }
        if (!empty($filters['show_admin'])) {
            $isAdminPath = strpos($path, '/management/') === 0 || strpos($path, '/sysadmin/') === 0;
            if (!$isAdminPath) {
                return false;
            }
        }
        if (!empty($filters['show_public'])) {
            if (strpos($path, '/api') === 0 || strpos($path, '/management/') === 0 || strpos($path, '/sysadmin/') === 0) {
                return false;
            }
        }
        if (!empty($filters['only_signin'])) {
            $blob = strtolower((string)json_encode($entry));
            if (strpos($blob, 'login') === false && strpos($blob, 'signin') === false) {
                return false;
            }
        }
        if (!empty($filters['only_download'])) {
            $blob = strtolower((string)json_encode($entry));
            if (strpos($blob, 'download') === false && strpos($blob, 'export') === false) {
                return false;
            }
        }

        return true;
    }

    private function matchesSpace(array $entry, string $path, $spaceFilter): bool {
        $spaceFilter = is_scalar($spaceFilter) ? trim((string)$spaceFilter) : '';
        if ($spaceFilter === '') {
            return true;
        }
        $spaceNeedle = '/' . trim($spaceFilter, '/') . '/';
        if ($path !== '' && strpos($path, $spaceNeedle) !== false) {
            return true;
        }
        if (isset($entry['space_uid']) && (string)$entry['space_uid'] === $spaceFilter) {
            return true;
        }
        if (isset($entry['space_id']) && (string)$entry['space_id'] === $spaceFilter) {
            return true;
        }
        return false;
    }

    private function isUnauthorized(array $entry): bool {
        $status = (int)($entry['status'] ?? 0);
        $result = strtolower((string)($entry['result'] ?? ''));
        return $status >= 400 || strpos($result, 'unauth') !== false || strpos($result, 'denied') !== false;
    }

    private function buildStats(array $entries): array {
        $stats = [
            'total' => count($entries),
            'unauthorized' => 0,
            'by_result' => [],
            'by_hour' => array_fill(0, 24, 0),
        ];
        foreach ($entries as $entry) {
            $status = (int)($entry['status'] ?? 0);
            $result = strtolower((string)($entry['result'] ?? ''));
            if ($result === '' && $status > 0 && $status < 400) {
                $result = 'ok';
            }
            if ($this->isUnauthorized($entry)) {
                $stats['unauthorized']++;
            }
            $resultKey = $result !== '' ? $result : ($status ? (string)$status : 'unknown');
            $stats['by_result'][$resultKey] = ($stats['by_result'][$resultKey] ?? 0) + 1;
            $ts = isset($entry['timestamp']) ? strtotime((string)$entry['timestamp']) : null;
            if ($ts) {
                $hour = (int)date('G', $ts);
                $stats['by_hour'][$hour] = ($stats['by_hour'][$hour] ?? 0) + 1;
            }
        }
        return $stats;
    }
}
