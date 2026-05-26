<?php
namespace Core\Sys;

class StandardService {
    
    // A place to store meta-information
    protected $metadata = [];

    public function __construct() {
        $this->gatherMetadata();
    }

    // Gather reflection data or documentation
    protected function gatherMetadata() {
        $reflection = new \ReflectionClass($this);

        // For example, get public methods of this class
        $this->metadata['methods'] = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $this->metadata['methods'][] = $method->getName();
        }

        // Similarly, you can gather more information like properties, doc comments, etc.
    }

    // Getter for the metadata
    public function getMetadata() {
        return $this->metadata;
    }

    // Intercept method calls
    public function __call($name, $arguments) {
        $this->beforeAction();

        // If the method exists, call it, otherwise throw an exception
        if (method_exists($this, $name)) {
            $result = call_user_func_array([$this, $name], $arguments);
        } else {
            throw new \Exception("Method $name does not exist in " . get_class($this));
        }

        $this->afterAction();

        return $result;
    }

    // Standard behaviors
    protected function beforeAction() {
        // Things to do before any action
    }

    protected function afterAction() {
        // Things to do after any action
    }
}

/*
Usage:
Any class that extends StandardService will have
the beforeAction and afterAction automatically called
before and after any public method respectively:

namespace App\[Mesh_name];
use Core\StandardService;

class ExampleServiceClass extends StandardService {
    public function doSomething($arg1, $arg2) {
        // Some logic
        return "Did something with $arg1 and $arg2";
    }
}
Now when you call $exampleService->doSomething('test1', 'test2'),
it will automatically call beforeAction and afterAction
before and after doSomething, respectively.

Remember, the __call approach will only work for methods 
that aren't explicitly defined in the object's class. 
If doSomething was directly called, __call would not be invoked. 
To ensure beforeAction and afterAction are always invoked, 
you would need to apply a more complex strategy, 
potentially involving the decorator pattern or proxies.
*/