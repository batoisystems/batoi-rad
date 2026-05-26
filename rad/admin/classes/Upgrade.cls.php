<?php
namespace RadAdmin;

use DateTimeImmutable;
use Core\Sys\ErrorHandler;
use Core\Sys\Logger;
use Core\Sys\UpgradeController as SysUpgradeController;

class Upgrade {
    use AiAssistAware;
    private $runData = [];
    private $upgradeDir = '';
    private $checkpointFile = '';
    private $priv;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->priv = new \Core\Sys\PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
        if (!$this->priv->can('upgrade')) {
            throw new \Exception('Access denied.', 403);
        }
        $radDir = rtrim($this->runData['config']['dir']['rad'], '/');
        $this->upgradeDir = $radDir . '/upgrades';
        $this->checkpointFile = $radDir . '/data/upgrade/checkpoints.json';
        if (!is_dir($this->upgradeDir)) {
            mkdir($this->upgradeDir, 0775, true);
        }
        $checkpointDir = dirname($this->checkpointFile);
        if (!is_dir($checkpointDir)) {
            mkdir($checkpointDir, 0775, true);
        }
    }

    public function view() {
        $this->runData['route']['h1'] = 'Database Upgrades';
        $this->runData['route']['meta_title'] = 'Database Upgrades';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/home/view';

        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Run pending upgrade scripts directly from RAD Admin. Review the output below after every execution.';
        }

        $lastRun = null;
        if (strtoupper($this->runData['request']->method) === 'POST') {
            if (isset($this->runData['request']->post['revert_upgrade'])) {
                $targetId = $this->sanitizeUpgradeId($this->runData['request']->post['revert_upgrade']);
                if ($targetId !== '' && $this->revertUpgrade($targetId)) {
                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = sprintf('Upgrade %s reverted to pending for deployment on other servers.', htmlspecialchars($targetId));
                } else {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Unable to revert upgrade status. Please verify the upgrade ID.';
                }
            } elseif (isset($this->runData['request']->post['run_rollback'])) {
                $targetId = $this->sanitizeUpgradeId($this->runData['request']->post['run_rollback']);
                if ($targetId === '') {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Invalid upgrade identifier for rollback.';
                } else {
                    $rollbackResult = $this->executeRollback($targetId);
                    $this->runData['route']['alert'] = $rollbackResult['success'] ? 'success' : 'danger';
                    $this->runData['route']['alert_message'] = $rollbackResult['message'];
                    $lastRun = [
                        'success' => $rollbackResult['success'],
                        'message' => $rollbackResult['message'],
                        'output' => $rollbackResult['output'],
                        'executed_at' => $rollbackResult['executed_at'],
                        'log_file' => $rollbackResult['log_file'],
                    ];
                }
            } elseif (isset($this->runData['request']->post['run_upgrade'])) {
                $lastRun = $this->executeUpgrades();
                $this->runData['route']['alert'] = $lastRun['success'] ? 'success' : 'danger';
                $this->runData['route']['alert_message'] = $lastRun['message'];
            }
        }

        $this->runData['data']['last_run'] = $lastRun;
        $this->runData['data']['log_tail'] = $this->getLogTail();
        $this->runData['data']['upgrades'] = $this->getUpgradeStatus();

        return $this->runData;
    }

    public function add() {
        $this->runData['route']['h1'] = 'Add Upgrade Script';
        $this->runData['route']['meta_title'] = 'Add Upgrade Script';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/upgrade/view';

        $defaultId = $this->generateUpgradeId();
        $defaultCode = $this->buildDefaultTemplate($defaultId, 'Short summary of this upgrade.');

        $form = [
            'id' => $this->runData['request']->post['upgrade_id'] ?? $defaultId,
            'description' => $this->runData['request']->post['description'] ?? '',
            'code' => $this->runData['request']->post['code'] ?? $defaultCode,
        ];

        if (strtoupper($this->runData['request']->method) === 'POST' && isset($this->runData['request']->post['save_upgrade'])) {
            $errors = $this->validateUpgradeForm($form);
            if (empty($errors)) {
                $filePath = $this->upgradeDir . '/' . $form['id'] . '.php';
                $form['code'] = $this->syncDescriptionInCode($form['code'], $form['description']);
                file_put_contents($filePath, $form['code']);
                chmod($filePath, 0664);

                $this->runData['request']->setAlert('Upgrade script created successfully.', 'success');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/upgrade/view');
                exit;
            } else {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = implode('<br>', $errors);
            }
        }

        $this->runData['data']['form'] = $form;
        return $this->runData;
    }

    public function aiassist() {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload || !isset($payload['content'])) {
            echo json_encode(['error' => 'Invalid data provided']);
            return;
        }

        try {
            $service = $this->getAiAssistService('coding', 'full');
        } catch (\Throwable $e) {
            echo json_encode(['error' => 'AI service is unavailable.']);
            return;
        }

        $metadata = [
            'upgrade_id' => $payload['upgrade_id'] ?? ($this->runData['request']->post['upgrade_id'] ?? ''),
            'description' => $payload['description'] ?? ($this->runData['request']->post['description'] ?? ''),
        ];

        echo json_encode($service->suggest($payload['content'], 'upgrade', $metadata));
    }

    public function edit() {
        $pathParts = $this->runData['route']['pathparts'];
        $id = $pathParts[3] ?? '';
        $id = preg_replace('/[^a-z0-9_\-]/', '', strtolower($id));
        if ($id === '') {
            throw new \Exception('Upgrade identifier missing.', 404);
        }

        $filePath = $this->upgradeDir . '/' . $id . '.php';
        if (!is_file($filePath)) {
            throw new \Exception('Upgrade script not found.', 404);
        }

        $definition = @include $filePath;
        if (!is_array($definition)) {
            throw new \Exception('Upgrade script is invalid.', 500);
        }

        $form = [
            'id' => $id,
            'description' => $definition['description'] ?? '',
            'code' => file_get_contents($filePath),
        ];

        if (strtoupper($this->runData['request']->method) === 'POST' && isset($this->runData['request']->post['update_upgrade'])) {
            $form['description'] = $this->runData['request']->post['description'] ?? '';
            $form['code'] = $this->runData['request']->post['code'] ?? '';

            $errors = $this->validateUpgradeForm($form, true);
            if (empty($errors)) {
                $form['code'] = $this->syncDescriptionInCode($form['code'], $form['description']);
                file_put_contents($filePath, $form['code']);
                chmod($filePath, 0664);

                $this->runData['request']->setAlert('Upgrade script updated successfully.', 'success');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/upgrade/view');
                exit;
            } else {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = implode('<br>', $errors);
            }
        }

        $this->runData['route']['h1'] = 'Edit Upgrade Script';
        $this->runData['route']['meta_title'] = 'Edit Upgrade Script';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/upgrade/view';
        $this->runData['data']['form'] = $form;

        return $this->runData;
    }

    private function executeUpgrades(): array {
        $logger = $this->runData['logger'] ?? new Logger($this->runData['config']['dir']['log']);
        $errorHandler = $this->runData['errorHandler'] ?? new ErrorHandler($logger);
        $controller = new SysUpgradeController(
            $this->runData['config'],
            $this->runData['db'],
            $logger,
            $errorHandler
        );

        ob_start();
        $exception = null;
        try {
            $controller->handleCli([]);
        } catch (\Throwable $e) {
            $exception = $e;
        }
        $buffer = trim(ob_get_clean());
        $output = $buffer === '' ? [] : preg_split("/\r\n|\n|\r/", $buffer);
        if ($exception) {
            $output[] = 'Error: ' . $exception->getMessage();
        }

        $executedAt = new DateTimeImmutable('now');
        $logFile = $this->getLogFile($executedAt);

        $result = [
            'success' => $exception === null,
            'message' => $exception === null ? 'Upgrade completed successfully.' : 'Upgrade failed. Review the output for details.',
            'output' => $output,
            'executed_at' => $executedAt->format(DateTimeImmutable::ATOM),
            'log_file' => $logFile,
        ];

        $this->appendLog($result, $logFile);

        return $result;
    }

    private function executeRollback(string $upgradeId): array {
        $logger = $this->runData['logger'] ?? new Logger($this->runData['config']['dir']['log']);
        $errorHandler = $this->runData['errorHandler'] ?? new ErrorHandler($logger);
        $controller = new SysUpgradeController(
            $this->runData['config'],
            $this->runData['db'],
            $logger,
            $errorHandler
        );

        $executedAt = new DateTimeImmutable('now');
        $logFile = $this->getLogFile($executedAt);

        try {
            $result = $controller->rollbackUpgrade($upgradeId);
            $message = $result['message'];
            $output = $result['output'] ?? [];
            $success = true;
        } catch (\Throwable $e) {
            $message = 'Rollback failed: ' . $e->getMessage();
            $output = [$message];
            $success = false;
        }

        $entry = [
            'success' => $success,
            'message' => $message,
            'output' => $output,
            'executed_at' => $executedAt->format(DateTimeImmutable::ATOM),
            'log_file' => $logFile,
        ];

        $this->appendLog($entry, $logFile);

        return $entry;
    }

    private function getLogTail(int $lines = 200): array {
        $logFile = $this->findLatestLogFile();
        if (!$logFile || !is_file($logFile)) {
            return [];
        }

        $contents = file($logFile, FILE_IGNORE_NEW_LINES);
        if ($contents === false) {
            return [];
        }

        $contents = array_reverse($contents);
        return array_slice(array_filter($contents, static function ($line) {
            return $line !== '';
        }), 0, $lines);
    }

    private function appendLog(array $result, string $logFile): void {
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $status = $result['success'] ? 'SUCCESS' : 'FAILURE';
        $logEntry = sprintf(
            "[%s] %s\n%s\n%s\n",
            $result['executed_at'],
            $status,
            implode(PHP_EOL, $result['output'] ?? []),
            str_repeat('-', 80)
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    private function getLogFile(?DateTimeImmutable $date = null): string {
        $date = $date ?? new DateTimeImmutable('now');
        $logDir = sprintf(
            '%s/%s/%s/%s',
            $this->runData['config']['dir']['log'],
            $date->format('Y'),
            $date->format('m'),
            $date->format('d')
        );

        return $logDir . '/upgrade.log';
    }

    private function findLatestLogFile(): ?string {
        $logRoot = $this->runData['config']['dir']['log'];
        if (!is_dir($logRoot)) {
            return null;
        }

        $years = glob($logRoot . '/*', GLOB_ONLYDIR);
        rsort($years);

        foreach ($years as $yearDir) {
            $months = glob($yearDir . '/*', GLOB_ONLYDIR);
            rsort($months);
            foreach ($months as $monthDir) {
                $days = glob($monthDir . '/*', GLOB_ONLYDIR);
                rsort($days);
                foreach ($days as $dayDir) {
                    $file = $dayDir . '/upgrade.log';
                    if (is_file($file)) {
                        return $file;
                    }
                }
            }
        }

        return null;
    }

    private function getUpgradeStatus(): array {
        $upgrades = $this->loadUpgrades();
        $checkpoints = $this->loadCheckpoints();
        $historyMap = [];
        foreach ($checkpoints['history'] as $entry) {
            $historyMap[$entry['id']] = $entry;
        }

        foreach ($upgrades as &$upgrade) {
            $upgrade['applied'] = in_array($upgrade['id'], $checkpoints['applied'], true);
            $upgrade['executed_at'] = isset($historyMap[$upgrade['id']]) ? $historyMap[$upgrade['id']]['executed_at'] : null;
            $upgrade['locked'] = in_array($upgrade['id'], $checkpoints['locked'], true);
        }

        return $upgrades;
    }

    private function loadUpgrades(): array {
        if (!is_dir($this->upgradeDir)) {
            return [];
        }

        $files = glob($this->upgradeDir . '/*.php');
        rsort($files);

        $upgrades = [];
        foreach ($files as $file) {
            $definition = @include $file;
            if (!is_array($definition) || empty($definition['id'])) {
                continue;
            }
            $upgrades[] = [
                'id' => $definition['id'],
                'description' => $definition['description'] ?? 'No description',
                'file' => $file,
                'has_rollback' => isset($definition['rollback']) && is_callable($definition['rollback']),
            ];
        }

        return $upgrades;
    }

    private function loadCheckpoints(): array {
        if (!is_file($this->checkpointFile)) {
            return [
                'applied' => [],
                'history' => [],
                'locked' => [],
            ];
        }

        $data = json_decode(file_get_contents($this->checkpointFile), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [
                'applied' => [],
                'history' => [],
                'locked' => [],
            ];
        }

        $data['applied'] = $data['applied'] ?? [];
        $data['history'] = $data['history'] ?? [];
        $data['locked'] = $data['locked'] ?? [];

        return $data;
    }

    private function saveCheckpoints(array $data): void {
        file_put_contents($this->checkpointFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function revertUpgrade(string $id): bool {
        $checkpoints = $this->loadCheckpoints();
        if (!in_array($id, $checkpoints['applied'], true)) {
            return false;
        }

        $checkpoints['applied'] = array_values(array_filter(
            $checkpoints['applied'],
            function ($appliedId) use ($id) {
                return $appliedId !== $id;
            }
        ));

        $checkpoints['locked'][] = $id;
        $checkpoints['locked'] = array_values(array_unique($checkpoints['locked']));

        $this->saveCheckpoints($checkpoints);

        return true;
    }

    private function validateUpgradeForm(array &$form, bool $isEdit = false): array {
        $errors = [];

        $form['id'] = strtolower(trim($form['id']));
        $form['id'] = preg_replace('/[^a-z0-9_\-]/', '', $form['id']);
        if ($form['id'] === '') {
            $errors[] = 'Upgrade ID is required and may only contain lowercase letters, numbers, hyphen and underscore.';
        } elseif (!$isEdit) {
            $filePath = $this->upgradeDir . '/' . $form['id'] . '.php';
            if (file_exists($filePath)) {
                $errors[] = 'An upgrade file with this ID already exists.';
            }
        } elseif ($isEdit) {
            $filePath = $this->upgradeDir . '/' . $form['id'] . '.php';
            if (!file_exists($filePath)) {
                $errors[] = 'Upgrade script could not be found.';
            }
        }

        if (trim($form['code']) === '') {
            $errors[] = 'Upgrade code cannot be empty.';
        }

        if (strpos($form['code'], "'id'") === false && strpos($form['code'], '"id"') === false) {
            $errors[] = 'Upgrade code must return an array with an "id" key.';
        }

        return $errors;
    }

    private function generateUpgradeId(): string {
        return strtolower(date('Ymd_His') . '_upgrade');
    }

    private function sanitizeUpgradeId(?string $id): string {
        $id = strtolower(trim($id ?? ''));
        return preg_replace('/[^a-z0-9_\-]/', '', $id);
    }

    private function buildDefaultTemplate(string $id, string $description): string {
        $escapedDescription = str_replace("'", "\\'", $description);
        return <<<PHP
<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '{$id}',
    'description' => '{$escapedDescription}',
    'run' => function (Database \$db, Logger \$logger, array \$config): void {
        // \$db->execute('UPDATE ...');
        \$logger->logError('Custom log entry from upgrade {$id}.');
    },
    'rollback' => function (Database \$db, Logger \$logger, array \$config): void {
        // Implement rollback logic to revert changes made in the run() block.
        \$logger->logError('Rollback invoked for upgrade {$id}.');
    },
];
PHP;
    }

    private function syncDescriptionInCode(string $code, string $description): string {
        $descriptionSingle = str_replace("'", "\\'", $description);
        $descriptionDouble = str_replace('"', '\\"', $description);

        $singlePattern = "/('description'\\s*=>\\s*)'([^']*)'/";
        if (preg_match($singlePattern, $code)) {
            return preg_replace($singlePattern, "$1'{$descriptionSingle}'", $code, 1);
        }

        $doublePattern = '/("description"\\s*=>\\s*)"([^"]*)"/';
        if (preg_match($doublePattern, $code)) {
            return preg_replace($doublePattern, "$1\"{$descriptionDouble}\"", $code, 1);
        }

        return $code;
    }
}
