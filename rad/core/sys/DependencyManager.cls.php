<?php
namespace Core\Sys;

class DependencyManager {
    private $dependencies;

    public function __construct() {
        $this->dependencies = require __DIR__ . '/../dependencies.php';
    }

    public function get($name) {
        if (!isset($this->dependencies[$name])) {
            throw new \Exception("Unknown dependency: $name");
        }

        $class = $this->dependencies[$name];
        return new $class;
    }
}
