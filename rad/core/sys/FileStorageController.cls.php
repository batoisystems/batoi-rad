<?php
namespace Core\Sys;

class FileStorageController {
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
        array_shift($this->routeIndex);
        $fileNameWithPath = implode('/', $this->routeIndex);
        $filePath = $this->runData['config']['dir']['data'].'/'.$fileNameWithPath;
    
        if (file_exists($filePath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE); 
            $mime_type = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            
            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
        } else {
            echo 'The file does not exist.';
        }
    }    
}
