<?php
namespace Core\Sys;

use PDO;
use PDOException;

class Database {
    private $dbh;
    private $errorHandler;
    private $enableSqlLog;

    public function __construct($configDb, \Core\Sys\ErrorHandler $errorHandler) {
        // print '<pre>';print_r($configDb);print '</pre>';print $configDb['enable_sql_log'];
        $this->enableSqlLog = $configDb['enable_sql_log'] ?? 0;
        // print $this->enableSqlLog;die('ok');
        $this->errorHandler = $errorHandler;
        $dsn = 'mysql:host=' . $configDb['host'] . ';dbname=' . $configDb['name'];
        $options = array(
            PDO::ATTR_PERSISTENT => true, 
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        try {
            $this->dbh = new PDO($dsn, $configDb['user'], $configDb['password'], $options);
        } 
        catch(PDOException $e) {
            $this->errorHandler = $e->getMessage();
        }
    }

    /**
     * Logs the executed SQL query and its parameters
     *
     * @param string $query The executed SQL query
     * @param array $params The parameters used in the SQL query
     * @return void
     */
    private function logQuery($query, $params) {
        $log = [
            'query' => $query,
            'params' => $params,
        ];
        if ($this->enableSqlLog) {
            // print $this->enableSqlLog;die('ok');
            $this->errorHandler->logSql($log);
        }
    }

    function generateUuidV4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff), // time_low
            random_int(0, 0xffff),                         // time_mid
            random_int(0, 0x0fff) | 0x4000,                // time_hi_and_version
            random_int(0, 0x3fff) | 0x8000,                // clk_seq_hi_res | clk_seq_low
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)  // node
        );
    }

    public function select($table, $where = [], $allFields = false, $order = [], $limit = null, $join = null, $group = null) {
        // Get the schema of the table to determine the fields
        $fields = $this->getSchema($table);
        // if $allFields is not true, then remove the fields that do not start with a_ or s_
        if(!$allFields) {
            $fields = array_filter($fields, function($field) {
                return strpos($field, 'a_') === 0 || strpos($field, 's_') === 0;
            });
        }
        $sql = "SELECT " . implode(", ", $fields) . " FROM " . $table;

        if($join) {
            $sql .= " " . $this->joinClause($join);
        }

        if(!empty($where)) {
            $sql .= " WHERE " . $this->whereClause($where);
        }

        if($group) {
            $sql .= " GROUP BY " . $group;
        }

        if(!empty($order)) {
            $sql .= " ORDER BY " . $this->orderClause($order);
        }

        if($limit) {
            $sql .= " LIMIT " . $limit;
        }
    
        $stmt = $this->dbh->prepare($sql);
    
        foreach ($where as $field => &$value) {
            $stmt->bindParam(':' . $field, $value);
        }

        $this->logQuery($sql, $where);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($table, $data, $state_data = []) {
        // Ensure that the data array is not empty
        if (empty($data)) {
            throw new \InvalidArgumentException('The data array in an INSERT query cannot be empty.');
        }
        // Get the schema of the table
        $fields = $this->getSchema($table);
        // determine the values of default fields of the table - the fields without a_ or s_ prefix
        $defaultFields = array_filter($fields, function($field) {
            return strpos($field, 'a_') !== 0 && strpos($field, 's_') !== 0;
        });
        // print '<pre>';print_r($defaultFields);die('ok')
        array_shift($defaultFields);
        // Find the keys of $data array
        $dataKeys = array_keys($data);
        //merge the default fields with the data keys
        $fields = array_merge($defaultFields, $dataKeys);

        // build sql statement for insert
        $sql = "INSERT INTO " . $table . " (";
        $sql .= implode(", ", $fields);
        $sql .= ") VALUES (:";
        $sql .= implode(", :", $fields);
        $sql .= ")";
        // print $sql;die('ok');
        // use the state_data to get space_id, cretedby, wf_status, livestatus
        
        if (!empty($state_data)) {

            if(array_key_exists('space_id', $state_data)) {
                $space_id = $state_data['space_id'];
            }
            else {
                $space_id = 0;
            }
            if(array_key_exists('createdby', $state_data)) {
                $createdby = $state_data['createdby'];
            }
            else {
                $createdby = 1;
            }
            if(array_key_exists('wf_status', $state_data)) {
                $wf_status = $state_data['wf_status'];
            }
            else {
                $wf_status = 0;
            }
            if(array_key_exists('livestatus', $state_data)) {
                $liveStatus = $state_data['livestatus'];
            }
            else {
                $liveStatus = '1';
            }
        }
        else {
            $space_id = 0;
            $createdby = 1;
            $wf_status = 0;
            $liveStatus = '1';
        }

        // Prepare the SQL query
        $stmt = $this->dbh->prepare($sql);
        // Create bind value for all default fields
        $stmt->bindValue(':uid', $this->generateUuidV4());
        $stmt->bindValue(':livestatus', $liveStatus);
        $stmt->bindValue(':versioncode', 1);
        $stmt->bindValue(':wf_status', $wf_status);
        $stmt->bindValue(':space_id', $space_id);
        $stmt->bindValue(':createdby', $createdby);
        $stmt->bindValue(':createstamp', date('Y-m-d H:i:s'));
        $stmt->bindValue(':updatedby', $createdby);
        $stmt->bindValue(':updatestamp', date('Y-m-d H:i:s'));
        // Create bind value for all data fields
        foreach ($data as $field => &$value) {
            $stmt->bindParam(':' . $field, $value);
        }
        $this->logQuery($sql, $data);
        // Execute the prepared statement
        $stmt->execute();
        // Return the ID of the inserted row
        return $this->dbh->lastInsertId();
    }

    public function update($table, $data, $where, $state_data = []) {
        // Ensure that the data and where arrays are not empty
        if (empty($data)) {
            throw new \InvalidArgumentException('The data array in an UPDATE query cannot be empty.');
        }
    
        if (empty($where)) {
            throw new \InvalidArgumentException('The WHERE clause in an UPDATE query cannot be empty.');
        }
    
        // Begin the transaction
        $this->dbh->beginTransaction();
    
        try {
            // Get the schema of the table
            $fields = $this->getSchema($table);
            // Filter and prefix the input data according to the table's schema and prefix
    
            // Filter and prefix the where clause according to the table's schema and prefix
    
            // Fetch the existing record before it's updated
            $existingRecord = $this->select($table, $where, true);
            if (empty($existingRecord)) {
                throw new \Exception('Record not found for provided WHERE clause.');
            }
    
            // Save the current version of the data that is about to be updated
            $this->saveVersion($table, $existingRecord[0], $where);
    
            // Start constructing the SQL query
            $sql = "UPDATE " . $table . " SET ";
    
            // Add the fields and values to the SQL query
            $setParts = [];
            foreach ($data as $field => $value) {
                $setParts[] = "$field = :$field";
            }
            
            // Always increment versioncode by 1
            $setParts[] = "versioncode = versioncode + 1";

            // first get the updatedby value from the state_data
            if(array_key_exists('updatedby', $state_data)) {
                $updatedby = $state_data['updatedby'];
            }
            else {
                $updatedby = 1;
            }
            // then set the updatedby value
            $setParts[] = "updatedby = $updatedby";
            // then set the updatestamp value
            $setParts[] = "updatestamp = '" . date('Y-m-d H:i:s') . "'";
    
            $sql .= implode(", ", $setParts);
    
            // Add the WHERE clause to the SQL query
            $sql .= " WHERE ";
            $whereParts = [];
            foreach ($where as $field => $value) {
                $whereParts[] = "$field = :$field";
            }
    
            $sql .= implode(" AND ", $whereParts);
    
            // Prepare the SQL query
            $stmt = $this->dbh->prepare($sql);
    
            // Bind the parameters to the prepared statement
            foreach ($data as $field => &$value) {
                $stmt->bindParam(':' . $field, $value);
            }
            foreach ($where as $field => &$value) {
                $stmt->bindParam(':' . $field, $value);
            }
    
            $this->logQuery($sql, array_merge($data, $where));
            // Execute the prepared statement
            $stmt->execute();
    
            // Commit the transaction
            $this->dbh->commit();
    
            // Return the number of updated rows
            return $stmt->rowCount();
        } catch (Exception $e) {
            // An error occurred; rollback the transaction
            $this->dbh->rollback();
            throw $e; // Rethrow the exception for the caller to handle
        }
    }    

    public function delete($table, $where) {
        // Ensure that the where array is not empty to prevent deleting all rows
        if (empty($where)) {
            throw new \InvalidArgumentException('The WHERE clause in a DELETE query cannot be empty.');
        }
    
        // Start constructing the SQL query
        $sql = "DELETE FROM " . $table;
    
        // Add the WHERE clause to the SQL query
        $sql .= " WHERE ";
        $whereParts = [];
    
        foreach ($where as $field => $value) {
            $whereParts[] = "$field = :$field";
        }
    
        $sql .= implode(" AND ", $whereParts);
    
        // Prepare the SQL query
        $stmt = $this->dbh->prepare($sql);
    
        // Bind the parameters to the prepared statement
        foreach ($where as $field => &$value) {
            $stmt->bindParam(':' . $field, $value);
        }
    
        $this->logQuery($sql, $where);
        // Execute the prepared statement
        $stmt->execute();
    
        // Return the number of deleted rows
        return $stmt->rowCount();
    }

    // public function query($sql, $params = []) {
    //     // Prepare the SQL query
    //     $stmt = $this->dbh->prepare($sql);
    
    //     // Bind parameters to the SQL query if they exist
    //     if (!empty($params)) {
    //         foreach ($params as $param => &$value) {
    //             $stmt->bindParam($param, $value);
    //         }
    //     }
    
    //     $this->logQuery($sql, $params);
    //     // Execute the prepared statement
    //     $stmt->execute($params);
    //     return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // }
    public function query($sql, $params = []) {
        try {
            // Prepare the SQL query
            $stmt = $this->dbh->prepare($sql);
    
            // Bind parameters directly to the SQL query
            foreach ($params as $key => &$value) {
                $stmt->bindParam($key, $value);
            }
    
            // Log the query and parameters for debugging
            $this->logQuery($sql, $params);
    
            // Execute the prepared statement
            $stmt->execute();
    
            // Return results based on the type of SQL operation
            if (stripos($sql, 'select') === 0) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif (stripos($sql, 'insert') === 0) {
                return $this->dbh->lastInsertId();
            } elseif (stripos($sql, 'update') === 0 || stripos($sql, 'delete') === 0) {
                return $stmt->rowCount();
            } else {
                return true; // For other queries like CREATE, ALTER, etc.
            }
        } catch (\PDOException $e) {
            // Handle SQL errors
            throw new \Exception("Error executing SQL: " . $e->getMessage());
        }
    }              

    private function getSchema($table) {
        $stmt = $this->dbh->prepare("DESCRIBE $table");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function whereClause($where) {
        $whereClause = '';
        foreach ($where as $column => $value) {
            // Check if the column already has an operator or not
            if (strpos($column, ' ') === false) {
                // If no operator, assume equality
                $whereClause .= "$column = :$column AND ";
            } else {
                // If operator included, use it
                $whereClause .= "$column :$column AND ";
            }
        }
        return rtrim($whereClause, ' AND ');
    }
    

    private function joinClause($join) {
        $joinClause = '';
        foreach ($join as $table => $condition) {
            $joinClause .= "JOIN $table ON $condition ";
        }
        return $joinClause;
    }

    private function groupClause($group) {
        return $group;
    }

    private function limitClause($limit) {
        return $limit;
    }

    private function orderClause($order) {
        $orderClause = '';
        foreach ($order as $column => $direction) {
            $orderClause .= "$column $direction, ";
        }
        return rtrim($orderClause, ', ');
    }

    private function saveVersion($table, $data, $where) {
        // Ensure that the data and where arrays are not empty
        if (empty($data)) {
            throw new \InvalidArgumentException('The data array in a saveVersion query cannot be empty.');
        }
    
        if (empty($where)) {
            throw new \InvalidArgumentException('The WHERE clause in a saveVersion query cannot be empty.');
        }
    
        // Validate that 'id' key exists in data array
        if (!array_key_exists('id', $data)) {
            throw new \InvalidArgumentException('The data array in a saveVersion query must contain an "id" key.');
        }

        // Fetch the current state of the record to be updated
        $currentRecord = $this->select($table, $where, true);
        if (empty($currentRecord)) {
            throw new \Exception('Unable to fetch current record state for versioning.');
        }
    
        // Prepare data for insertion into version_history table
        // Serialize, compress, and encode data
        $dataDump = base64_encode(gzcompress(serialize($currentRecord)));

        $versionNumber = $currentRecord[0]['versioncode'];
        // If session user id exists, use it, otherwise use 0
        $modifiedBy = isset($_SESSION['entity_id']) ? $_SESSION['entity_id'] : 0;
    
        // Insert current record state into version_history table
        $sql = "INSERT INTO s_version_history (s_db_table, s_data_record_id, s_data_record_dump, s_version_number, s_modified_by) VALUES (?, ?, ?, ?, ?)";
        // print $sql;print_r([$table, $data['id'], $dataDump, $versionNumber, $modifiedBy]);exit;
        try {
            $this->dbh->prepare($sql)->execute([$table, $data['id'], $dataDump, $versionNumber, $modifiedBy]);
        } catch (PDOException $e) {
            throw new \Exception('Failed to save version: ' . $e->getMessage());
        }
    }

    public function archive($table, $where) {
        return $this->updateLivestatus($table, $where, '2');
    }
    
    public function activate($table, $where) {
        return $this->updateLivestatus($table, $where, '1');
    }
    
    public function suspend($table, $where) {
        return $this->updateLivestatus($table, $where, '3');
    }

    /**
     * getTables - Get all tables in the database
     */
    public function getTables($tableType = 'all') {
        if ($tableType == 'all') {
            $sql = "SHOW TABLES";
        } else {
            $sql = "SHOW TABLES LIKE '{$tableType}_%'";
        }
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function updateLivestatus($table, $where, $status) {
        // Ensure that the where array is not empty to prevent updating all rows
        if (empty($where)) {
            throw new \InvalidArgumentException('The WHERE clause in an UPDATE query cannot be empty.');
        }
    
        // Begin the transaction
        $this->dbh->beginTransaction();
    
        try {
            // Fetch the current state of the record(s) for versioning
            $selectParts = [];
            foreach ($where as $field => $value) {
                $selectParts[] = "$field = :$field";
            }
            $sqlSelect = "SELECT * FROM " . $table . " WHERE " . implode(" AND ", $selectParts);
            $stmtSelect = $this->dbh->prepare($sqlSelect);
            foreach ($where as $field => &$value) {
                $stmtSelect->bindParam(':' . $field, $value);
            }
            $stmtSelect->execute();
            $currentData = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);
    
            // Save the current version of each record that is about to be updated
            foreach ($currentData as $data) {
                $this->saveVersion($table, $data, $where);
            }
    
            // Start constructing the SQL query
            $sql = "UPDATE " . $table . " SET livestatus = :status";
    
            // Add the WHERE clause to the SQL query
            $sql .= " WHERE " . implode(" AND ", $selectParts);
    
            // Prepare the SQL query
            $stmt = $this->dbh->prepare($sql);
    
            // Bind the parameters to the prepared statement
            $stmt->bindParam(':status', $status);
            foreach ($where as $field => &$value) {
                $stmt->bindParam(':' . $field, $value);
            }
    
            // Execute the prepared statement
            $stmt->execute();
    
            // Commit the transaction
            $this->dbh->commit();
    
            // Return the number of updated rows
            return $stmt->rowCount();
        } catch (Exception $e) {
            // An error occurred; rollback the transaction
            $this->dbh->rollback();
            throw $e; // Rethrow the exception for the caller to handle
        }
    }        
    
}
