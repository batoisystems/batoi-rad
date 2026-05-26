<?php
namespace Core\Sys;
use DateTime;

class DataService {
    private $db;
    private $errorHandler;

    public function __construct(array $runData, $errorHandler) {
        $this->db = $runData['db'];
        $this->errorHandler = $errorHandler;
    }

    // Add new record
    public function add($tableName, array $data) {
        // Assuming your $db object has a method 'insert'
        if (!$this->db->insert($tableName, $data)) {
            $this->errorHandler->reportError("Failed to add record to {$tableName}");
        }
    }

    // Edit record
    public function edit($tableName, array $data, $where) {
        // Assuming your $db object has a method 'update'
        if (!$this->db->update($tableName, $data, $where)) {
            $this->errorHandler->reportError("Failed to edit record in {$tableName}");
        }
    }

    // Delete record
    public function delete($tableName, $where) {
        // Assuming your $db object has a method 'delete'
        if (!$this->db->delete($tableName, $where)) {
            $this->errorHandler->reportError("Failed to delete record from {$tableName}");
        }
    }

    // Display records in a grid
    public function displayGrid($tableName) {
        // Assuming your $db object has a method 'select'
        $data = $this->db->select($tableName);

        if (!$data) {
            $this->errorHandler->reportError("Failed to fetch data from {$tableName}");
            return;
        }

        echo "<table border='1'>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>{$cell}</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }

    // Search/Filter records
    public function search($tableName, $conditions) {
        // Assuming your $db object has a method 'search'
        return $this->db->search($tableName, $conditions);
    }
}

/*
Usage:
// Add record
$dataService->add('s_service', [
    's_name' => 'ExampleService',
    // ... other fields
]);

// Edit record
$dataService->edit('s_service', ['s_name' => 'ModifiedService'], "id = 1");

// Delete record
$dataService->delete('s_service', "id = 1");

// Display grid
$dataService->displayGrid('s_service');

// Search records
$results = $dataService->search('s_service', "s_name LIKE '%Sample%'");
*/
