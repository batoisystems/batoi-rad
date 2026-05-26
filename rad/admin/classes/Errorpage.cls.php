<?php
namespace RadAdmin;

class Errorpage {
    private $runData = [];

    public function __construct(array $runData) {
        $this->runData = $runData;
    }

    /**
     * Dedicated IP restriction page. Use when client IP is not in the trusted network.
     */
    public function ip_restricted() {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->runData['route']['h1'] = 'Access Restricted';
        $this->runData['route']['meta_title'] = 'Access Restricted';
        $this->runData['route']['subheading'] = 'You are accessing from outside the trusted network.';
        $this->runData['route']['breadcrumb'] = ['Home' => $this->runData['route']['rad_admin_url'] . '/home/view', 'Access Restricted' => ''];
        $this->runData['route']['error_status'] = 'ip_restricted';
        $this->runData['route']['client_ip'] = $clientIp;
        $this->runData['route']['pagepart'] = 'errorpage-ip-restricted';
        // Use a minimal template to avoid loading the full RAD Admin chrome when blocked.
        $this->runData['ms']['tpl_name'] = 'error-page';
        http_response_code(403);
        return $this->runData;
    }
}
