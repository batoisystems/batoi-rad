<?php
namespace Core\Sys;

class HomeController {
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
        // config sys site_status = L, then print Live, else print Maintenance
        if ($this->runData['config']['sys']['site_status'] == 'L') {
            // if sys home_page is redirect, then redirect to the url in sys home_page_redirect_url
            if ($this->runData['config']['sys']['home_page'] == 'redirect') {
                $redirectUrl = $this->runData['config']['sys']['home_page_redirect_url'];
                header("Location: {$redirectUrl}");
                exit;
            } else if ($this->runData['config']['sys']['home_page'] == 'content') {
                $home_page_content = explode(',',$this->runData['config']['sys']['home_page_content']);
                $this->runData['ms']['tpl_name'] = $home_page_content[0];
                $this->runData['route']['content_id'][0] = $home_page_content[1];
                $this->runData['route']['content_tpl'] = 'N';
                // get content from s_content table for id = $this->runData['route']['content_id'][0]
                $contentRows = $this->db->select('s_content', ['id' => $this->runData['route']['content_id'][0]]);
                // print '<pre>';print_r($contentRows);print '</pre>';die('Reached Home Controller');
                $this->runData['route']['content'] = $contentRows[0]['s_content'];
                $this->runData['route']['content_title'] = $contentRows[0]['s_meta_title'];
                $this->runData['route']['meta_title'] = $this->runData['route']['content_title'];
                $this->runData['route']['meta_description'] = $contentRows[0]['s_meta_description'];
                // print '<pre>';print_r($this->runData);print '</pre>';die('Reached Home Controller');
                $this->view->render($this->runData);
            } else {
                $this->runData['ms']['tpl_name'] = 'home';
                $this->view->render($this->runData);
            }
        } else {
            $this->runData['ms']['tpl_name'] = 'maintenance';
            $this->view->render($this->runData);
        }
    }
}
