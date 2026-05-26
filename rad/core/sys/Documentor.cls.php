<?php
namespace Core\Sys;

class Documentor {
    public function generateDocumentation($directory) {
        // Run phpDocumentor command for the given directory
    }

    public function getClassInfo($className) {
        $reflector = new \ReflectionClass($className);
        $methods = $reflector->getMethods();

        $info = [
            'name' => $reflector->getName(),
            'description' => $reflector->getDocComment(),
            'methods' => []
        ];

        foreach ($methods as $method) {
            $info['methods'][] = [
                'name' => $method->getName(),
                'description' => $method->getDocComment(),
                'parameters' => $method->getParameters(),
            ];
        }

        return $info;
    }
}


