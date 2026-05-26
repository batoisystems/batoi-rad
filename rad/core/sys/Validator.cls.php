<?php
namespace Core\Sys;

class Validator {
    private $data;
    private $errors = [];
    private $rules;

    public function __construct(array $data, array $rules) {
        $this->data = $data;
        $this->rules = $rules;
        $this->applyRules();
    }

    /**
     * Usage of class calling
        $data = $_REQUEST;
        $rules = [
            'email' => ['required', 'email'],
            'age' => [['numeric', 'Must be a number'], ['length', 1, 3]],
            'password' => [['length', 8, 20]],
            'date_of_birth' => [['date', 'Y-m-d']],
            'website' => ['url'],
            'ip_address' => ['ip'],
        ];

        $validator = new \Core\Validator($sanitizedData, $rules);

        if ($validator->fails()) {
            print_r($validator->getErrors());
        } else {
            print_r($validator->getValidatedData());
        }
     */

     private function applyRules() {
        foreach ($this->rules as $field => $rules) {
            foreach ($rules as $rule) {
                if (is_string($rule)) {
                    $this->$rule($field);
                } elseif (is_array($rule)) {
                    $method = array_shift($rule);
                    $args = array_merge([$field], $rule);
                    call_user_func_array([$this, $method], $args);
                }
            }
        }
    }    

    public function required($field, $message = 'This field is required') {
        if (!isset($this->data[$field]) || empty($this->data[$field])) {
            $this->errors[$field][] = $message;
        }
    }

    public function email($field, $message = 'Invalid email format') {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message;
        }
    }

    public function numeric($field, $message = 'Must be a number') {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = $message;
        }
    }

    public function length($field, $min, $max, $message = null) {
        if (isset($this->data[$field])) {
            $length = strlen($this->data[$field]);
            if ($length < $min || $length > $max) {
                $this->errors[$field][] = $message ?: "Input must be between $min and $max characters";
            }
        }
    }

    public function url($field, $message = 'Invalid URL') {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = $message;
        }
    }

    public function ip($field, $message = 'Invalid IP address') {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_IP)) {
            $this->errors[$field][] = $message;
        }
    }

    public function date($field, $format = 'Y-m-d', $message = 'Invalid date') {
        if (isset($this->data[$field])) {
            $d = \DateTime::createFromFormat($format, $this->data[$field]);
            if (!($d && $d->format($format) === $this->data[$field])) {
                $this->errors[$field][] = $message;
            }
        }
    }

    public function getErrors() {
        return $this->errors;
    }

    public function passes() {
        return empty($this->errors);
    }

    public function fails() {
        return !empty($this->errors);
    }

    public function getValidatedData() {
        return $this->data;
    }

    // Add more validation methods as needed...
}
