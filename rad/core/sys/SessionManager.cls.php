<?php
namespace Core\Sys;

class SessionManager {
    private $db;
    private $sessionParams;

    public function __construct(\Core\Sys\Database $db, $sessionParams) {
        $this->db = $db;
        $this->sessionParams = $sessionParams;
        // $this->start();
    }

    public function start() {
        if(session_status() == PHP_SESSION_NONE) {
            // $this->config['sys']['session_path'] = $this->config['dir']['session'];
            // Set session path
            // session_save_path($this->config['sys']['session_path']);
            session_save_path();

            // set secure cookie parameters
            $cookieParams = [
                'lifetime' => $this->sessionParams['lifetime'] * 60, // convert minutes to seconds
                'path' => '/',
                'domain' => $this->sessionParams['domain'],
                'secure' => $this->sessionParams['secure'], 
                'httponly' => $this->sessionParams['httponly'], 
                // Allow the session on top-level navigations such as email links.
                'samesite' => 'Lax',
            ];            

            session_set_cookie_params($cookieParams);
            session_name($this->sessionParams['name']);
            session_start();
            // If user's session is expired or not valid, destroy the session
            if (!$this->isSessionValid()) {
                // print '<pre>';print_r('Session is not valid --');print '</pre>';exit;
                // $this->destroy();
            }
        }
    }

    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public function get($key) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    public function delete($key) {
        if(isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public function destroy() {
        if(session_status() === PHP_SESSION_ACTIVE){
            session_destroy();
            setcookie(session_name(), '', time() - 42000); // remove session cookie
        }
    }    
    
    private function isSessionValid() {
        // check if session is valid, e.g. by checking `s_user_session` table in the database
        $sessionId = session_id();
        // print '<pre>';print_r($sessionId);print '</pre>';exit;
        $userSession = $this->db->select('s_entity_session', ['s_session_key' => $sessionId, 'livestatus' => '1'], false);

        // print '<pre>';print $sessionId;print_r($userSession);print '</pre>';exit;
        // if there is no such session or it's expired, return false
        if (!$userSession || $this->isSessionExpired()) {
            // print '<pre>';print $sessionId;print_r($userSession);print '</pre>';exit;
            return false;
        }

        // print '<pre>';print $sessionId;print_r($userSession);print '</pre>';exit;
        
        // check if user is idle for too long
        if ($this->isSessionIdle()) {
            return false;
        }

        // admin forced relogin to be implemented
        // by deactivating all user_session table records
        if ($this->isAdminForcedRelogin()) {
            return false;
        }

        return true;
    }

    private function isSessionIdle() {
        $lastActivity = $this->get('last_activity');
        // print '<pre>';print_r($lastActivity);print '</pre>';exit;
        if (!$lastActivity) {
            $this->set('last_activity', time());
            return false;
        }

        $idleTimeout = $this->sessionParams['idle_timeout'] * 60; // convert minutes to seconds
        return time() - $lastActivity > $idleTimeout;
    }

    private function isAdminForcedRelogin() {
        // check a flag in the database or elsewhere that indicates if the admin forced relogin
        // you will need to implement this check yourself based on your application logic
    }

    private function isSessionExpired() {
        // Get create time from session
        $createTime = $this->get('create_time');
        if (!$createTime) {
            // If create time is not set, it's a new session, set it and consider it not expired
            $this->set('create_time', time());
            return false;
        }

        $sessionLifetime = $this->sessionParams['lifetime'] * 60; // convert minutes to seconds
        // $diff = time() - $createTime;
        // print '<pre>';print_r($diff);print '<br/>';print_r($sessionLifetime);print '</pre>';exit;
        return time() - $createTime > $sessionLifetime;
    }

    public function isSessionPresent() {
        return isset($_SESSION['entity_id']);
    }
}
