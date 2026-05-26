<?php
namespace Core\Sys;

class QueueController {
    private array $runData = [];
    private array $routeIndex = [];
    private Logger $logger;
    private Database $db;

    public function __construct(array $runData, \Core\Sys\View $view, \Core\Sys\ErrorHandler $errorHandler) {
        $this->runData = $runData;
        $this->routeIndex = $runData['route']['pathparts'] ?? [];
        $this->logger = $runData['logger'] ?? new Logger($runData['config']['dir']['log']);
        $this->db = $runData['db'];
    }

    public function handle() {
        array_shift($this->routeIndex);
        $action = $this->routeIndex[0] ?? 'run';
        if ($action === '' || $action === 'run') {
            $this->run();
            return;
        }
        header('Content-Type: application/json', true, 404);
        echo json_encode(['error' => 'Invalid queue action']);
        exit;
    }

    private function run(): void {
        $token = $this->runData['config']['sys']['queue_token'] ?? '';
        if ($token !== '') {
            $provided = $this->runData['request']->get['token'] ?? $this->runData['request']->post['token'] ?? '';
            if (!hash_equals($token, (string)$provided)) {
                header('Content-Type: application/json', true, 403);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
        }

        $job = $this->runData['request']->get['job'] ?? $this->runData['request']->post['job'] ?? null;
        $service = new QueueService($this->db, $this->logger, $this->runData['config']);
        $result = $service->runDueJobs($job ? (string)$job : null);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'jobs' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
