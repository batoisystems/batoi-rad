<?php
namespace RadAdmin;

/**
 * Dedicated RAD Admin login screen that reuses the shared /login/localsession flow.
 */
class Adminlogin {
    private array $runData = [];

    public function __construct(array $runData) {
        $this->runData = $runData;
    }

    public function view() {
        // If already logged in, bounce to admin home
        if (!empty($this->runData['entity']['id'])) {
            header('Location: ' . $this->runData['config']['sys']['base_url'] . '/rad-admin/home/view');
            exit;
        }
        $session = $this->runData['session'] ?? null;
        if ($session) {
            $flash = $session->get('login_flash') ?? null;
            if ($flash) {
                $session->delete('login_flash');
                $this->runData['route']['alert'] = $flash['type'] ?? 'danger';
                $this->runData['route']['alert_message'] = $flash['message'] ?? '';
            }
            $debugFlag = strtoupper((string)($this->runData['config']['sys']['dev_debug_flag']
                ?? $this->runData['config']['app']['dev_debug_flag']
                ?? 'N')) === 'Y';
            if ($debugFlag) {
                $debug = $session->get('login_debug') ?? null;
                if ($debug) {
                    $session->delete('login_debug');
                }
                $this->runData['route']['debug_enabled'] = true;
                $this->runData['route']['login_debug'] = $debug;
            }
        }
        $this->runData['route']['h1'] = 'RAD Admin Login';
        $this->runData['route']['meta_title'] = 'RAD Admin Login';
        $this->runData['route']['meta_description'] = 'Sign in to RAD Admin';
        $this->runData['route']['pagepart'] = 'adminlogin-view';
        return $this->runData;
    }
}
