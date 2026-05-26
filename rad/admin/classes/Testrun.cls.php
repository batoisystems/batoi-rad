<?php
namespace RadAdmin;

class Testrun {
    private $runData = [];
    private $db;
    private $errorHandler;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'] ?? null;
        $this->errorHandler = $runData['errorHandler'] ?? null;
    }

    private function requireAdmin() { return true; }

    public function viewone() {
        $id = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($id <= 0) throw new \Exception('Invalid run reference', 404);

        $this->runData['route']['h1'] = 'Test Run';
        $this->runData['route']['meta_title'] = 'Test Run';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Test Plans' => $this->runData['route']['rad_admin_url'] . '/testplan/view',
            'Run' => ''
        ];

        $run = null; $plan = null; $results = [];
        try {
            $runs = $this->db->select('s_test_run', ['id' => $id], true);
            $run = $runs[0] ?? null;
            if ($run) {
                $plans = $this->db->select('s_test_plan', ['id' => $run['s_test_plan_id']], true);
                $plan = $plans[0] ?? null;
                $results = $this->db->query(trim("
                    SELECT res.*, item.s_name AS item_name, item.s_type AS item_type
                    FROM s_test_result res
                    JOIN s_test_item item ON item.id = res.s_test_item_id
                    WHERE res.s_test_run_id = :rid
                    ORDER BY item.s_order ASC, res.id ASC
                "), [':rid' => $id]);
            }
        } catch (\Throwable $e) {
            if ($this->errorHandler) $this->errorHandler->handleException($e);
        }
        if (!$run) throw new \Exception('Test run not found', 404);

        $this->runData['data']['run'] = $run;
        $this->runData['data']['plan'] = $plan;
        $this->runData['data']['results'] = is_array($results) ? $results : [];
        return $this->runData;
    }

    public function updateresult() {
        $this->requireAdmin();
        $runId = (int)($this->runData['route']['pathparts'][3] ?? 0);
        $resultId = (int)($this->runData['request']->post['id'] ?? 0);
        if ($runId <= 0 || $resultId <= 0) throw new \Exception('Invalid test result reference', 404);
        $status = $this->runData['request']->post['s_status'] ?? 'not_run';
        $comment = $this->runData['request']->post['s_comment'] ?? '';
        $duration = $this->runData['request']->post['s_duration_ms'] ?? null;
        try {
            $safeStatus = addslashes($status);
            $safeComment = addslashes($comment);
            $safeDuration = $duration !== null && $duration !== '' ? (int)$duration : 'NULL';
            $sql = "UPDATE s_test_result SET s_status = '{$safeStatus}', s_comment = '{$safeComment}', s_duration_ms = {$safeDuration} WHERE id = {$resultId} AND s_test_run_id = {$runId}";
            $this->db->query($sql, []);
            $this->refreshRunStatus($runId);
        } catch (\Throwable $e) {
            if ($this->errorHandler) $this->errorHandler->handleException($e);
        }
        header("Location: ".$this->runData['route']['rad_admin_url']."/testrun/viewone/".$runId);
        exit;
    }

    private function refreshRunStatus(int $runId): void {
        try {
            $counts = $this->db->query(
                "SELECT s_status, COUNT(*) AS total FROM s_test_result WHERE s_test_run_id = {$runId} GROUP BY s_status",
                []
            );
            $map = [];
            foreach ($counts as $row) {
                $map[$row['s_status']] = (int)$row['total'];
            }
            $failed = $map['failed'] ?? 0;
            $blocked = $map['blocked'] ?? 0;
            $notRun = $map['not_run'] ?? 0;
            $passed = $map['passed'] ?? 0;
            $total = array_sum($map);
            $status = 'pending';
            if ($failed > 0) {
                $status = 'failed';
            } elseif ($blocked > 0) {
                $status = 'blocked';
            } elseif ($notRun > 0 && $total > $notRun) {
                $status = 'in_progress';
            } elseif ($notRun === 0 && $total > 0) {
                $status = 'passed';
            }

            $runRows = $this->db->select('s_test_run', ['id' => $runId], true);
            $run = $runRows[0] ?? [];
            $started = !empty($run['s_started_at']) ? $run['s_started_at'] : date('Y-m-d H:i:s');
            $completed = ($notRun === 0 && $total > 0) ? date('Y-m-d H:i:s') : ($run['s_completed_at'] ?? 'NULL');
            $startedSql = $started ? "'".addslashes($started)."'" : "NULL";
            $completedSql = ($notRun === 0 && $total > 0) ? "'".addslashes($completed)."'" : "NULL";
            $sql = "UPDATE s_test_run SET s_status = '".addslashes($status)."', s_started_at = {$startedSql}, s_completed_at = {$completedSql} WHERE id = {$runId}";
            $this->db->query($sql, []);
        } catch (\Throwable $e) {
            if ($this->errorHandler) $this->errorHandler->handleException($e);
        }
    }
}
