<?php
namespace RadAdmin;

class Testhookhelper {
    private $db;
    private $errorHandler;

    public function __construct($db, $errorHandler = null) {
        $this->db = $db;
        $this->errorHandler = $errorHandler;
    }

    public function fetchForMicroservice(int $msId): array {
        return $this->fetchPlans("s_scope='microservice' AND s_ms_id=:id", [':id' => $msId], 20);
    }

    public function fetchForRoute(int $routeId): array {
        return $this->fetchPlans("s_scope='route' AND s_route_id=:id", [':id' => $routeId], 20);
    }

    public function fetchForApi(int $apiId): array {
        return $this->fetchPlans("s_scope='api' AND s_apiendpoint_id=:id", [':id' => $apiId], 20);
    }

    private function fetchPlans(string $where, array $params, int $limit = 20): array {
        try {
            $sql = "SELECT * FROM s_test_plan WHERE {$where} AND livestatus <> '0' ORDER BY updatestamp DESC LIMIT {$limit}";
            $rows = $this->db->query($sql, $params);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
            return [];
        }
    }
}
