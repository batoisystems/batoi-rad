<?php
namespace RadAdmin;

/**
 * Static Code Analysis (read-only) for application code excluding RAD Admin and vendor.
 */
class Sca {
    private array $runData = [];
    private $db;
    private $errorHandler;
    private ?array $scanCache = null;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
    }

    public function view() {
        $report = $this->runScan();
        $this->runData['data']['sca'] = $report;
        $this->runData['route']['h1'] = 'Static Code Analysis';
        $this->runData['route']['meta_title'] = 'Static Code Analysis';
        $this->runData['route']['breadcrumb'] = ['Static Code Analysis' => ''];
        return $this->runData;
    }

    public function metrics() {
        $report = $this->runScan();
        $severity = $report['severity_counts'] ?? ['high' => 0, 'medium' => 0, 'low' => 0];
        $rules = $report['rule_counts'] ?? [];
        arsort($rules);
        $rules = array_slice($rules, 0, 6, true);
        $modules = $report['module_counts'] ?? [];

        header('Content-Type: application/json');
        echo json_encode([
            'severity' => $severity,
            'rules' => $rules,
            'modules' => $modules,
            'files_scanned' => $report['files_scanned'] ?? 0,
            'findings' => $report['findings_total'] ?? 0,
        ]);
        exit;
    }

    public function export() {
        $report = $this->runScan();
        $html = $this->renderHtmlSummary($report);
        $format = strtolower($this->runData['route']['pathparts'][3] ?? 'html');

        if ($format === 'pdf') {
            $tcpdfPath = $this->runData['config']['dir']['vendor'] . '/tcpdf/tcpdf.php';
            if (!file_exists($tcpdfPath)) {
                $this->runData['request']->setAlert('PDF export requires TCPDF under rad/vendor/tcpdf.', 'danger');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/sca/view');
                exit;
            }
            require_once $tcpdfPath;
            $pdf = new \TCPDF();
            $pdf->SetTitle('Static Code Analysis');
            $pdf->AddPage();
            $pdf->writeHTML($html);
            $pdf->Output('static-code-analysis.pdf', 'I');
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    private function runScan(): array {
        if ($this->scanCache !== null) {
            return $this->scanCache;
        }

        $root = $this->runData['config']['dir']['site'] ?? dirname(__DIR__, 2);
        $includePaths = [
            $root . '/rad',
            $root . '/public_html',
        ];
        $excludePaths = [
            $root . '/rad/admin',
            $root . '/rad/core',
            $root . '/rad/vendor',
            $root . '/rad/log',
            $root . '/public_html/assets/vendor',
        ];
        $maxFiles = 500;
        $files = $this->collectFiles($includePaths, $excludePaths, $maxFiles);

        $rules = $this->ruleset();
        $findings = [];
        $severityCounts = ['high' => 0, 'medium' => 0, 'low' => 0];
        $ruleCounts = [];
        $moduleCounts = [];

        foreach ($files as $path) {
            $content = @file($path);
            if ($content === false) {
                continue;
            }
            $relPath = $this->relativePath($path);
            $module = $this->inferModule($path);
            if (!isset($moduleCounts[$module])) {
                $moduleCounts[$module] = 0;
            }
            foreach ($content as $idx => $line) {
                foreach ($rules as $ruleId => $rule) {
                    if (preg_match($rule['pattern'], $line)) {
                        $severity = $rule['severity'];
                        $severityCounts[$severity] = ($severityCounts[$severity] ?? 0) + 1;
                        $ruleCounts[$ruleId] = ($ruleCounts[$ruleId] ?? 0) + 1;
                        $moduleCounts[$module]++;
                        $findings[] = [
                            'file' => $relPath,
                            'line' => $idx + 1,
                            'severity' => $severity,
                            'rule' => $rule['title'],
                            'remediation' => $rule['remediation'],
                            'snippet' => trim($line),
                            'module' => $module,
                        ];
                    }
                }
            }
        }

        $this->scanCache = [
            'files_scanned' => count($files),
            'findings_total' => count($findings),
            'severity_counts' => $severityCounts,
            'rule_counts' => $ruleCounts,
            'module_counts' => $moduleCounts,
            'findings' => $findings,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        return $this->scanCache;
    }

    private function collectFiles(array $include, array $exclude, int $maxFiles): array {
        $list = [];
        $seen = 0;
        foreach ($include as $dir) {
            if (!is_dir($dir)) { continue; }
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($seen >= $maxFiles) {
                    return $list;
                }
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) { continue; }
                $path = $file->getPathname();
                if ($this->isExcluded($path, $exclude)) { continue; }
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['php', 'js'], true)) { continue; }
                $list[] = $path;
                $seen++;
            }
        }
        return $list;
    }

    private function isExcluded(string $path, array $excludePaths): bool {
        foreach ($excludePaths as $prefix) {
            if (strpos($path, rtrim($prefix, '/') . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    private function relativePath(string $path): string {
        $root = rtrim($this->runData['config']['dir']['site'] ?? '', '/');
        if ($root !== '' && strpos($path, $root) === 0) {
            $trimmed = substr($path, strlen($root));
            return $trimmed === '' ? '/' : $trimmed;
        }
        return $path;
    }

    private function inferModule(string $path): string {
        if (strpos($path, '/rad/ms/') !== false) {
            return 'microservice';
        }
        if (strpos($path, '/rad/core/') !== false) {
            return 'core';
        }
        if (strpos($path, '/rad/theme/') !== false) {
            return 'theme';
        }
        if (strpos($path, '/public_html/') !== false) {
            return 'public_html';
        }
        return 'other';
    }

    private function ruleset(): array {
        return [
            'eval_call' => [
                'title' => 'Avoid eval()',
                'pattern' => '/\\beval\\s*\\(/i',
                'severity' => 'high',
                'remediation' => 'Replace eval with explicit logic or safe parsing.',
            ],
            'exec_call' => [
                'title' => 'Shell execution',
                'pattern' => '/\\b(shell_exec|exec|system|passthru)\\s*\\(/i',
                'severity' => 'high',
                'remediation' => 'Remove shell calls or sandbox them; validate inputs.',
            ],
            'base64_decode' => [
                'title' => 'Encoded payloads',
                'pattern' => '/base64_decode\\s*\\(/i',
                'severity' => 'medium',
                'remediation' => 'Review encoded payload usage; avoid dynamic code.',
            ],
            'dynamic_include' => [
                'title' => 'Dynamic include/require',
                'pattern' => '/\\b(include|require)(_once)?\\s*\\(\\s*\\$_/i',
                'severity' => 'medium',
                'remediation' => 'Avoid dynamic paths from user input; whitelist includes.',
            ],
            'tainted_concat' => [
                'title' => 'Tainted input concatenation',
                'pattern' => '/\\$_(GET|POST|REQUEST|COOKIE)\\s*\\[[^\\]]+\\]\\s*\\./i',
                'severity' => 'medium',
                'remediation' => 'Sanitize input before concatenation; use parameter binding for SQL.',
            ],
            'todo_fixme' => [
                'title' => 'TODO/FIXME present',
                'pattern' => '/\\b(TODO|FIXME)\\b/',
                'severity' => 'low',
                'remediation' => 'Track and resolve TODO/FIXME items.',
            ],
        ];
    }

    private function renderHtmlSummary(array $report): string {
        $summary = [
            'Files scanned' => $report['files_scanned'] ?? 0,
            'Findings' => $report['findings_total'] ?? 0,
            'High' => $report['severity_counts']['high'] ?? 0,
            'Medium' => $report['severity_counts']['medium'] ?? 0,
            'Low' => $report['severity_counts']['low'] ?? 0,
            'Generated at' => $report['generated_at'] ?? '',
        ];

        $html = '<h1>Static Code Analysis</h1>';
        $html .= '<ul>';
        foreach ($summary as $label => $value) {
            $html .= '<li><strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars((string)$value) . '</li>';
        }
        $html .= '</ul>';

        $html .= '<h2>Findings</h2>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
        $html .= '<tr><th>Severity</th><th>Rule</th><th>File</th><th>Line</th><th>Snippet</th><th>Remediation</th></tr>';
        foreach ($report['findings'] as $finding) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars(ucfirst($finding['severity'])) . '</td>';
            $html .= '<td>' . htmlspecialchars($finding['rule']) . '</td>';
            $html .= '<td>' . htmlspecialchars($finding['file']) . '</td>';
            $html .= '<td>' . (int)$finding['line'] . '</td>';
            $html .= '<td>' . htmlspecialchars($finding['snippet']) . '</td>';
            $html .= '<td>' . htmlspecialchars($finding['remediation']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        $html .= '<h2>Visual Summaries</h2>';
        $html .= '<h3>Severity</h3>' . $this->renderBarTable([
            ['label' => 'High', 'value' => $report['severity_counts']['high'] ?? 0, 'total' => max(1, array_sum($report['severity_counts'] ?? [])), 'color' => '#e74c3c'],
            ['label' => 'Medium', 'value' => $report['severity_counts']['medium'] ?? 0, 'total' => max(1, array_sum($report['severity_counts'] ?? [])), 'color' => '#f39c12'],
            ['label' => 'Low', 'value' => $report['severity_counts']['low'] ?? 0, 'total' => max(1, array_sum($report['severity_counts'] ?? [])), 'color' => '#95a5a6'],
        ]);

        $html .= '<h3>Modules</h3>' . $this->renderBarTable($this->normalizeCounts($report['module_counts'] ?? [], '#3498db'));

        $html .= '<h3>Top Rules</h3>' . $this->renderBarTable($this->normalizeCounts(array_slice($report['rule_counts'] ?? [], 0, 6, true), '#0d6efd', true));

        return $html;
    }

    private function normalizeCounts(array $counts, string $color, bool $isRule = false): array {
        arsort($counts);
        $rows = [];
        $total = array_sum($counts);
        foreach ($counts as $label => $value) {
            $rows[] = [
                'label' => $isRule ? $label : ucfirst($label),
                'value' => (int)$value,
                'total' => max(1, $total),
                'color' => $color,
            ];
        }
        return $rows ?: [['label' => 'None', 'value' => 0, 'total' => 1, 'color' => $color]];
    }

    private function renderBarTable(array $rows): string {
        $barWidth = 180;
        $html = '<table border="0" cellpadding="4" cellspacing="0" width="100%">';
        foreach ($rows as $row) {
            $value = (int)$row['value'];
            $total = (int)$row['total'];
            $pct = $total > 0 ? round(($value / $total) * 100) : 0;
            $fillWidth = (int)round(($pct / 100) * $barWidth);
            $emptyWidth = max(0, $barWidth - $fillWidth);
            $color = $row['color'] ?? '#3498db';
            $html .= '<tr><td width="35%">' . htmlspecialchars($row['label']) . '</td><td width="65%">';
            $html .= '<table border="0" cellspacing="0" cellpadding="0" width="' . $barWidth . '" style="border:1px solid #e0e0e0;"><tr>';
            $html .= '<td width="' . $fillWidth . '" bgcolor="' . htmlspecialchars($color) . '">&nbsp;</td>';
            $html .= '<td width="' . $emptyWidth . '" bgcolor="#f6f8fa">&nbsp;</td>';
            $html .= '</tr></table>';
            $html .= '<div style="font-size:10px;color:#555;margin-top:2px;">' . $value . ' / ' . $total . ' (' . $pct . '%)</div>';
            $html .= '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }
}
