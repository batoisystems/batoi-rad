<?php
namespace Core\Sys;

class FileIntegrityChecker {
    public function checkIntegrity($originalHash, $file) {
        $currentHash = md5_file($file);

        if ($originalHash !== $currentHash) {
            throw new \Exception('File integrity check failed.');
        }
    }
}
