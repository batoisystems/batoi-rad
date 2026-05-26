<?php
namespace Core\Sys;

class ReportWriter {
    private $db;

    public function __construct(\Core\Sys\Database $db) {
        $this->db = $db;
    }

    /**
     * Fetches data for a report
     *
     * @param string $table The table to fetch data from
     * @param array $where The WHERE clause for the SQL query
     * @param array $order The ORDER BY clause for the SQL query
     * @return array The data for the report
     */
    public function fetchData($table, $where = [], $order = []) {
        return $this->db->select($table, $where, true, $order);
    }
    
}
