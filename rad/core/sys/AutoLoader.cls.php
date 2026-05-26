<?php
namespace Core\Sys;

class AutoLoader {
    protected $directories = [];

    public function register() {
        spl_autoload_register([$this, 'loadClass']);
        // Include Composer's autoload file for vendor libraries
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
    }

    public function addDirectory($directory) {
        $this->directories[] = $directory;
    }

    public function loadClass($className) {
        $className = ltrim($className, '\\');
        $fileName = '';
        $namespace = '';

        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }

        // Handle Core\Sys namespace separately
        if ($namespace === 'Core\Sys') {
            $fileName = $className . '.cls.php';
        } elseif ($namespace === 'Core\App') {
            $fileName = $className . '.cls.php';
        } else {
            // Use the normal handling for other namespaces
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.cls.php';
        }

        // Attempt to load classes from registered directories
        foreach ($this->directories as $directory) {
            $fullPath = $directory . DIRECTORY_SEPARATOR . $fileName;

            // Add logging to debug the file paths being checked
            // error_log("Autoloader: Looking for file at $fullPath");

            if (file_exists($fullPath)) {
                require $fullPath;
                return;
            }
        }

        // Optionally, include an error message or logging if the class is not found
        error_log("Autoloader: Class not found - $className");
    }
}
