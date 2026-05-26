<?php
namespace Core\Sys;

class ErrorHandler {
    private $logger;
    private $lastError;
    private $view;
    private $contextRunData;
    private $responseMode = 'html';

    public function __construct(Logger $logger) {
        $this->logger = $logger;
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }

    public function attachContext(array &$runData, \Core\Sys\View $view): void {
        $this->contextRunData = &$runData;
        $this->view = $view;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function handleError($errno, $errstr, $errfile, $errline) {
        $this->logger->logError("ERROR $errno in $errfile($errline): $errstr");
        $this->lastError = "An error occurred. Please try again later.";
    }

    public function reportError($message) {
        $this->logger->logError($message);
        $this->lastError = "An error occurred. Please try again later: {$message} ";
    }

    public function logError($message, array $extraContext = []): void {
        $this->logger->logError($message, $extraContext);
    }

    public function setResponseMode(string $mode): void {
        $mode = strtolower($mode);
        $this->responseMode = in_array($mode, ['json', 'html'], true) ? $mode : 'html';
    }

    public function handleException($exception) {
        $supportsThrowable = interface_exists('\Throwable', false);

        if (
            $exception instanceof \Exception ||
            ($supportsThrowable && $exception instanceof \Throwable)
        ) {
            $rawCode = $exception->getCode();
            $code = is_int($rawCode) ? $rawCode : 500;
            $msg = $exception->getMessage();
            $file = $exception->getFile();
            $line = $exception->getLine();
        } else if (is_string($exception)) {
            $code = 500;
            $msg = $exception;
            $file = 'Unknown File';
            $line = 'Unknown Line';
        } else {
            $code = 500;
            $msg = 'Unhandled error';
            $file = 'Unknown File';
            $line = 'Unknown Line';
        }
    
        $reference = substr(hash('sha1', $code . $msg . $file . $line . microtime(true)), 0, 10);

        $this->logger->logError(
            "EXCEPTION with code $code in $file ($line): $msg",
            ['reference' => $reference]
        );
        http_response_code($code);

        $this->lastError = "An error occurred. Please try again later.";

        if ($this->responseMode === 'json') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'message' => $this->lastError,
            ]);
            exit;
        }

        if ($this->view && is_array($this->contextRunData)) {
            try {
                $this->prepareContextualErrorData($code, $msg, [
                    'file' => $file,
                    'line' => $line,
                    'reference' => $reference,
                ]);
                $this->view->render($this->contextRunData);
                exit;
            } catch (\Throwable $e) {
                $this->logger->logError('Error rendering themed error page: ' . $e->getMessage());
            }
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        $this->renderInlineErrorPage($code, $msg);
    }

    public function handleSqlError($errno, $errstr, $errfile, $errline, $sql) {
        $this->logger->logSql($sql);  // log the SQL command
        $this->logger->logError("SQL ERROR $errno in $errfile($errline): $errstr");
        $this->lastError = "A database error occurred. Please try again later.";
    }

    public function logSql($log) {
        $this->logger->logSql($log);
    }

    private function prepareContextualErrorData(int $code, string $message, array $context = []): void {
        $alertClass = $this->mapAlertClass($code);
        $path = $_SERVER['REQUEST_URI'] ?? ($this->contextRunData['route']['path'] ?? '');
        $baseUrl = $this->contextRunData['config']['sys']['base_url'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $host = $_SERVER['HTTP_HOST'] ?? ($this->contextRunData['config']['sys']['base_host'] ?? '');
        $timestamp = time();
        $reference = $context['reference'] ?? substr(hash('sha1', $message . ($context['file'] ?? '') . ($context['line'] ?? '') . microtime(true)), 0, 10);
        $location = '';
        if (!empty($context['file'])) {
            $location = basename($context['file']) . (isset($context['line']) ? ':' . $context['line'] : '');
        }

        $routeRef =& $this->contextRunData['route'];
        if (!isset($routeRef) || !is_array($routeRef)) {
            $routeRef = [];
        }

        $routeRef['error_status'] = 'error';
        $routeRef['error_code'] = $code;
        $routeRef['error_message'] = $message;
        $routeRef['error_path'] = $path;
        $routeRef['error_tone'] = $alertClass;
        $routeRef['alert'] = $alertClass;
        $routeRef['alert_message'] = '<strong>Error ' . htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') . '</strong> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $routeRef['h1'] = 'Error ' . $code;
        $routeRef['meta_title'] = 'Error ' . $code;
        $routeRef['meta_description'] = '';
        $routeRef['home_url'] = $baseUrl;
        $routeRef['error_meta'] = [
            'method' => $method,
            'host' => $host,
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_id' => $_SESSION['entity_id'] ?? '',
            'user_name' => $_SESSION['fullname'] ?? '',
            'timestamp' => $timestamp,
            'reference' => $reference,
            'location' => $location,
        ];

        if (!isset($this->contextRunData['ms']) || !is_array($this->contextRunData['ms'])) {
            $this->contextRunData['ms'] = [];
        }
        $this->contextRunData['ms']['tpl_name'] = 'error-page';
    }

    private function mapAlertClass(int $code): string {
        if ($code >= 500) {
            return 'danger';
        }
        if ($code >= 400) {
            return $code === 404 ? 'warning' : 'danger';
        }
        return 'info';
    }

    private function renderInlineErrorPage(int $code, string $message): void {
        $safeCode = htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message ?: 'Unexpected error', ENT_QUOTES, 'UTF-8');
        $requestUri = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : '';
        $home = isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '';
        $backLabel = $home ? 'Back to Home' : 'Back';

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error {$safeCode}</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f8fafc;
        }
        .error-shell {
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 40px 120px rgba(7, 25, 71, 0.08);
            padding: 48px 42px;
            width: min(520px, calc(100% - 32px));
            text-align: center;
            position: relative;
            isolation: isolate;
        }
        .error-shell::before {
            content: '';
            position: absolute;
            inset: -40px;
            background: radial-gradient(circle, rgba(15,98,254,0.12) 0%, rgba(15,98,254,0) 60%);
            z-index: -1;
        }
        .error-shell h1 {
            font-size: 2.5rem;
            margin-bottom: 0.25rem;
            color: #0f1f3d;
        }
        .error-shell p {
            color: #546178;
            line-height: 1.6;
        }
        .error-shell code {
            display: block;
            margin: 1.25rem auto 0;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            background: #eef4ff;
            color: #1e2a44;
            font-size: 0.95rem;
            letter-spacing: 0.03em;
        }
        .error-shell a.btn-home {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1.5rem;
            padding: 0.65rem 1.25rem;
            border-radius: 999px;
            text-decoration: none;
            background: #0f62fe;
            color: #fff;
            font-weight: 600;
            border: none;
            box-shadow: 0 15px 30px rgba(15, 98, 254, 0.15);
        }
        .error-shell small {
            display: block;
            margin-top: 1.25rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #050914; }
            .error-shell { background: #0f1729; color: #e3e7f3; }
            .error-shell p { color: #a6b0c8; }
            .error-shell code { background: #1b2845; color: #eef2ff; }
            .error-shell a.btn-home { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="error-shell">
        <h1>{$safeCode}</h1>
        <p>{$safeMessage}</p>
HTML;
        if ($requestUri !== '') {
            echo "<code>{$requestUri}</code>";
        }
        if ($home !== '') {
            echo '<a class="btn-home" href="' . htmlspecialchars($home, ENT_QUOTES, 'UTF-8') . '">&larr; ' . $backLabel . '</a>';
        }
        echo '<small>© ' . date('Y') . ' ' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '', ENT_QUOTES, 'UTF-8') . '</small>';
        echo <<<HTML
    </div>
</body>
</html>
HTML;
    }

    private function recordTelemetryEvent(array $payload): void {
        if (!$this->telemetryService instanceof TelemetryService) {
            return;
        }
        try {
            $this->telemetryService->recordEvent($payload);
        } catch (\Throwable $e) {
            // ignore telemetry failures
        }
    }
}
