<?php
namespace Core\Sys;

class UnitTesting {
    public function assertEqual($value1, $value2, $message) {
        if ($value1 != $value2) {
            throw new \Exception("Assertion Failed: $message");
        }
    }
    // Add more assertion methods as needed
}
