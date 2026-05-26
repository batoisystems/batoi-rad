<?php
namespace Core\Sys;

class ConfigLoader {
    private $config;
    private $siteRootPath;

    public function __construct($configPath) {
        // load the default configuration file
        $this->config = require $configPath;
        $this->siteRootPath = realpath(dirname(dirname(dirname($configPath))));
        $this->config['dir']['log'] = $this->siteRootPath . '/rad/log';
        $this->config['sys']['session_path'] = $this->siteRootPath . '/rad/session';
        
        // get the current environment
        $config_live_path = dirname($configPath) . '/sys.live.inc.php';
        $config_beta_path = dirname($configPath) . '/sys.beta.inc.php';
        $config_dev_path = dirname($configPath) . '/sys.dev.inc.php';
        $config_local_path = dirname($configPath) . '/sys.local.inc.php';
        $environment_externally = getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : '';

        $envConfigFile = '';
        if(file_exists($config_live_path)) $envConfigFile = $config_live_path;
        else if(file_exists($config_beta_path)) $envConfigFile = $config_beta_path;
        else if(file_exists($config_dev_path)) $envConfigFile = $config_dev_path;
        else if( ($environment_externally != '') && (file_exists( dirname($configPath) . '/sys.'.$environment_externally.'inc.php')) ) $envConfigFile = dirname($configPath) . "/sys.{$environment_externally}.inc.php";
        else $envConfigFile = '';

        // if there's an environment-specific configuration file, include it
        if ($envConfigFile != '') {
            $this->config = array_merge($this->config, require $envConfigFile);
        }
        $this->normalizeAuthConfig();
        $this->hydrateApiGatewayConfig();
        // print '<pre>';print_r($this->config);print '</pre>';exit;
    }

    public function get($key) {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                throw new \Exception("Configuration key '{$key}' not found.");
            }

            $value = $value[$key];
        }

        return $value;
    }

    /* The following function will output the set config parameters in an array */
    public function getInitAll() {
        return $this->config;
    }

    public function getAll($dbConfig) {
        $this->config = array_merge($this->config, $dbConfig);
        unset($this->config['database']);  // remove the 'database' key
        $this->getDirs();
        return $this->config;
    }

    private function getDirs() {
        // Setup directory paths
        $this->config['dir']['site'] = $this->siteRootPath;
        $this->config['dir']['rad'] = $this->config['dir']['site'] . '/rad';
        $this->config['dir']['www'] = $this->config['dir']['site'] . '/public_html';
        $this->config['dir']['core'] = $this->config['dir']['site'] . '/rad/core';
        $this->config['dir']['ms'] = $this->config['dir']['site'] . '/rad/ms';
        $this->config['dir']['theme'] = $this->config['dir']['site'] . '/rad/theme';
        $this->config['dir']['log'] = $this->config['dir']['rad'] . '/log';
        $this->config['dir']['session'] = $this->config['dir']['rad'] . '/log/session';
        $this->config['dir']['data'] = $this->config['dir']['rad'] . '/data/uploads';
        $this->config['dir']['data_uploads'] = $this->config['dir']['rad'] . '/data/uploads';
        $this->config['dir']['data_temp'] = $this->config['dir']['rad'] . '/data/temp';
        $this->config['dir']['admin'] = $this->config['dir']['rad'] . '/admin'; // ui, deploy, upgrade
        $this->config['dir']['vendor'] = $this->config['dir']['site'] . '/rad/vendor';
        $this->config['dir']['assets'] = $this->config['dir']['www'] . '/assets';
        $this->config['dir']['pub'] = $this->config['dir']['www'] . '/pub';
        // print '<pre>';print_r($this->config);print '</pre>';
    }

    public function setEnvConfig() {
        // Display errors if we're in a development environment
        if ($this->get('sys.display_errors')) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
            error_reporting(0);
        }

        // Set the timezone
        date_default_timezone_set($this->get('sys.timezone'));

        // Set the locale
        setlocale(LC_ALL, $this->get('sys.locale'));

        // Set the session name
        session_name($this->get('sys.session_name'));
        $this->config['sys']['session_path'] = $this->config['dir']['session'];
        // print '<pre>';print_r($this->config);print '</pre>';exit;

        // Set the session cookie parameters
        session_set_cookie_params(
            $this->get('sys.session_lifetime'),
            $this->get('dir.session'),
            $this->get('sys.session_domain'),
            $this->get('sys.session_secure'),
            $this->get('sys.session_httponly')
        );
    }

    public function fetchDbConfig($db) {
        $dbConfigRows = $db->select('s_config', ['livestatus' => '1'], false);
        $dbConfig = ['sys' => [], 'app' => []];
        
        // Organize the config data into separate 'sys' and 'app' arrays
        foreach ($dbConfigRows as $row) {
            if ($row['s_config_origin'] === 'S') {
                $dbConfig['sys'][$row['s_config_handle']] = $row['s_config_value'];
            } elseif ($row['s_config_origin'] === 'A') {
                $dbConfig['app'][$row['s_config_handle']] = $row['s_config_value'];
            }
        }

        return $dbConfig;
    }

    private function hydrateApiGatewayConfig(): void {
        $apiGateway = $this->config['sys']['api_gateway'] ?? [];
        $radAdminConfigPath = $this->siteRootPath . '/rad/admin/rad.config.php';
        if (file_exists($radAdminConfigPath)) {
            $radAdminConfig = include $radAdminConfigPath;
            if (isset($radAdminConfig['api_gateway']) && is_array($radAdminConfig['api_gateway'])) {
                $apiGateway = array_replace_recursive($apiGateway, $radAdminConfig['api_gateway']);
            }
        }
        $serviceManifestPath = $this->siteRootPath . '/rad/config/api-services.php';
        if (file_exists($serviceManifestPath)) {
            $manifest = include $serviceManifestPath;
            if (is_array($manifest)) {
                $apiGateway['system_services'] = array_values($manifest);
            }
        }
        $this->config['sys']['api_gateway'] = $apiGateway;
    }

    private function normalizeAuthConfig(): void {
        if (!isset($this->config['auth']) || !is_array($this->config['auth'])) {
            $this->config['auth'] = [];
        }
        $role = strtolower(trim((string)($this->config['auth']['sso_role'] ?? 'disabled')));
        if (!in_array($role, ['disabled', 'server', 'client'], true)) {
            $role = 'disabled';
        }
        $this->config['auth']['sso_role'] = $role;
        if (!isset($this->config['auth']['sso_server']) || !is_array($this->config['auth']['sso_server'])) {
            $this->config['auth']['sso_server'] = [];
        }
        if (!isset($this->config['auth']['sso_client']) || !is_array($this->config['auth']['sso_client'])) {
            $this->config['auth']['sso_client'] = [];
        }
    }
}
