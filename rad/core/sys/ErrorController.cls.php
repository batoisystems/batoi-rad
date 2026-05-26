<?php
namespace Core\Sys;

class ErrorController {
    private $db;
    private $view;
    private $session;
    private $errorHandler;
    private $runData = [];
    private $routeIndex = [];
    public function __construct(array $runData, \Core\Sys\View $view, \Core\Sys\ErrorHandler $errorHandler) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->routeIndex = $runData['route']['pathparts'];
        $this->view = $view;
        $this->session = $runData['session'];
        $this->errorHandler = $errorHandler;
    }

    public function handle() {
        $errorCode = $this->routeIndex[1];
        // print '<pre>';print_r($this->route);print '</pre>';exit;
        // print 'Assets Controller.';die(' - here');
        // Initialize variables for the route code use
        switch($errorCode) {
            case 400: // Bad Request
                $alertMessage = 'The request could not be understood by the server due to malformed syntax.';
                $alertClassColor = 'danger';
                break;
            case 401: // Unauthorized
                $alertMessage = 'The request requires rightful user authentication. The current user session may not have the required permissions to access the requested resource.';
                $alertClassColor = 'danger';
                break;
            case 403: // Forbidden
                $alertMessage = 'The server understood the request, but is refusing to fulfill it. The current user session may not have the required permissions to access the requested resource.';
                $alertClassColor = 'danger';
                break;
            case 404: // Not Found
                $alertMessage = 'The server has not found anything matching the requested URI (Uniform Resource Identifier).';
                $alertClassColor = 'warning';
                break;
            case 405: // Method Not Allowed
                $alertMessage = 'The method specified in the request is not allowed for the resource identified by the request URI.';
                $alertClassColor = 'danger';
                break;
            case 408: // Request Timeout
                $alertMessage = 'The server timed out waiting for the request.';
                $alertClassColor = 'danger';
                break;
            case 429: // Too Many Requests
                $alertMessage = 'The user has sent too many requests in a given amount of time. Intended for use with rate-limiting schemes.';
                $alertClassColor = 'danger';
                break;
            case 500: // Internal Server Error
                $alertMessage = 'The server encountered an unexpected condition which prevented it from fulfilling the request.';
                $alertClassColor = 'danger';
                break;
            case 501: // Not Implemented
                $alertMessage = 'The server does not support the functionality required to fulfill the request.';
                $alertClassColor = 'warning';
                break;
            case 502: // Bad Gateway
                $alertMessage = 'The server, while acting as a gateway or proxy, received an invalid response from the upstream server it accessed in attempting to fulfill the request.';
                $alertClassColor = 'danger';
                break;
            case 503: // Service Unavailable
                $alertMessage = 'The server is currently unable to handle the request due to a temporary overloading or maintenance of the server.';
                $alertClassColor = 'danger';
                break;
            case 504: // Gateway Timeout
                $alertMessage = 'The server, while acting as a gateway or proxy, did not receive a timely response from the upstream server specified by the URI.';
                $alertClassColor = 'danger';
                break;
            default: // If the status code does not match any case, show the 500 error page.
                $alertMessage = 'The server encountered an unexpected condition which prevented it from fulfilling the request.';
                $alertClassColor = 'warning';
        }
        $this->runData['route']['path_full'] = $this->routeIndex[0].'/'.$this->routeIndex[1];
        $this->runData['route']['alert'] = $alertClassColor;
        $this->runData['route']['alert_message'] = '<strong>Error '.$errorCode.'</strong> '.$alertMessage;
        $this->runData['route']['h1'] = 'Error '.$errorCode;
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['meta_description'] = '';
        $this->runData['ms']['tpl_name'] = 'error-page';
        // print '<pre>';print_r($this->runData);print '</pre>';die('Before View');
        // call View
        $this->view->render($this->runData);
        // print '<br>Generic Controller.';
    }
}
