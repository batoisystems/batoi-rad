<?php
namespace RadAdmin;

use Core\Sys\IpAccessService;
use Core\Sys\PrivilegeService;

class Ipaccess {
    private array $runData = [];
    private PrivilegeService $privileges;
    private IpAccessService $ipAccessService;
    private string $configFile;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->privileges = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
        $this->ipAccessService = new IpAccessService();
        $this->configFile = rtrim($runData['config']['dir']['admin'] ?? '', '/') . '/ip-access.config.php';
    }

    public function view() {
        if ($this->privileges->role() !== 'system_admin') {
            throw new \Exception('Access denied.', 403);
        }

        $this->runData['route']['h1'] = 'IP Access Control';
        $this->runData['route']['meta_title'] = 'IP Access Control';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'IP Access Control' => '',
        ];

        $current = $this->runData['config']['rad']['ip_restrict'] ?? ($this->runData['config']['ip_restrict'] ?? []);
        $rule = $this->ipAccessService->normalizeRule(!empty($current['enabled']), $current['ip'] ?? '');

        if ($this->runData['request']->method === 'POST') {
            $token = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($token)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Security check failed. Refresh the page and try again.';
            } else {
                $enabled = !empty($this->runData['request']->post['enabled']);
                $rawIps = $this->runData['request']->post['ip'] ?? '';
                $candidate = $this->ipAccessService->normalizeRule($enabled, $rawIps);
                if (!empty($candidate['invalid'])) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Invalid IP entries: ' . implode(', ', $candidate['invalid']);
                    $rule = $candidate;
                } elseif ($enabled && empty($candidate['ips'])) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Add at least one valid IP before enabling RAD Admin restriction.';
                    $rule = $candidate;
                } else {
                    $config = [
                        'ip_restrict' => [
                            'enabled' => $enabled,
                            'ip' => implode(',', $candidate['ips']),
                        ],
                        'rad' => [
                            'ip_restrict' => [
                                'enabled' => $enabled,
                                'ip' => implode(',', $candidate['ips']),
                            ],
                        ],
                    ];
                    $backup = $this->writeConfig($config);
                    $this->runData['request']->setAlert(
                        'IP access settings saved. Backup created at <code>' . htmlspecialchars($backup, ENT_QUOTES, 'UTF-8') . '</code>.',
                        'success'
                    );
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/ipaccess/view');
                    exit;
                }
            }
        }

        $this->runData['data']['ip_access_rule'] = $rule;
        $this->runData['data']['ip_access_config_file'] = $this->configFile;
        $this->runData['data']['current_client_ip'] = $this->ipAccessService->getClientIp();
        return $this->runData;
    }

    private function loadConfig(): array {
        if (!file_exists($this->configFile)) {
            return [];
        }
        $data = include $this->configFile;
        return is_array($data) ? $data : [];
    }

    private function writeConfig(array $config): string {
        $backupDir = dirname($this->configFile) . '/.backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        $backupFile = $backupDir . '/rad.config.' . date('Ymd_His') . '.php';
        if (file_exists($this->configFile)) {
            @copy($this->configFile, $backupFile);
        }
        $export = "<?php\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents($this->configFile, $export) === false) {
            throw new \RuntimeException('Unable to write rad.config.php');
        }
        return $backupFile;
    }
}
