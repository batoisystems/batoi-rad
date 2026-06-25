<?php
namespace RadAdmin;

class All {
    private $runData = [];

    public function __construct(array $runData) {
        $this->runData = $runData;
    }

    public function view() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['route']['h1'] = 'All RAD Admin';
        $this->runData['route']['meta_title'] = 'All RAD Admin';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'All RAD Admin' => '',
        ];
        $sections = [
            'Workspace' => [
                ['Workspaces', 'bi bi-buildings', '/space/view'],
                ['Memberships', 'bi bi-people-fill', '/membership/view'],
                ['Permission Bindings', 'bi bi-key', '/permissionbindings/view'],
            ],
            'Build & Content' => [
                ['Microservicelets', 'bi bi-boxes', '/microservice/view'],
                ['Routes', 'bi bi-signpost-split', '/route/viewall'],
                ['Business Classes', 'bi bi-cpu', '/controller/viewall'],
                ['API Endpoints', 'bi bi-plug', '/apiendpoint/view'],
                ['Navigation', 'bi bi-diagram-3', '/nav/view'],
                ['Theme Templates', 'bi bi-snow2', '/theme/view'],
                ['Theme Assets', 'bi bi-front', '/uiassets/view'],
                ['Libraries', 'bi bi-puzzle', '/vendor/view'],
                ['Content Blocks', 'bi bi-file-text', '/content/view'],
                ['Dot Phrases', 'bi bi-three-dots', '/dotphrase/view'],
            ],
            'Identity & Access' => [
                ['Users', 'bi bi-person-lines-fill', '/user/view'],
                ['Roles', 'bi bi-shield-lock', '/role/view'],
                ['Privilege Matrix', 'bi bi-grid-3x3-gap', '/iam/privilegematrix'],
                ['RAD Admin Privileges', 'bi bi-shield-check', '/privilege/view'],
            ],
            'Data & Config' => [
                ['Data Models', 'bi bi-table', '/appdata/view'],
                ['Data Explorer', 'bi bi-folder-symlink', '/dataexplorer/view'],
                ['IP Access Control', 'bi bi-shield-lock', '/ipaccess/view'],
                ['Config Parameters', 'bi bi-gear', '/config/view'],
                ['System MFA', 'bi bi-shield-lock', '/mfa/dashboard'],
                ['SSO Provider', 'bi bi-link-45deg', '/sso/view'],
                ['SSO Server Clients', 'bi bi-diagram-2', '/ssoclient/view'],
                ['System Tables', 'bi bi-hdd-stack', '/config/systemtables'],
            ],
            'Observability' => [
                ['Access Log &amp; Analytics', 'bi bi-file-earmark-bar-graph', '/accesslog/view'],
                ['Error Analytics', 'bi bi-binoculars', '/errorlog/view'],
                ['SQL Analytics', 'bi bi-database-exclamation', '/sqllog/view'],
                ['Telemetry', 'bi bi-broadcast', '/telemetry/view'],
                ['Find code', 'bi bi-search', '/observability/findcode'],
            ],
            'Engagement' => [
                ['Notifications', 'bi bi-bell', '/notifications/view'],
                ['Notification Settings', 'bi bi-sliders', '/notifications/settings'],
                ['Activity Feed', 'bi bi-clock-history', '/activity/view'],
            ],
            'AI & Automation' => [
                ['AI Wizard', 'bi bi-stars', '/microservice/aiwizard'],
                ['Batoi Intelligence', 'bi bi-stars', '/aiassist'],
                ['AI Code Studio', 'bi bi-terminal', '/codex/view'],
                ['AI Settings', 'bi bi-gear', '/aiconfig/view'],
            ],
            'Governance' => [
                ['Version Explorer', 'bi bi-clock-history', '/version'],
                ['Technical Docs', 'bi bi-journal-richtext', '/techdocs/view'],
                ['DevSecOps Report', 'bi bi-shield-check', '/devsecops/view'],
                ['Static Code Analysis', 'bi bi-search', '/sca/view'],
                ['Changelog', 'bi bi-journal-arrow-down', '/governance/changelog'],
                ['Code Insights', 'bi bi-graph-up', '/governance/insights'],
                ['System Health', 'bi bi-heart-pulse', '/governance/health'],
                ['Stray Routes', 'bi bi-exclamation-triangle', '/governance/strayroutes'],
                ['Queue Overview', 'bi bi-clock-history', '/queue/overview'],
                ['Queue Jobs', 'bi bi-list-check', '/queue/jobs'],
                ['Queue History', 'bi bi-activity', '/queue/history'],
                ['Cron Setup', 'bi bi-terminal', '/queue/cron'],
                ['UI Templates', 'bi bi-files', '/uitpl/view'],
                ['RAD Dev Guide', 'bi bi-journal-code', '/devguide/view'],
                ['Test Plans', 'bi bi-clipboard-check', '/testplan/view'],
                ['Upgrades', 'bi bi-arrow-repeat', '/upgrade/view'],
            ],
        ];

        $role = $priv->role();
        if ($role !== 'system_admin') {
            if (!empty($sections['Data & Config'])) {
                $sections['Data & Config'] = array_values(array_filter($sections['Data & Config'], function ($item) {
                    return $item[2] !== '/ipaccess/view';
                }));
            }
            if (!empty($sections['Identity & Access'])) {
                $sections['Identity & Access'] = array_values(array_filter($sections['Identity & Access'], function ($item) {
                    return $item[2] !== '/privilege/view';
                }));
            }
            if (!empty($sections['AI & Automation'])) {
                $sections['AI & Automation'] = array_values(array_filter($sections['AI & Automation'], function ($item) {
                    return $item[2] !== '/aiconfig/view';
                }));
            }
            if (!empty($sections['Engagement'])) {
                $sections['Engagement'] = array_values(array_filter($sections['Engagement'], function ($item) {
                    return $item[2] !== '/notifications/settings';
                }));
            }
        }

        if (!class_exists('\\RadAdmin\\RadAdminCommunity', false)) {
            $communityClass = ($this->runData['config']['dir']['admin'] ?? dirname(__DIR__)) . '/classes/RadAdminCommunity.cls.php';
            if (is_file($communityClass)) {
                require_once $communityClass;
            }
        }
        $sections = \RadAdmin\RadAdminCommunity::filterNavSections($sections, $this->runData['config'] ?? []);

        $this->runData['data']['sections'] = $sections;
        return $this->runData;
    }
}
