<?php
namespace Core\Sys;
class Request {
    public $headers;
    public $body;
    public $method;
    public $timestamp;
    public $uri;
    public $ip;
    public $user_agent;
    public $referer;
    public $data;
    public $responseHeaders;
    public $responseBody;
    public $responseCode;
    public $responseMessage;
    public $responseError;
    public $responseException;
    public $responseExceptionTrace;
    public $responseExceptionFile;
    public $responseExceptionLine;
    public $responseExceptionCode;
    public $responseExceptionMessage;
    public $headerItem = [];
    public $post;
    public $get;
    public $csrf_token;

    public function __construct() {
        $this->headers = getallheaders();
        $this->body = file_get_contents('php://input');
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->timestamp = time();
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->ip = $_SERVER['REMOTE_ADDR'];
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $this->referer = $_SERVER['HTTP_REFERER'] ?? null;
        $this->data = $_REQUEST;
        $this->responseHeaders = [];
        $this->responseBody = null;
        $this->responseCode = null;
        $this->responseMessage = null;
        $this->responseError = null;
        $this->responseException = null;
        $this->responseExceptionTrace = null;
        $this->responseExceptionFile = null;
        $this->responseExceptionLine = null;
        $this->responseExceptionCode = null;
        $this->responseExceptionMessage = null;

        $this->headerItem['Cookie'] = $_SERVER['HTTP_COOKIE'] ?? null;
        $this->headerItem['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        // $this->headerItem['X-Forwarded-For'] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        // $this->headerItem['X-Forwarded-Host'] = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? null;
        // $this->headerItem['X-Forwarded-Proto'] = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
        // $this->headerItem['X-Forwarded-Port'] = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? null;
        // $this->headerItem['X-Forwarded-Server'] = $_SERVER['HTTP_X_FORWARDED_SERVER'] ?? null;
        $this->headerItem['X-Real-Ip'] = $_SERVER['HTTP_X_REAL_IP'] ?? null;
        $this->headerItem['X-Real-Port'] = $_SERVER['HTTP_X_REAL_PORT'] ?? null;
        $this->headerItem['X-Real-Proto'] = $_SERVER['HTTP_X_REAL_PROTO'] ?? null;
        $this->headerItem['X-Real-Server'] = $_SERVER['HTTP_X_REAL_SERVER'] ?? null;
        $this->headerItem['X-Real-Host'] = $_SERVER['HTTP_X_REAL_HOST'] ?? null;
        // $this->headerItem['X-Real-Forwarded-For'] = $_SERVER['HTTP_X_REAL_FORWARDED_FOR'] ?? null;
        // $this->headerItem['X-Real-Forwarded-Host'] = $_SERVER['HTTP_X_REAL_FORWARDED_HOST'] ?? null;
        // $this->headerItem['X-Real-Forwarded-Proto'] = $_SERVER['HTTP_X_REAL_FORWARDED_PROTO'] ?? null;
        // $this->headerItem['X-Real-Forwarded-Port'] = $_SERVER['HTTP_X_REAL_FORWARDED_PORT'] ?? null;
        // $this->headerItem['X-Real-Forwarded-Server'] = $_SERVER['HTTP_X_REAL_FORWARDED_SERVER'] ?? null;

        $this->post = isset($_POST) ? $this->sanitize($_POST) : [];
        $this->get = isset($_GET) ? $this->sanitize($_GET) : [];

        $this->generateCSRFToken();
        $this->csrf_token = $_SESSION['csrf_token'];
    }

    /* Save the alert for next request */
    public function setAlert($message, $type = 'info') {
        // Convert $message to a string if it's an array
        if (is_array($message)) {
            $message = json_encode($message);
        }

        // Write the route alert and alert_message to session
        $_SESSION['alert_from_request'] = true;
        $_SESSION['route_alert'] = $type;
        $_SESSION['route_alert_message'] = $message;
        // Write the route alert and alert_message to cookie
        // setcookie('route_alert', $type, time() + (86400 * 30), "/");
        // setcookie('route_alert_message', $message, time() + (86400 * 30), "/");
    }    

    /* Normalize $_POST and $_GET input values.
     * Keep raw text here; output escaping must happen at render time.
     */
    private function sanitize($data, $key = '') {
        // Skip sanitization for fields that start with s_content or a_content,
        // and for CSRF tokens which must remain unchanged.
        if ($key && (
            $key === 'csrf_token' ||
            strpos($key, 's_content') === 0 ||
            strpos($key, 'a_content') === 0
        )) {
            return $data;
        }
    
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitize($value, $key);
            }
        } else {
            $data = trim(stripslashes((string)$data));
        }
        return $data;
    }    

    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = base64_encode(openssl_random_pseudo_bytes(32));
        }
    }

    public function checkCSRFToken($token) {
        return $token === $this->csrf_token;
    }
    
}

/*
Usage:
// To generate and get the CSRF token:
$token = $request->csrf_token;

// To check the CSRF token:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($request->checkCSRFToken($_POST['csrf_token'])) {
        // valid token
    } else {
        // invalid token
    }
}
*/
