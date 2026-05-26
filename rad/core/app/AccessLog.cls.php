<?php
namespace Core\App;

/**
 * Access log reader for applications.
 * Provides simple, paginated log access (latest-first) with filters.
 */
class AccessLog {
    private $logDir;

    public function __construct(array $config) {
        $this->logDir = rtrim($config['dir']['log'] ?? '', '/');
    }

    /**
     * Get recent access log entries (latest first).
     *
     * @param int $limit Number of lines to return
     * @param string|null $date Optional date folder (YYYY/MM/DD); uses latest log if null
     * @return array List of log lines
     */
    public function recent(int $limit = 50, ?string $date = null): array {
        $file = $this->resolveLogFile('access.log', $date);
        return $this->tail($file, $limit);
    }

    private function resolveLogFile(string $filename, ?string $date): string {
        if (!$date) {
            // find latest existing log file
            $pattern = $this->logDir . '/*/*/*/' . $filename;
            $files = glob($pattern);
            rsort($files);
            return $files[0] ?? '';
        }
        return $this->logDir . '/' . $date . '/' . $filename;
    }

    private function tail(string $file, int $limit): array {
        if ($file === '' || !is_file($file)) {
            return [];
        }
        $lines = [];
        $log = new \SplFileObject($file, 'r');
        $log->seek(PHP_INT_MAX);
        $last = $log->key();
        $start = max($last - $limit + 1, 0);
        $log->seek($start);
        while (!$log->eof()) {
            $line = trim((string)$log->fgets());
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return array_reverse($lines);
    }
}
