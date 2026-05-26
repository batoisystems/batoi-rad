<?php
namespace Core\Sys;

use RuntimeException;

class ApiEndpointService {
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

    public function listActive(): array {
        $rows = $this->db->select('s_api_endpoint', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        return $this->hydrateRows($rows);
    }

    public function getBySlug(string $slug): array {
        if ($slug === '') {
            throw new RuntimeException('Endpoint slug is required.');
        }
        $rows = $this->db->select('s_api_endpoint', ['s_slug' => $slug, 'livestatus' => '1'], true);
        if (count($rows) !== 1) {
            throw new RuntimeException('API endpoint not found for slug: ' . $slug);
        }
        return $this->hydrateRows($rows)[0];
    }

    private function hydrateRows(array $rows): array {
        foreach ($rows as &$row) {
            $row['definition'] = $this->decodeJson($row['s_definition'] ?? null);
            $row['rate_limit'] = $this->decodeJson($row['s_rate_limit'] ?? null);
        }
        unset($row);
        return $rows;
    }

    private function decodeJson($value) {
        if (!$value) {
            return [];
        }
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        return $decoded;
    }
}
