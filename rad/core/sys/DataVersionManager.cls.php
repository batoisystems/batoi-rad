<?php
namespace Core\Sys;

use PDOException;

class DataVersionManager {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function listVersions($table, $recordId) {
        try {
            $where = ['db_table_id' => $table, 'data_record_id' => $recordId];
            $versions = $this->db->select('s_version_history', $where, true);
            return $versions;
        } catch (PDOException $e) {
            throw new Exception("Unable to list versions: " . $e->getMessage());
        }
    }

    public function deleteVersion($versionId) {
        try {
            $where = ['id' => $versionId];
            $this->db->delete('s_version_history', $where);
        } catch (PDOException $e) {
            throw new Exception("Unable to delete version: " . $e->getMessage());
        }
    }

    public function restoreVersion($versionId) {
        try {
            $where = ['id' => $versionId];
            $version = $this->db->select('s_version_history', $where, true);
            if($version) {
                $data = unserialize(gzuncompress(base64_decode($version['data_record_dump'])));
                if ($this->db->exists($version['db_table_id'], ['id' => $version['data_record_id']])) {
                    $this->db->update($version['db_table_id'], $data, ['id' => $version['data_record_id']]);
                } else {
                    $this->db->insert($version['db_table_id'], $data);
                }
            }
        } catch (PDOException $e) {
            throw new Exception("Unable to restore version: " . $e->getMessage());
        }
    }
    
    public function existsVersion($versionId) {
        try {
            $where = ['id' => $versionId];
            return $this->db->exists('s_version_history', $where);
        } catch (PDOException $e) {
            throw new Exception("Unable to check version: " . $e->getMessage());
        }
    }
}
