<?php
namespace Core\App;

/**
 * Activity log reader for applications.
 * Fetches recent activity (from s_activity if present) or returns empty when table missing.
 *
 * Usage in a route (rad/ms/{ms}/route.{id}.php):
 * $activity = new \Core\App\Activity($db);
     * $activity->logRoute([
 *     'route_id' => 12,          // or 'route_uid' => '...'
 *     'action' => 'invoke',      // e.g., invoke/create/update
 *     'actor_id' => $entityId,   // optional
 *     'actor_name' => $entityName, // optional
 *     'description' => 'Called tasks list', // optional
     * ]);
     */
class Activity {
    private $db;
    private $config;

    public function __construct($db, array $config = []) {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Attach access-log activity metadata for the current request.
     * This metadata will be picked up by the Logger when access.log is written.
     *
     * Example:
     * $activity->setAccessContext([
     *   'activity_label' => 'Viewed dashboard',
     *   'activity_notify' => false,
     *   'activity_severity' => 'info',
     *   'space_id' => 3,
     *   'ms_name' => 'admin',
     *   'route_id' => 12,
     * ]);
     */
    public function setAccessContext(array $context): void {
        \Core\Sys\ActivityContext::set($context);
    }

    /**
     * Get recent activity records.
     *
     * @param int $limit Number of rows to return
     * @param array $filters Optional keys: user_id, space_id
     * @return array Activity rows (empty if table missing)
     */
    public function recent(int $limit = 50, array $filters = []): array {
        // Graceful fallback if table does not exist
        try {
            $sql = "SELECT * FROM s_activity WHERE 1=1";
            $params = [];
            if (!empty($filters['user_id'])) {
                $sql .= " AND s_actor_id = :uid";
                $params[':uid'] = (int)$filters['user_id'];
            }
            if (!empty($filters['space_id'])) {
                $sql .= " AND space_id = :sid";
                $params[':sid'] = (int)$filters['space_id'];
            }
            $sql .= " ORDER BY createstamp DESC LIMIT {$limit}";
            return $this->db->query($sql, $params);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Log an activity for a route, rendering the route's template when present.
     *
     * $data keys:
     * - route_id or route_uid (required)
     * - action (required, e.g., invoke/create/update)
     * - actor_id (optional), actor_name (optional)
     * - ms_id (optional, will be derived from route if omitted)
     * - description (optional)
     * - space_id (optional)
     *
     * @return int|null Always null; activity is written to filesystem logs for ingestion.
     */
    public function logRoute(array $data): ?int {
        $action = trim($data['action'] ?? '');
        if ($action === '') {
            return null;
        }

        $routeRow = $this->resolveRouteRow($data);
        if (!$routeRow) {
            return null;
        }

        $msRow = $this->resolveMsRow($data, (int)($routeRow['s_ms_id'] ?? 0));
        $actorId = isset($data['actor_id']) ? (int)$data['actor_id'] : null;
        $actorName = $data['actor_name'] ?? '';
        $timestamp = date('Y-m-d H:i:s T');

        $context = [
            '{action}' => $action,
            '{route_id}' => (string)($routeRow['id'] ?? ''),
            '{route_uid}' => $routeRow['uid'] ?? '',
            '{route_name}' => $routeRow['s_name'] ?? '',
            '{route_description}' => $data['description'] ?? ($routeRow['s_description'] ?? ''),
            '{ms_id}' => (string)($msRow['id'] ?? ''),
            '{ms_uid}' => $msRow['uid'] ?? '',
            '{ms_name}' => $msRow['s_name'] ?? '',
            '{actor}' => $actorName,
            '{timestamp}' => $timestamp,
        ];

        $template = $routeRow['s_activity_template'] ?? '';
        $message = $this->renderTemplate($template, $context, sprintf('Route %s: %s', $action, $context['{route_name}']));
        $activityContext = [
            'activity_label' => $message,
            'activity_notify' => $data['activity_notify'] ?? null,
            'activity_severity' => $data['activity_severity'] ?? null,
            'space_id' => isset($data['space_id']) ? (int)$data['space_id'] : 0,
            'ms_id' => $msRow['id'] ?? null,
            'ms_name' => $context['{ms_name}'],
            'route_id' => (int)$routeRow['id'],
            'route_uid' => $routeRow['uid'] ?? '',
            'route_name' => $context['{route_name}'],
            'activity_profile' => $data['activity_profile'] ?? null,
            'activity_profiles' => $data['activity_profiles'] ?? null,
        ];
        \Core\Sys\ActivityContext::set($activityContext);
        return null;
    }

    /**
     * Ingest access log entries into s_activity for fast user timelines.
     *
     * Options:
     * - start (Y-m-d), end (Y-m-d)
     * - log_dir (override)
     * - max_days (int, default 31)
     * - skip_assets (bool, default true)
     *
     * @return array counts: processed, inserted, skipped, days
     */
    public function ingestAccessLogs(array $options = []): array {
        $start = $options['start'] ?? date('Y-m-d');
        $end = $options['end'] ?? $start;
        $maxDays = (int)($options['max_days'] ?? 31);
        $skipAssets = (bool)($options['skip_assets'] ?? true);
        $logDir = $options['log_dir'] ?? ($this->config['dir']['log'] ?? '');

        $startDate = \DateTime::createFromFormat('Y-m-d', $start) ?: new \DateTime();
        $endDate = \DateTime::createFromFormat('Y-m-d', $end) ?: $startDate;
        if ($endDate < $startDate) {
            $tmp = $startDate;
            $startDate = $endDate;
            $endDate = $tmp;
        }

        $days = (int)$startDate->diff($endDate)->days + 1;
        if ($days > $maxDays) {
            $endDate = (clone $startDate)->modify('+' . ($maxDays - 1) . ' days');
            $days = $maxDays;
        }

        $counts = ['processed' => 0, 'inserted' => 0, 'skipped' => 0, 'days' => $days];
        if ($logDir === '' || !is_dir($logDir)) {
            return $counts;
        }

        $dayCursor = clone $startDate;
        while ($dayCursor <= $endDate) {
            $dateStr = $dayCursor->format('Y-m-d');
            $files = $this->getAccessLogFilesForDate($logDir, $dateStr);
            foreach ($files as $file) {
                $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (empty($lines)) {
                    continue;
                }
                $entries = $this->parseAccessLines($lines, $skipAssets);
                if (empty($entries)) {
                    continue;
                }
                $counts['processed'] += count($entries);
                $inserted = $this->persistAccessEntries($entries);
                $counts['inserted'] += $inserted;
                $counts['skipped'] += (count($entries) - $inserted);
            }
            $dayCursor->modify('+1 day');
        }

        return $counts;
    }

    private function renderTemplate(string $template, array $context, string $fallback): string {
        $tpl = trim($template);
        if ($tpl !== '') {
            $rendered = strtr($tpl, $context);
            if ($rendered !== '') {
                return $rendered;
            }
        }
        return $fallback;
    }

    private function resolveRouteRow(array $data): ?array {
        $id = isset($data['route_id']) ? (int)$data['route_id'] : 0;
        $uid = $data['route_uid'] ?? '';
        if ($id > 0) {
            $rows = $this->db->select('s_msroute', ['id' => $id], true);
        } elseif ($uid !== '') {
            $rows = $this->db->select('s_msroute', ['uid' => $uid], true);
        } else {
            return null;
        }
        return $rows[0] ?? null;
    }

    private function resolveMsRow(array $data, int $fallbackMsId): array {
        $msId = isset($data['ms_id']) ? (int)$data['ms_id'] : $fallbackMsId;
        if ($msId <= 0) {
            return [];
        }
        $rows = $this->db->select('s_ms', ['id' => $msId], true);
        return $rows[0] ?? [];
    }

    private function getAccessLogFilesForDate(string $logDir, string $date): array {
        [$year, $month, $day] = explode('-', $date);
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $day = str_pad($day, 2, '0', STR_PAD_LEFT);
        $dayDir = rtrim($logDir, '/') . '/' . $year . '/' . $month . '/' . $day;
        if (!is_dir($dayDir)) {
            return [];
        }
        $files = glob($dayDir . '/*/access.log') ?: [];
        $legacy = $dayDir . '/access.log';
        if (is_file($legacy)) {
            $files[] = $legacy;
        }
        return $files;
    }

    private function parseAccessLines(array $lines, bool $skipAssets): array {
        $entries = [];
        foreach ($lines as $line) {
            if (!preg_match('/^\[(.*?)\]:\s*(\{.*\})$/', trim($line), $matches)) {
                continue;
            }
            $timestamp = $matches[1];
            $payload = json_decode($matches[2], true);
            if (!is_array($payload)) {
                continue;
            }
            $uri = $payload['uri'] ?? '';
            if ($skipAssets && $this->isStaticAsset($uri)) {
                continue;
            }
            $payload['timestamp'] = $timestamp;
            $entries[] = $payload;
        }
        return $entries;
    }

    private function persistAccessEntries(array $entries): int {
        if (empty($entries)) {
            return 0;
        }
        $hashes = [];
        foreach ($entries as $entry) {
            $hashes[] = $this->accessHash($entry);
        }
        $existing = $this->fetchExistingAccessHashes($hashes);

        $inserted = 0;
        foreach ($entries as $entry) {
            $hash = $this->accessHash($entry);
            if (isset($existing[$hash])) {
                continue;
            }
            $actorId = isset($entry['user_id']) ? (int)$entry['user_id'] : null;
            $spaceId = isset($entry['space_id']) ? (int)$entry['space_id'] : 0;
            $timestamp = $entry['timestamp'] ?? date('Y-m-d H:i:s');
            $label = trim((string)($entry['activity_label'] ?? ''));
            if ($label === '') {
                $label = trim(($entry['method'] ?? 'GET') . ' ' . ($entry['uri'] ?? '/'));
            }
            $payload = $entry;
            $payload['log_hash'] = $hash;
            try {
                $createdBy = $actorId ?: 0;
                $uid = $this->db->generateUuidV4();
                $sql = "INSERT INTO s_activity (
                            uid, livestatus, versioncode, wf_status, space_id,
                            createdby, createstamp, updatedby, updatestamp,
                            s_actor_id, s_object_type, s_object_id, s_action, s_message, s_payload
                        ) VALUES (
                            :uid, :livestatus, :versioncode, :wf_status, :space_id,
                            :createdby, :createstamp, :updatedby, :updatestamp,
                            :s_actor_id, :s_object_type, :s_object_id, :s_action, :s_message, :s_payload
                        )";
                $params = [
                    ':uid' => $uid,
                    ':livestatus' => '1',
                    ':versioncode' => 1,
                    ':wf_status' => 0,
                    ':space_id' => $spaceId,
                    ':createdby' => $createdBy,
                    ':createstamp' => $timestamp,
                    ':updatedby' => $createdBy,
                    ':updatestamp' => $timestamp,
                    ':s_actor_id' => $actorId,
                    ':s_object_type' => 'access',
                    ':s_object_id' => $hash,
                    ':s_action' => 'access',
                    ':s_message' => $label,
                    ':s_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
                $this->db->query($sql, $params);
                $inserted++;
                if (!empty($entry['activity_notify']) && empty($entry['activity_notified'])) {
                    $bridge = new \Core\Sys\NotificationBridge($this->db, $this->config);
                    $baseLink = '';
                    if (!empty($entry['host']) && !empty($entry['uri'])) {
                        $baseLink = 'https://' . $entry['host'] . $entry['uri'];
                    }
                    $bridge->notifyFromActivity($entry, [
                        'entity_id' => $actorId ?: 0,
                        'actor_id' => $actorId ?: 0,
                        'space_id' => $spaceId,
                        'link' => $baseLink,
                        'event_type' => $entry['event_type'] ?? $entry['activity_event'] ?? '',
                    ], 'ingest');
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $inserted;
    }

    private function fetchExistingAccessHashes(array $hashes): array {
        $hashes = array_values(array_unique(array_filter($hashes, static function ($val) {
            return $val !== null && $val !== '';
        })));
        if (empty($hashes)) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach ($hashes as $idx => $hash) {
            $ph = ':h' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = $hash;
        }
        $sql = "SELECT s_object_id FROM s_activity WHERE s_object_type = 'access' AND s_object_id IN (" . implode(',', $placeholders) . ")";
        $rows = $this->db->query($sql, $params);
        $existing = [];
        foreach ($rows as $row) {
            $existing[(string)($row['s_object_id'] ?? '')] = true;
        }
        return $existing;
    }

    private function accessHash(array $entry): string {
        $basis = ($entry['timestamp'] ?? '') . '|' . ($entry['session_key'] ?? '') . '|' . ($entry['uri'] ?? '') . '|' . ($entry['method'] ?? '');
        return sprintf('%u', crc32($basis));
    }

    private function isStaticAsset(string $uri): bool {
        $uri = strtolower($uri);
        return str_starts_with($uri, '/assets')
            || str_starts_with($uri, '/rad-assets')
            || preg_match('#\.(css|js|png|jpg|jpeg|gif|svg)$#', $uri);
    }
}
