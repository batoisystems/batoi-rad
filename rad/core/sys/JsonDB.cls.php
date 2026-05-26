<?php
namespace Core\Sys;
/**
 * A simple JSON-based database in PHP.
 * 
 * This class represents a database that stores its data in JSON format.
 * The class provides basic create, read, update, and delete (CRUD) operations.
 * All data is automatically encrypted and decrypted using the OpenSSL extension.
 */
class JsonDB {
    /**
     * @var array The data of the database.
     */
    private $data;
    /**
     * @var string File Name
     */
    private $filename;
    /**
     * @var string the switch whether to encrypt or not
     */
    private $isEncrypted;
    /**
     * @var string The key used for encryption and decryption.
     */
    private $encryptionKey;
    /**
     * @var string The encryption method used by OpenSSL.
     */
    private $encryptionMethod;
    /**
     * Creates a new database instance.
     * 
     * @param string $encryptionKey The key used for encryption and decryption.
     */
    public function __construct($filename, $encrypt = [false, '', '']) {
        $this->filename = $filename;
        $this->isEncrypted = $encrypt[0];
        $this->encryptionKey = $encrypt[1];
        $this->encryptionMethod = $encrypt[2] ?? 'AES-256-CBC';
        $this->data = [];
    }

    /**
     * Loads the data from a file.
     * 
     * @param string $filename The name of the file.
     */
    public function loadFromFile($filename) {
        $contents = file_get_contents($filename);
        if (!$this->isEncrypted) {
            $decrypted = openssl_decrypt($contents, $this->encryptionMethod, $this->encryptionKey);
            $this->data = json_decode($decrypted, true);
        }
        else {
            $this->data = json_decode($contents, true);
        }
        
    }

    /**
     * Saves the data to a file.
     * 
     * @param string $filename The name of the file.
     */
    public function saveToFile($filename) {
        $json = json_encode($this->data);
        if (!$this->isEncrypted) {
            $encrypted = openssl_encrypt($json, $this->encryptionMethod, $this->encryptionKey);
            file_put_contents($filename, $encrypted);
        }
        else {
            file_put_contents($filename, $json);
        }
    }

    /**
     * Puts a value in the database.
     * 
     * @param string $key The key associated with the value.
     * @param mixed $value The value.
     */
    public function put($key, $value) {
        $this->data[$key] = $value;
    }

    /**
     * Appends a value to an array in the database.
     * 
     * @param string $key The key associated with the array.
     * @param mixed $value The value to append.
     */
    public function append($key, $value) {
        if (!isset($this->data[$key])) {
            $this->data[$key] = [];
        }

        $this->data[$key][] = $value;
    }

    /**
     * Deletes a value from the database.
     * 
     * @param string $key The key associated with the value.
     */
    public function delete($key) {
        unset($this->data[$key]);
    }

    /**
     * Gets a value from the database.
     * 
     * @param string $key The key associated with the value.
     * 
     * @return mixed The value, or null if it does not exist.
     */
    public function get($key) {
        return $this->data[$key] ?? null;
    }
}
