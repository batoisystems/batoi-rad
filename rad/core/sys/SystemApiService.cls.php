<?php
namespace Core\Sys;

use RuntimeException;

class SystemApiService {
    private Database $db;
    private ErrorHandler $errorHandler;
    private array $config;
    private array $runData;

    public function __construct(Database $db, ErrorHandler $errorHandler, array $config = [], array $runData = []) {
        $this->db = $db;
        $this->errorHandler = $errorHandler;
        $this->config = $config;
        $this->runData = $runData;
    }

    public function dispatch(array $payload, array $apiAccount): array {
        $definition = $payload['system'] ?? [];
        if (empty($definition)) {
            throw new RuntimeException('Missing system payload block.');
        }

        $targetType = strtolower($definition['target_type'] ?? 'table');
        return $targetType === 'service'
            ? $this->handleServiceCall($definition, $apiAccount)
            : $this->handleTableOperation($definition, $apiAccount);
    }

    private function handleTableOperation(array $definition, array $apiAccount): array {
        $table = $this->normalizeSystemTableName($definition['target'] ?? '');
        if ($table === '') {
            throw new RuntimeException('System API table target is required.');
        }

        $allowedTables = $this->resolveAllowedTables($apiAccount);
        if (!empty($allowedTables) && !in_array($table, $allowedTables, true)) {
            throw new RuntimeException('Table access is not permitted for this API key.');
        }

        $action = strtolower($definition['action'] ?? 'select');
        $criteria = $definition['criteria'] ?? [];
        if ($criteria instanceof \stdClass) {
            $criteria = (array) $criteria;
        }
        if (!is_array($criteria)) {
            throw new RuntimeException('Criteria must be an object/array.');
        }

        $data = $definition['data'] ?? [];
        if ($data instanceof \stdClass) {
            $data = (array) $data;
        }
        if (!is_array($data)) {
            throw new RuntimeException('Data must be an object/array.');
        }

        switch ($action) {
            case 'select':
                $rows = $this->db->select($table, $criteria, true);
                return ['rows' => $rows];
            case 'insert':
                if (empty($data)) {
                    throw new RuntimeException('Insert operations require data.');
                }
                $state = [
                    'livestatus' => $definition['livestatus'] ?? '1',
                    'space_id' => (int)($definition['space_id'] ?? 0),
                    'createdby' => (int)($definition['createdby'] ?? ($apiAccount['id'] ?? 1)),
                ];
                $insertId = $this->db->insert($table, $data, $state);
                return ['insert_id' => $insertId];
            case 'update':
                if (empty($data) || empty($criteria)) {
                    throw new RuntimeException('Update operations require both data and criteria.');
                }
                $affected = $this->db->update($table, $data, $criteria);
                return ['updated' => $affected];
            case 'delete':
                if (empty($criteria)) {
                    throw new RuntimeException('Delete operations require criteria.');
                }
                $deleted = $this->db->delete($table, $criteria);
                return ['deleted' => $deleted];
            default:
                throw new RuntimeException('Unsupported table action: ' . $action);
        }
    }

    private function handleServiceCall(array $definition, array $apiAccount): array {
        $serviceKey = $definition['target'] ?? '';
        if ($serviceKey === '') {
            throw new RuntimeException('Service target is required.');
        }

        $serviceMeta = $this->resolveAllowedService($serviceKey, $apiAccount);
        if ($serviceMeta === null) {
            throw new RuntimeException('Service access is not permitted for this API key.');
        }

        $className = $serviceMeta['class'];
        $method = $serviceMeta['method'];
        if (!class_exists($className)) {
            throw new RuntimeException('Service class not found: ' . $className);
        }

        $constructor = strtolower($serviceMeta['constructor'] ?? 'db');
        switch ($constructor) {
            case 'run_data':
            case 'rundata':
                $service = new $className($this->runData);
                break;
            case 'none':
                $service = new $className();
                break;
            case 'error_handler':
            case 'errorhandler':
                $service = new $className($this->errorHandler);
                break;
            case 'db_errorhandler':
                $service = new $className($this->db, $this->errorHandler);
                break;
            case 'db_rundata':
                $service = new $className($this->db, $this->runData);
                break;
            case 'db':
            default:
                $service = new $className($this->db);
                break;
        }
        if (!method_exists($service, $method)) {
            throw new RuntimeException('Method not available on service: ' . $method);
        }

        $arguments = $definition['arguments'] ?? [];
        if (!is_array($arguments)) {
            $arguments = [];
        }

        $result = call_user_func_array([$service, $method], $arguments);
        return ['result' => $result];
    }

    private function resolveAllowedTables(array $apiAccount): array {
        $accountTables = $apiAccount['auth_info']['system_tables'] ?? null;
        if (is_array($accountTables) && !empty($accountTables)) {
            return array_values(array_unique(array_map([$this, 'normalizeSystemTableName'], $accountTables)));
        }
        $configTables = $this->config['system_tables'] ?? [];
        return array_values(array_unique(array_map([$this, 'normalizeSystemTableName'], $configTables)));
    }

    private function resolveAllowedService(string $serviceKey, array $apiAccount): ?array {
        $configured = $this->config['system_services'] ?? [];
        $map = [];
        foreach ($configured as $service) {
            if (!empty($service['key'])) {
                $map[$service['key']] = $service;
            }
        }

        $allowedKeys = $apiAccount['auth_info']['system_services'] ?? null;
        if (is_array($allowedKeys) && !empty($allowedKeys)) {
            $map = array_intersect_key($map, array_flip($allowedKeys));
        }

        return $map[$serviceKey] ?? null;
    }

    private function normalizeSystemTableName(string $table): string {
        $table = trim($table);
        if ($table === '') {
            return '';
        }
        $lower = strtolower($table);
        if (strpos($lower, 's_') === 0 || strpos($lower, 'a_') === 0) {
            return $table;
        }
        return 's_' . ltrim($lower, '_');
    }
}
