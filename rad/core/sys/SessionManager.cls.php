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
            $sessionDir = (string)($this->sessionParams['dir'] ?? '');
            if ($sessionDir !== '') {
                if (!is_dir($sessionDir) && !mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) {
                    throw new \RuntimeException('Unable to create the RAD session directory.');
                }
                session_save_path($sessionDir);
            }

            // set secure cookie parameters
            $lifetimeSeconds = max(60, (int)($this->sessionParams['lifetime'] ?? 1800));
            $cookieParams = [
                'lifetime' => $lifetimeSeconds,
                'path' => '/',
                'domain' => $this->sessionParams['domain'],
                'secure' => $this->sessionParams['secure'], 
                'httponly' => $this->sessionParams['httponly'], 
                // Allow the session on top-level navigations such as email links.
                'samesite' => 'Lax',
            ];            

            session_set_cookie_params($cookieParams);
            session_name($this->sessionParams['name']);
            if (!session_start()) {
                throw new \RuntimeException('Unable to start the RAD session.');
            }
            // Anonymous sessions are valid for CSRF and login flows. Authenticated
            // sessions must have a live server-side record and valid timestamps.
            if ($this->isSessionPresent() && !$this->isSessionValid()) {
                $this->destroy();
                if (!session_start()) {
                    throw new \RuntimeException('Unable to reset the expired RAD session.');
                }
            }
        }
    }

    public function rotateId(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Cannot rotate an inactive session.');
        }
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Unable to rotate the session identifier.');
        }
        // Authentication changes also rotate the CSRF secret.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return session_id();
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
            $recordId = (int)($_SESSION['session_id'] ?? 0);
            if ($recordId > 0) {
                try {
                    $this->db->query(
                        "UPDATE s_entity_session SET livestatus = '0', updatestamp = NOW() WHERE id = :id",
                        [':id' => $recordId]
                    );
                } catch (\Throwable) {
                    // Session invalidation must still complete if persistence is unavailable.
                }
            }
            $_SESSION = [];
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
            session_destroy();
            session_id('');
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

        $idleTimeout = (int)($this->sessionParams['idle_timeout'] ?? 0);
        if ($idleTimeout <= 0) {
            $idleTimeout = max(60, (int)($this->sessionParams['lifetime'] ?? 1800));
        }
        if (time() - $lastActivity > $idleTimeout) {
            return true;
        }
        $this->set('last_activity', time());
        return false;
    }

    private function isAdminForcedRelogin() {
        // Revocation is represented by setting the matching s_entity_session
        // record inactive. isSessionValid() only loads active records.
        return false;
    }

    private function isSessionExpired() {
        // Get create time from session
        $createTime = $this->get('create_time');
        if (!$createTime) {
            // If create time is not set, it's a new session, set it and consider it not expired
            $this->set('create_time', time());
            return false;
        }

        $sessionLifetime = max(60, (int)($this->sessionParams['lifetime'] ?? 1800));
        // $diff = time() - $createTime;
        // print '<pre>';print_r($diff);print '<br/>';print_r($sessionLifetime);print '</pre>';exit;
        return time() - $createTime > $sessionLifetime;
    }

    public function isSessionPresent() {
        return isset($_SESSION['entity_id']);
    }
}
