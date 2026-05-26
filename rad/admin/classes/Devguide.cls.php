<?php
namespace RadAdmin;

class Devguide {
    private $runData = [];
    private $errorHandler;
    private $db;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->errorHandler = $runData['errorHandler'] ?? null;
        $this->db = $runData['db'] ?? null;
    }

    public function view() {
        $this->runData['route']['h1'] = 'RAD Dev Guide';
        $this->runData['route']['meta_title'] = 'RAD Dev Guide';
        $this->runData['route']['subheading'] = 'Guidance, references, and architecture visuals for RAD application development.';
        $this->runData['route']['breadcrumb'] = ['RAD Dev Guide' => ''];
        $this->runData['data']['devguide_links'] = [
            [
                'title' => 'Tools & Utilities',
                'desc' => 'Curated list of built-in tools and utilities for RAD Admin and app development.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/tools',
                'icon' => 'bi-wrench'
            ],
            [
                'title' => 'Application Classes',
                'desc' => 'Read-only documentation of application classes discovered under core/app.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/appclasses',
                'icon' => 'bi-journal-code'
            ],
            [
                'title' => 'Data Field Types',
                'desc' => 'Reference for available data field types from s_data_field_type.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/datatypes',
                'icon' => 'bi-list-check'
            ],
            [
                'title' => 'Architecture Diagrams',
                'desc' => 'Interactive architecture visuals generated on-canvas (coming soon).',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/diagrams',
                'icon' => 'bi-diagram-3'
            ],
            [
                'title' => 'Access Resolution Diagrams',
                'desc' => 'Visualize principal-to-role-to-permission binding chains for route and microservice access.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/access-diagrams',
                'icon' => 'bi-shield-lock'
            ],
            [
                'title' => 'Request Flow Diagram',
                'desc' => 'Sequence view of bootstrap, dispatch, and render flow for Web, API, and RAD Admin requests.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/request-flow',
                'icon' => 'bi-diagram-2'
            ],
            [
                'title' => 'Navigation Authorization Map',
                'desc' => 'Visual map of navsets, nav items, role assignments, and permission-bound objects.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/navmap',
                'icon' => 'bi-menu-button-wide'
            ],
            [
                'title' => 'Database Viewer',
                'desc' => 'Visualize database tables and their relationships.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/dbview',
                'icon' => 'bi-diagram-3-fill'
            ],
            [
                'title' => 'UI Templates',
                'desc' => 'Manage embedded UI templates stored under rad/data/uitpl.',
                'href' => $this->runData['route']['rad_admin_url'] . '/uitpl/view',
                'icon' => 'bi-files'
            ],
            [
                'title' => 'RunData Reference',
                'desc' => 'Runtime keys available for non-admin routes (config, route, entity, workspace, and access).',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/rundatadoc',
                'icon' => 'bi-diagram-2'
            ],
            [
                'title' => 'RAD Cache',
                'desc' => 'How file caching works, configuration options, and usage examples.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/cache',
                'icon' => 'bi-lightning-charge'
            ],
            [
                'title' => 'Blue/Green Versioning',
                'desc' => 'Beta/live branching for routes, controllers, content, and data-model schema changes.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/branching',
                'icon' => 'bi-branch'
            ],
            [
                'title' => 'IP Access Control',
                'desc' => 'Operator guide for platform DYN and workspace DYN IP restrictions.',
                'href' => $this->runData['route']['rad_admin_url'] . '/devguide/ip-access-control',
                'icon' => 'bi-shield-lock'
            ],
        ];
        return $this->runData;
    }

    public function ip_access_control() {
        $this->runData['route']['h1'] = 'IP Access Control';
        $this->runData['route']['meta_title'] = 'IP Access Control';
        $this->runData['route']['subheading'] = 'Operator guide for managing platform DYN and workspace DYN IP restrictions.';
        $this->runData['route']['breadcrumb'] = [
            'RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view',
            'IP Access Control' => '',
        ];
        $this->runData['data']['ip_access_sections'] = [
            [
                'id' => 'purpose',
                'title' => 'Purpose',
                'note' => 'IP access control lets administrators limit access to selected parts of the system using approved IP addresses.',
                'bullets' => [
                    'The system supports two operator-managed restriction layers.',
                    'Platform DYN microservicelets',
                    'Workspace-scoped DYN access',
                ],
            ],
            [
                'id' => 'covered',
                'title' => 'What Is Covered',
                'cards' => [
                    [
                        'title' => 'Platform DYN Microservicelets',
                        'body' => 'Use this layer when a non-workspace DYN microservicelet should be limited to approved IP addresses.',
                        'items' => [
                            'Managed from: Build & Code > Microservicelets',
                            'Scope: platform-scoped DYN URLs such as /{ms_name}/...',
                            'Storage: s_ms.s_definition.ip_access',
                            'Applies only when the microservicelet is both DYN and Platform.',
                        ],
                    ],
                    [
                        'title' => 'Workspace-Scoped DYN Access',
                        'body' => 'Use this layer when a workspace should allow workspace-scoped DYN traffic only from approved IP addresses.',
                        'items' => [
                            'Managed from: Workspace & SaaS > Workspaces',
                            'Scope: workspace DYN URLs such as /{workspace_prefix}/{workspace_slug}/{ms_name}/...',
                            'Storage: s_space.s_definition.ip_access',
                            'This restriction is applied at the workspace level.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'not-changed',
                'title' => 'What Is Not Changed',
                'bullets' => [
                    'API allowlist enforcement remains in the existing API stack.',
                    'This guide does not change API key IP restrictions.',
                    'Static route authorization and permission bindings continue to work as before.',
                ],
            ],
            [
                'id' => 'configure',
                'title' => 'Where To Configure Each Layer',
                'cards' => [
                    [
                        'title' => 'Platform DYN Microservicelet',
                        'items' => [
                            'Open from: Build & Code > Microservicelets',
                            'Fields: Enable platform IP allowlist, Allowed IPs',
                            'Behavior: active only for Platform + DYN.',
                        ],
                    ],
                    [
                        'title' => 'Workspace',
                        'items' => [
                            'Open from: Workspace & SaaS > Workspaces',
                            'Fields: Enable workspace allowlist, Allowed IPs',
                            'Behavior: applies to workspace-scoped DYN traffic for that workspace.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'input',
                'title' => 'Input Format',
                'note' => 'Both layers use the same IP entry format.',
                'bullets' => [
                    'one IP per line',
                    'comma-separated IPs',
                    'mixed comma and newline input',
                    'only valid IPv4 or IPv6 values are accepted',
                    'blank entries are ignored',
                    'duplicate IPs are removed during normalization',
                    'invalid entries cause the save to fail with a user-facing error',
                ],
                'examples' => [
                    [
                        'label' => 'Line-separated example',
                        'code' => "127.0.0.1\n::1\n203.0.113.10\n198.51.100.22",
                    ],
                    [
                        'label' => 'Comma-separated example',
                        'code' => '127.0.0.1, ::1, 203.0.113.10',
                    ],
                ],
            ],
            [
                'id' => 'runtime',
                'title' => 'Runtime Behavior',
                'cards' => [
                    [
                        'title' => 'Platform DYN',
                        'items' => [
                            'DYN platform requests are checked against the microservicelet-level allowlist when enabled.',
                            'If no microservicelet allowlist is enabled, the request is not blocked by this layer.',
                        ],
                    ],
                    [
                        'title' => 'Workspace DYN',
                        'items' => [
                            'Workspace DYN requests are checked against the workspace-level allowlist when enabled.',
                            'If no workspace allowlist is enabled, the request is not blocked by this layer.',
                        ],
                    ],
                    [
                        'title' => 'Blocked Requests',
                        'items' => [
                            'Runtime DYN access returns a 403 Forbidden response.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'patterns',
                'title' => 'Recommended Usage Patterns',
                'cards' => [
                    [
                        'title' => 'Restrict one internal platform DYN service',
                        'items' => [
                            'Use when one platform DYN microservicelet should be private to office or VPN.',
                            'Configure the target microservicelet from Build & Code > Microservicelets.',
                        ],
                    ],
                    [
                        'title' => 'Restrict one customer workspace',
                        'items' => [
                            'Use when a workspace should be reachable only from customer-approved networks.',
                            'Configure the workspace from Workspace & SaaS > Workspaces.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'ops',
                'title' => 'Operational Notes',
                'bullets' => [
                    'Prefer stable office, VPN, or bastion IPs.',
                    'Confirm your own current IP before enabling a new allowlist.',
                    'Test a small allowlist first in lower environments before enabling it in production.',
                    'Review workspace and platform settings separately; they solve different problems.',
                ],
            ],
            [
                'id' => 'storage',
                'title' => 'Files and Storage Summary',
                'rows' => [
                    ['label' => 'Platform DYN microservicelet settings', 'value' => 's_ms.s_definition.ip_access'],
                    ['label' => 'Workspace settings', 'value' => 's_space.s_definition.ip_access'],
                ],
            ],
            [
                'id' => 'checklist',
                'title' => 'Quick Checklist',
                'bullets' => [
                    'Confirm the correct target layer before enabling a restriction.',
                    'Confirm the intended IP list.',
                    'Confirm the target URLs and environment.',
                    'After saving, verify that approved IPs can access and non-approved IPs are blocked.',
                    'Verify that unrelated routes still behave as expected.',
                ],
            ],
        ];
        return $this->runData;
    }

    public function appclasses() {
        $this->runData['route']['h1'] = 'Application Classes';
        $this->runData['route']['meta_title'] = 'Application Classes';
        $this->runData['route']['subheading'] = 'Read-only documentation of application classes under core/app.';
        $this->runData['route']['breadcrumb'] = ['RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view', 'Application Classes' => ''];

        $classes = $this->scanClasses();
        if (!empty($classes)) {
            usort($classes, function ($a, $b) {
                return strcasecmp($a['short_name'] ?? '', $b['short_name'] ?? '');
            });
        }
        $this->runData['data']['app_classes'] = $classes;

        return $this->runData;
    }

    public function rundatadoc() {
        $this->runData['route']['h1'] = 'RunData Reference';
        $this->runData['route']['meta_title'] = 'RunData Reference';
        $this->runData['route']['subheading'] = 'Non-admin runtime keys available on $this->runData, grouped by area.';
        $this->runData['route']['breadcrumb'] = [
            'RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view',
            'RunData Reference' => '',
        ];

        $sections = [
            [
                'id' => 'basics',
                'title' => 'Core Runtime',
                'note' => 'Always available on non-admin routes.',
                'items' => [
                    ['key' => "config", 'code' => "\$this->runData['config']", 'desc' => 'System/app paths and settings (sys, app, dir).'],
                    ['key' => "db", 'code' => "\$this->runData['db']", 'desc' => 'Database instance.'],
                    ['key' => "request", 'code' => "\$this->runData['request']", 'desc' => 'Request object with method, get/post, uri, csrf_token (if set).'],
                    ['key' => "route", 'code' => "\$this->runData['route']", 'desc' => 'Route metadata (h1, meta_title, url, assets_url, path, pagepart, alerts).'],
                    ['key' => "ms", 'code' => "\$this->runData['ms']", 'desc' => 'Microservicelet metadata (id, uid, name, type, scope, tpl_name).'],
                    ['key' => "nav", 'code' => "\$this->runData['nav']", 'desc' => 'Navigation groups and role-scoped items.'],
                    ['key' => "data", 'code' => "\$this->runData['data']", 'desc' => 'Payload set by route/controller for templates.'],
                ],
            ],
            [
                'id' => 'dynamic-routes',
                'title' => 'Dynamic Routes (DYN)',
                'note' => 'Applies to microservicelets with type DYN.',
                'items' => [
                    ['key' => "route.path", 'code' => "\$this->runData['route']['path']", 'desc' => 'Dynamic route name (single segment in the URL).'],
                    ['key' => "route_definition.method", 'code' => "\$this->runData['route']['definition']['method']", 'desc' => 'Service method for DYN (defaults to index if not set).'],
                    ['key' => "url.pattern", 'code' => "/{ms_name}/{route_name}/... or /{workspace_slug_prefix}/{space_name}/{ms_name}/{route_name}/...", 'desc' => 'URL pattern for DYN (workspace scope uses workspace prefix + space slug).'],
                ],
            ],
            [
                'id' => 'entity',
                'title' => 'Entity and Access',
                'note' => 'Available after login or when session is present.',
                'items' => [
                    ['key' => "entity.id", 'code' => "\$this->runData['entity']['id']", 'desc' => 'Entity ID.'],
                    ['key' => "entity.uid", 'code' => "\$this->runData['entity']['uid']", 'desc' => 'Entity UID.'],
                    ['key' => "entity.fullname", 'code' => "\$this->runData['entity']['fullname']", 'desc' => 'Display name.'],
                    ['key' => "entity.username", 'code' => "\$this->runData['entity']['username']", 'desc' => 'Login identity.'],
                    ['key' => "entity.nonsaas_role_id", 'code' => "\$this->runData['entity']['nonsaas_role_id']", 'desc' => 'Primary non-SaaS (platform) role ID.'],
                    ['key' => "entity.role_id", 'code' => "\$this->runData['entity']['role_id']", 'desc' => 'Legacy role key; may hold an array of role IDs. Prefer nonsaas_role_id.'],
                    ['key' => "entity.is_logged_in", 'code' => "\$this->runData['entity']['is_logged_in']", 'desc' => 'Boolean login status.'],
                    ['key' => "entity.route_access", 'code' => "\$this->runData['entity']['route_access']", 'desc' => 'Y/N after access checks.'],
                    ['key' => "entity.spaces", 'code' => "\$this->runData['entity']['spaces']", 'desc' => 'Map of workspace IDs to role flags when populated.'],
                ],
            ],
            [
                'id' => 'workspace',
                'title' => 'Workspace Scope',
                'note' => 'Only for workspace-scoped microservicelets.',
                'items' => [
                    ['key' => "route.space_id", 'code' => "\$this->runData['route']['space_id']", 'desc' => 'Workspace ID resolved from the URL segment.'],
                    ['key' => "route.space_uid", 'code' => "\$this->runData['route']['space_uid']", 'desc' => 'Workspace UID resolved from the URL segment.'],
                    ['key' => "route.space_slug", 'code' => "\$this->runData['route']['space_slug']", 'desc' => 'Workspace slug resolved from the URL segment (STA/DYN).'],
                    ['key' => "route.space_role_id", 'code' => "\$this->runData['route']['space_role_id']", 'desc' => 'Workspace role for the current space (resolved by membership).'],
                    ['key' => "route.ms_role_id", 'code' => "\$this->runData['route']['ms_role_id']", 'desc' => 'MS-scoped role for the current space+microservicelet (if any).'],
                    ['key' => "route.access_scope", 'code' => "\$this->runData['route']['access_scope']", 'desc' => 'public/private access scope derived from ms scope.'],
                    ['key' => "entity.space_id", 'code' => "\$this->runData['entity']['space_id']", 'desc' => 'Array of workspace IDs associated with the user.'],
                    ['key' => "entity.space_ids_csv", 'code' => "\$this->runData['entity']['space_ids_csv']", 'desc' => 'Comma-separated workspace IDs.'],
                    ['key' => "entity.spaces_roles", 'code' => "\$this->runData['entity']['spaces_roles']", 'desc' => 'Workspace role map: space_id => role metadata array.'],
                    ['key' => "entity.roles.by_space", 'code' => "\$this->runData['entity']['roles']['by_space']", 'desc' => 'Role map keyed by space_id with role_id + scope_level + ms_id.' ],
                ],
            ],
            [
                'id' => 'alerts',
                'title' => 'Alerts and UI Helpers',
                'note' => 'Optional keys used by themes and templates.',
                'items' => [
                    ['key' => "route.alert", 'code' => "\$this->runData['route']['alert']", 'desc' => 'Alert type (success/info/warning/danger).'],
                    ['key' => "route.alert_message", 'code' => "\$this->runData['route']['alert_message']", 'desc' => 'Alert message content.'],
                    ['key' => "route.backlink", 'code' => "\$this->runData['route']['backlink']", 'desc' => 'Optional backlink URL for the page header.'],
                ],
            ],
            [
                'id' => 'debug',
                'title' => 'Debug Block',
                'note' => 'Visible only when dev_debug_flag=Y and debug_block=1 for allowed users.',
                'items' => [
                    ['key' => "route.debug_block", 'code' => "\$this->runData['route']['debug_block']", 'desc' => 'Debug payload rendered by the theme (timing + checkpoints).'],
                    ['key' => "debug.checkpoints[]", 'code' => "\$this->runData['debug']['checkpoints']", 'desc' => 'Optional checkpoints for perf timing.'],
                ],
            ],
            [
                'id' => 'legacy',
                'title' => 'Legacy Shapes',
                'note' => 'Some older themes use these. Prefer the primary keys above.',
                'items' => [
                    ['key' => "entity.user.id", 'code' => "\$this->runData['entity']['user']['id']", 'desc' => 'Legacy user id.'],
                    ['key' => "entity.user.fullname", 'code' => "\$this->runData['entity']['user']['fullname']", 'desc' => 'Legacy fullname.'],
                    ['key' => "entity.user.username", 'code' => "\$this->runData['entity']['user']['username']", 'desc' => 'Legacy username.'],
                    ['key' => "entity.user.role_id", 'code' => "\$this->runData['entity']['user']['role_id']", 'desc' => 'Legacy role id.'],
                ],
            ],
        ];

        $this->runData['data']['rundata_sections'] = $sections;
        return $this->runData;
    }

    public function branching() {
        $this->runData['route']['h1'] = 'Blue/Green (Beta/Live) Versioning';
        $this->runData['route']['meta_title'] = 'Blue/Green (Beta/Live) Versioning';
        $this->runData['route']['subheading'] = 'How branching works, what is safe to edit, and how to promote changes without disrupting live traffic.';
        $this->runData['route']['breadcrumb'] = [
            'RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view',
            'Blue/Green Versioning' => '',
        ];
        return $this->runData;
    }

    public function cache() {
        $this->runData['route']['h1'] = 'RAD Cache';
        $this->runData['route']['meta_title'] = 'RAD Cache';
        $this->runData['route']['subheading'] = 'File-based caching for routes, data models, and content blocks.';
        $this->runData['route']['breadcrumb'] = [
            'RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view',
            'RAD Cache' => '',
        ];

        $sections = [
            [
                'id' => 'what',
                'title' => 'What is RAD Cache',
                'note' => 'RAD Cache stores rendered route output and content payloads on disk to reduce DB and template work.',
                'bullets' => [
                    'Location: rad/data/cache/{ms_name}/route_{route_id} or content_{content_id}.',
                    'Routes are cached only for public GET requests without a logged-in session.',
                    'Cache entries auto-expire by TTL and can be purged manually.',
                ],
            ],
            [
                'id' => 'backend',
                'title' => 'How it is maintained (backend)',
                'note' => 'Cache entries are created by the runtime and stored with metadata.',
                'bullets' => [
                    'Variant keys are derived from URL segments + query (filtered), host, and space_id.',
                    'Payload is stored with created_at and ttl in a JSON wrapper (base64 payload).',
                    'Expired entries are removed on next read.',
                ],
            ],
            [
                'id' => 'developer',
                'title' => 'How to use (developer)',
                'note' => 'Override cache behavior in route/content/DM definition or pass cache options in code.',
                'examples' => [
                    [
                        'label' => 'Route cache override (s_msroute.s_definition)',
                        'code' => json_encode([
                            'cache' => [
                                'enabled' => 'Y',
                                'ttl' => 300,
                            ]
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ],
                    [
                        'label' => 'DataRecord list with DM cache',
                        'code' => "\$records = \$dataRecord->list('a_tasks', ['livestatus' => 1], 50, 0, ['id' => 'DESC'], [\n    'cache' => [\n        'ms_name' => \$this->runData['ms']['name'],\n        'dm_id' => 12,\n        'space_id' => \$this->runData['route']['space_id'] ?? 0,\n        'ttl' => 300\n    ]\n]);",
                    ],
                    [
                        'label' => 'Bypass cache for a request',
                        'code' => 'Use ?nocache=1 on the URL to bypass.',
                    ],
                ],
            ],
            [
                'id' => 'admin',
                'title' => 'How to manage (admin/user)',
                'note' => 'Use Governance → Cache to purge entries.',
                'bullets' => [
                    'Mass purge clears all cached entries.',
                    'Purge by microservicelet, type, or specific item.',
                    'Use purge after code deployment or template/content updates.',
                ],
            ],
            [
                'id' => 'config',
                'title' => 'Config parameters',
                'note' => 'Stored in s_config (origin=S).',
                'rows' => [
                    [
                        'handle' => 'rad_cache_enabled',
                        'desc' => 'Y/N to enable cache globally.',
                        'example' => 'Y',
                    ],
                    [
                        'handle' => 'rad_cache_ttl',
                        'desc' => 'Default TTLs in seconds.',
                        'example' => '{"default":300,"route":300,"dm":300,"content":600}',
                    ],
                    [
                        'handle' => 'rad_cache_variant_defaults',
                        'desc' => 'Controls which inputs build the variant key.',
                        'example' => '{"segments":true,"query":true,"space":true,"host":true}',
                    ],
                    [
                        'handle' => 'rad_cache_ignore_query',
                        'desc' => 'Query params ignored when building variants.',
                        'example' => '["debug_block","nocache","t","_","utm_source","utm_medium","utm_campaign","utm_term","utm_content"]',
                    ],
                ],
            ],
            [
                'id' => 'troubleshoot',
                'title' => 'Troubleshooting',
                'note' => 'Common reasons for cache misses.',
                'bullets' => [
                    'Route is private or user is logged in (cache disabled by design).',
                    'Request is POST/PUT/DELETE (cache only for GET).',
                    'debug_block=1 or nocache=1 is present.',
                    'TTL expired or cache purged.',
                ],
            ],
        ];

        $this->runData['data']['cache_sections'] = $sections;
        return $this->runData;
    }

    public function appclass() {
        $radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
        $slug = trim($this->runData['route']['pathparts'][3] ?? ($this->runData['request']->get['class'] ?? ''));

        $this->runData['route']['h1'] = 'Class Source';
        $this->runData['route']['meta_title'] = 'Class Source';
        $this->runData['route']['breadcrumb'] = [
            'RAD Dev Guide' => $radAdminUrl . '/devguide/view',
            'Application Classes' => $radAdminUrl . '/devguide/appclasses',
            'Class Source' => '',
        ];

        $classes = $this->scanClasses();
        $match = null;
        foreach ($classes as $class) {
            if (strcasecmp($class['short_name'], $slug) === 0) {
                $match = $class;
                break;
            }
        }

        if (!$match) {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = 'Class not found. Choose a class from Application Classes to view its source.';
            $this->runData['data']['class_source'] = null;
            return $this->runData;
        }

        $source = '';
        $appDir = rtrim($this->runData['config']['dir']['app'] ?? '', '/');
        if ($appDir === '' || !is_dir($appDir)) {
            $coreDir = rtrim($this->runData['config']['dir']['core'] ?? '', '/');
            if ($coreDir !== '') {
                $appDir = $coreDir . '/app';
            }
        }
        $realAppDir = realpath($appDir) ?: '';
        $realFile = realpath($match['file']) ?: '';
        if ($realAppDir !== '' && $realFile !== '' && strncmp($realFile, $realAppDir, strlen($realAppDir)) === 0) {
            $source = @file_get_contents($realFile) ?: '';
        }

        $this->runData['data']['class_source'] = [
            'meta' => $match,
            'source' => $source,
        ];
        return $this->runData;
    }

    public function tools() {
        $this->runData['route']['h1'] = 'Tools & Utilities';
        $this->runData['route']['meta_title'] = 'Tools & Utilities';
        $this->runData['route']['subheading'] = 'Reference for built-in tools that support RAD development and operations.';
        $this->runData['route']['breadcrumb'] = ['RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view', 'Tools & Utilities' => ''];

        $tools = [
            ['name' => 'Version Explorer', 'path' => '/version', 'desc' => 'Browse version history across objects.'],
            ['name' => 'Changelog', 'path' => '/governance/changelog', 'desc' => 'Recent changes and snapshots/diffs.'],
            ['name' => 'Code Insights', 'path' => '/governance/insights', 'desc' => 'App code touch analytics.'],
            ['name' => 'System Health', 'path' => '/governance/health', 'desc' => 'Log health, cleanup, and activity rebuild tools.'],
            ['name' => 'Queue Overview', 'path' => '/queue/overview', 'desc' => 'Queue dashboard and cron shortcuts.'],
            ['name' => 'Queue Jobs', 'path' => '/queue/jobs', 'desc' => 'Scheduled job list and run controls.'],
            ['name' => 'Queue History', 'path' => '/queue/history', 'desc' => 'Run history by date and job.'],
            ['name' => 'Cron Setup', 'path' => '/queue/cron', 'desc' => 'Cron commands and token setup guidance.'],
            ['name' => 'Static Code Analysis', 'path' => '/sca', 'desc' => 'Review static analysis findings.'],
            ['name' => 'DevSecOps Report', 'path' => '/devsecops', 'desc' => 'Security, dependency, and job posture.'],
            ['name' => 'Telemetry', 'path' => '/telemetry/view', 'desc' => 'Signals and events across the stack.'],
            ['name' => 'Error Analytics', 'path' => '/errorlog', 'desc' => 'Error events and trends.'],
            ['name' => 'SQL Analytics', 'path' => '/sqllog', 'desc' => 'Database query insights.'],
            ['name' => 'UI Templates', 'path' => '/uitpl/view', 'desc' => 'Embedded UI templates for microservice flows.'],
        ];
        $this->runData['data']['tools'] = $tools;
        return $this->runData;
    }

    public function datatypes() {
        $this->runData['route']['h1'] = 'Data Field Types';
        $this->runData['route']['meta_title'] = 'Data Field Types';
        $this->runData['route']['subheading'] = 'Reference from s_data_field_type for building and validating data schemas.';
        $this->runData['route']['breadcrumb'] = ['RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view', 'Data Field Types' => ''];

        $rows = [];
        if ($this->db) {
            try {
                // s_data_field_type schema includes s_name, s_description, s_definition (no s_regex/s_max_length)
                $rows = $this->db->query("SELECT id, s_name, s_description, s_definition FROM s_data_field_type ORDER BY s_name");
            } catch (\Throwable $e) {
                if ($this->errorHandler) {
                    $this->errorHandler->handleException($e);
                }
            }
            // Fallback: try generic select if the direct query returned empty
            if (empty($rows)) {
                try {
                    $rows = $this->db->select('s_data_field_type', [], true, ['s_name' => 'ASC']);
                } catch (\Throwable $e) {
                    if ($this->errorHandler) {
                        $this->errorHandler->handleException($e);
                    }
                }
            }
        }
        $this->runData['data']['field_types'] = is_array($rows) ? $rows : [];
        if (empty($this->runData['data']['field_types'])) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_title'] = 'No field types found';
            $this->runData['route']['alert_message'] = 'The table s_data_field_type is empty. Add field type definitions to see them here.';
        }
        return $this->runData;
    }

    // URL-friendly alias /devguide/data-types
    public function data_types() {
        return $this->datatypes();
    }

    // Allow hyphenated mapping from controller sanitizer
    public function data_types_dash() {
        return $this->datatypes();
    }

    public function diagrams() {
        $this->runData['route']['h1'] = 'Architecture Diagrams';
        $this->runData['route']['meta_title'] = 'Architecture Diagrams';
        $this->runData['route']['subheading'] = 'Interactive, programmatically generated visuals (nodes/edges) for services, routes, and data flows.';
        $this->runData['route']['breadcrumb'] = ['RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view', 'Architecture Diagrams' => ''];
        $this->runData['data']['diagram_seed'] = $this->buildDiagramData();
        return $this->runData;
    }

    public function access_diagrams() {
        $this->runData['route']['h1'] = 'Access Resolution Diagrams';
        $this->runData['route']['meta_title'] = 'Access Resolution Diagrams';
        $this->runData['route']['subheading'] = 'Trace principal memberships, roles, and permission bindings to route/microservice objects.';
        $this->runData['route']['breadcrumb'] = ['RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view', 'Access Resolution Diagrams' => ''];
        $this->runData['data']['access_diagram_seed'] = $this->buildAccessDiagramData();
        return $this->runData;
    }

    public function request_flow() {
        $this->runData['route']['h1'] = 'Request Flow Diagram';
        $this->runData['route']['meta_title'] = 'Request Flow Diagram';
        $this->runData['route']['subheading'] = 'Sequence diagrams for request lifecycle across Web, API, and RAD Admin gateways.';
        $this->runData['route']['breadcrumb'] = ['RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view', 'Request Flow Diagram' => ''];
        $this->runData['data']['request_flow_seed'] = $this->buildRequestFlowData();
        return $this->runData;
    }

    public function navmap() {
        $this->runData['route']['h1'] = 'Navigation Authorization Map';
        $this->runData['route']['meta_title'] = 'Navigation Authorization Map';
        $this->runData['route']['subheading'] = 'Inspect navsets, nav items, role assignments, and permission object bindings.';
        $this->runData['route']['breadcrumb'] = ['RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view', 'Navigation Authorization Map' => ''];
        $this->runData['data']['navmap_seed'] = $this->buildNavmapData();
        return $this->runData;
    }

    public function dbview() {
        $this->runData['route']['h1'] = 'Database Viewer';
        $this->runData['route']['meta_title'] = 'Database Viewer';
        $this->runData['route']['subheading'] = 'Interactive view of tables and foreign key relationships.';
        $this->runData['route']['breadcrumb'] = ['RAD Dev Guide' => $this->runData['route']['rad_admin_url'] . '/devguide/view', 'Database Viewer' => ''];
        $this->runData['data']['dbview_seed'] = $this->buildDbviewData();
        return $this->runData;
    }

    private function buildDbviewData(): array {
        if (!$this->db) {
            return ['nodes' => [], 'edges' => []];
        }
        $nodes = [];
        $edges = [];
        try {
            $schemaRow = $this->db->query("SELECT DATABASE() AS dbname");
            $schema = is_array($schemaRow) && !empty($schemaRow[0]['dbname']) ? $schemaRow[0]['dbname'] : '';
            $tables = $this->db->query("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME LIMIT 400");
            $columns = $this->db->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_KEY, IS_NULLABLE, DATA_TYPE, COLUMN_TYPE, COLUMN_DEFAULT FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION");
            $fks = $this->db->query("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
            $tables = is_array($tables) ? $tables : [];
            $columns = is_array($columns) ? $columns : [];
            $fks = is_array($fks) ? $fks : [];
            $colsByTable = [];
            foreach ($columns as $col) {
                $t = $col['TABLE_NAME'];
                if (!isset($colsByTable[$t])) $colsByTable[$t] = [];
                $colsByTable[$t][] = [
                    'name' => $col['COLUMN_NAME'],
                    'type' => $col['COLUMN_TYPE'] ?: $col['DATA_TYPE'],
                    'nullable' => strtoupper($col['IS_NULLABLE']) === 'YES',
                    'key' => $col['COLUMN_KEY'],
                    'default' => $col['COLUMN_DEFAULT'],
                ];
            }
            $fkByTable = [];
            foreach ($fks as $fk) {
                $t = $fk['TABLE_NAME'];
                if (!isset($fkByTable[$t])) $fkByTable[$t] = [];
                $fkByTable[$t][] = $fk;
                $edges[] = [
                    'from' => 'tbl-' . $fk['TABLE_NAME'],
                    'to' => 'tbl-' . $fk['REFERENCED_TABLE_NAME'],
                    'label' => $fk['COLUMN_NAME'] . ' → ' . $fk['REFERENCED_COLUMN_NAME'],
                    'meta' => [
                        'column' => $fk['COLUMN_NAME'],
                        'ref_table' => $fk['REFERENCED_TABLE_NAME'],
                        'ref_column' => $fk['REFERENCED_COLUMN_NAME'],
                    ],
                ];
            }
            foreach ($tables as $tbl) {
                $name = $tbl['TABLE_NAME'];
                $cols = $colsByTable[$name] ?? [];
                $pkCols = array_column(array_filter($cols, function ($c) {
                    return $c['key'] === 'PRI';
                }), 'name');
                $nodes[] = [
                    'id' => 'tbl-' . $name,
                    'label' => $name,
                    'type' => 'table',
                    'meta' => [
                        'schema' => $schema,
                        'rows' => $tbl['TABLE_ROWS'] ?? null,
                        'columns' => $cols,
                        'pk' => $pkCols,
                        'fk_count' => isset($fkByTable[$name]) ? count($fkByTable[$name]) : 0,
                        'col_count' => count($cols),
                    ],
                ];
            }
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }
        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function buildDiagramData(): array {
        if (!$this->db) {
            return [
                'nodes' => [],
                'edges' => [],
                'summary' => [
                    'node_total' => 0,
                    'edge_total' => 0,
                    'node_counts_by_type' => ['ms' => 0, 'route' => 0, 'controller' => 0],
                    'orphan_count' => 0,
                    'component_count' => 0,
                    'inferred_edge_count' => 0,
                ],
                'top_degree' => [],
                'generated_at' => gmdate('c'),
                'seed_version' => 'v2',
                'limits' => [
                    'microservices' => 200,
                    'routes' => 400,
                    'controllers' => 400,
                ],
                'truncation' => [
                    'microservices' => false,
                    'routes' => false,
                    'controllers' => false,
                    'is_truncated' => false,
                ],
            ];
        }

        $nodes = [];
        $edges = [];
        $msLimit = 200;
        $routeLimit = 400;
        $controllerLimit = 400;
        $microservices = [];
        $routes = [];
        $controllers = [];

        try {
            $microservices = $this->db->query("SELECT id, s_name, uid, s_scope FROM s_ms ORDER BY id LIMIT " . $msLimit);
            if (is_array($microservices)) {
                foreach ($microservices as $ms) {
                    $nodes[] = [
                        'id' => 'ms-' . $ms['id'],
                        'label' => $ms['s_name'] ?? ('MS ' . $ms['id']),
                        'type' => 'ms',
                        'meta' => [
                            'uid' => $ms['uid'] ?? '',
                            'scope' => $ms['s_scope'] ?? '',
                            'access' => (strtolower($ms['s_scope'] ?? '') === 'global') ? 'public' : 'private',
                        ],
                    ];
                }
            }

            $routes = $this->db->query("SELECT id, s_name, s_ms_id, uid, s_entity_scope, s_degree FROM s_msroute ORDER BY id LIMIT " . $routeLimit);
            if (is_array($routes)) {
                foreach ($routes as $route) {
                    $rid = 'route-' . $route['id'];
                    $nodes[] = [
                        'id' => $rid,
                        'label' => $route['s_name'] ?? ('Route ' . $route['id']),
                        'type' => 'route',
                        'meta' => [
                            'ms_id' => (int)($route['s_ms_id'] ?? 0),
                            'uid' => $route['uid'] ?? '',
                            'entity_scope' => $route['s_entity_scope'] ?? '',
                            'degree' => $route['s_degree'] ?? '',
                        ],
                    ];
                    if (!empty($route['s_ms_id'])) {
                        $edges[] = [
                            'from' => 'ms-' . $route['s_ms_id'],
                            'to' => $rid,
                            'label' => 'exposes',
                        ];
                    }
                }
            }

            $controllers = $this->db->query("SELECT id, s_name, s_ms_id, uid, s_type FROM s_mscontroller ORDER BY id LIMIT " . $controllerLimit);
            if (is_array($controllers)) {
                foreach ($controllers as $ctl) {
                    $cid = 'ctl-' . $ctl['id'];
                    $nodes[] = [
                        'id' => $cid,
                        'label' => $ctl['s_name'] ?? ('Controller ' . $ctl['id']),
                        'type' => 'controller',
                        'meta' => [
                            'ms_id' => (int)($ctl['s_ms_id'] ?? 0),
                            'uid' => $ctl['uid'] ?? '',
                            'controller_type' => $ctl['s_type'] ?? '',
                        ],
                    ];
                    if (!empty($ctl['s_ms_id'])) {
                        $edges[] = [
                            'from' => 'ms-' . $ctl['s_ms_id'],
                            'to' => $cid,
                            'label' => 'owns',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }

        // dedupe nodes by id
        $seen = [];
        $uniqueNodes = [];
        foreach ($nodes as $n) {
            if (isset($seen[$n['id']])) {
                continue;
            }
            $seen[$n['id']] = true;
            $uniqueNodes[] = $n;
        }

        $nodeMap = [];
        $nodeCountsByType = [
            'ms' => 0,
            'route' => 0,
            'controller' => 0,
        ];
        foreach ($uniqueNodes as $node) {
            $id = (string)($node['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $nodeMap[$id] = $node;
            $type = (string)($node['type'] ?? '');
            if (isset($nodeCountsByType[$type])) {
                $nodeCountsByType[$type]++;
            }
        }

        $seenEdges = [];
        $routeNodesByMs = [];
        $controllerNodesByMs = [];
        foreach ($uniqueNodes as $node) {
            $type = (string)($node['type'] ?? '');
            $msId = (int)($node['meta']['ms_id'] ?? 0);
            if ($msId <= 0) {
                continue;
            }
            if ($type === 'route') {
                if (!isset($routeNodesByMs[$msId])) {
                    $routeNodesByMs[$msId] = [];
                }
                $routeNodesByMs[$msId][] = $node;
            } elseif ($type === 'controller') {
                if (!isset($controllerNodesByMs[$msId])) {
                    $controllerNodesByMs[$msId] = [];
                }
                $controllerNodesByMs[$msId][] = $node;
            }
        }

        foreach ($routeNodesByMs as $msId => $routeNodes) {
            $ctlNodes = $controllerNodesByMs[$msId] ?? [];
            if (empty($ctlNodes)) {
                continue;
            }
            foreach ($routeNodes as $routeNode) {
                $routeNorm = $this->normalizeDiagramToken((string)($routeNode['label'] ?? $routeNode['id'] ?? ''));
                if ($routeNorm === '') {
                    continue;
                }
                foreach ($ctlNodes as $ctlNode) {
                    $ctlNorm = $this->normalizeDiagramToken((string)($ctlNode['label'] ?? $ctlNode['id'] ?? ''));
                    if ($ctlNorm === '') {
                        continue;
                    }
                    $confidence = '';
                    $method = '';
                    if ($routeNorm === $ctlNorm) {
                        $confidence = 'high';
                        $method = 'name_exact';
                    } elseif (strpos($routeNorm, $ctlNorm) === 0 || strpos($ctlNorm, $routeNorm) === 0) {
                        $confidence = 'medium';
                        $method = 'name_prefix';
                    }
                    if ($confidence === '') {
                        continue;
                    }
                    $edges[] = [
                        'from' => (string)($routeNode['id'] ?? ''),
                        'to' => (string)($ctlNode['id'] ?? ''),
                        'label' => 'uses',
                        'meta' => [
                            'inferred' => true,
                            'method' => $method,
                            'confidence' => $confidence,
                        ],
                    ];
                }
            }
        }

        $validEdges = [];
        foreach ($edges as $edge) {
            $from = (string)($edge['from'] ?? '');
            $to = (string)($edge['to'] ?? '');
            if ($from === '' || $to === '' || !isset($nodeMap[$from]) || !isset($nodeMap[$to])) {
                continue;
            }
            $edgeKey = $from . '|' . $to . '|' . (string)($edge['label'] ?? '') . '|' . (!empty($edge['meta']['inferred']) ? '1' : '0');
            if (isset($seenEdges[$edgeKey])) {
                continue;
            }
            $seenEdges[$edgeKey] = true;
            $validEdges[] = $edge;
        }

        $degrees = [];
        foreach ($nodeMap as $nodeId => $_node) {
            $degrees[$nodeId] = 0;
        }
        foreach ($validEdges as $edge) {
            $from = (string)$edge['from'];
            $to = (string)$edge['to'];
            if (isset($degrees[$from])) {
                $degrees[$from]++;
            }
            if (isset($degrees[$to])) {
                $degrees[$to]++;
            }
        }
        $orphanCount = 0;
        foreach ($degrees as $degree) {
            if ((int)$degree === 0) {
                $orphanCount++;
            }
        }
        $inferredEdgeCount = 0;
        foreach ($validEdges as $edge) {
            if (!empty($edge['meta']['inferred'])) {
                $inferredEdgeCount++;
            }
        }

        $adj = [];
        foreach ($nodeMap as $nodeId => $_node) {
            $adj[$nodeId] = [];
        }
        foreach ($validEdges as $edge) {
            $from = (string)$edge['from'];
            $to = (string)$edge['to'];
            if (!isset($adj[$from]) || !isset($adj[$to])) {
                continue;
            }
            $adj[$from][$to] = true;
            $adj[$to][$from] = true;
        }

        $componentCount = 0;
        $visited = [];
        foreach ($nodeMap as $nodeId => $_node) {
            if (isset($visited[$nodeId])) {
                continue;
            }
            $componentCount++;
            $queue = [$nodeId];
            $visited[$nodeId] = true;
            while (!empty($queue)) {
                $current = array_shift($queue);
                if (!isset($adj[$current])) {
                    continue;
                }
                foreach ($adj[$current] as $neighborId => $_flag) {
                    if (isset($visited[$neighborId])) {
                        continue;
                    }
                    $visited[$neighborId] = true;
                    $queue[] = $neighborId;
                }
            }
        }

        arsort($degrees);
        $topDegree = [];
        $topLimit = 5;
        foreach ($degrees as $nodeId => $degree) {
            if (count($topDegree) >= $topLimit) {
                break;
            }
            $node = $nodeMap[$nodeId] ?? null;
            if (!is_array($node)) {
                continue;
            }
            $topDegree[] = [
                'id' => $nodeId,
                'label' => $node['label'] ?? $nodeId,
                'type' => $node['type'] ?? '',
                'degree' => (int)$degree,
            ];
        }

        $truncation = [
            'microservices' => is_array($microservices) && count($microservices) >= $msLimit,
            'routes' => is_array($routes) && count($routes) >= $routeLimit,
            'controllers' => is_array($controllers) && count($controllers) >= $controllerLimit,
        ];
        $truncation['is_truncated'] = $truncation['microservices'] || $truncation['routes'] || $truncation['controllers'];

        return [
            'nodes' => $uniqueNodes,
            'edges' => $validEdges,
            'summary' => [
                'node_total' => count($uniqueNodes),
                'edge_total' => count($validEdges),
                'node_counts_by_type' => $nodeCountsByType,
                'orphan_count' => $orphanCount,
                'component_count' => $componentCount,
                'inferred_edge_count' => $inferredEdgeCount,
            ],
            'top_degree' => $topDegree,
            'generated_at' => gmdate('c'),
            'seed_version' => 'v2',
            'limits' => [
                'microservices' => $msLimit,
                'routes' => $routeLimit,
                'controllers' => $controllerLimit,
            ],
            'truncation' => $truncation,
        ];
    }

    private function buildAccessDiagramData(): array {
        if (!$this->db) {
            return [
                'nodes' => [],
                'edges' => [],
                'summary' => [
                    'node_total' => 0,
                    'edge_total' => 0,
                    'counts' => ['entity' => 0, 'membership' => 0, 'role' => 0, 'object' => 0],
                ],
                'limits' => [
                    'entities' => 250,
                    'memberships' => 600,
                    'roles' => 250,
                    'bindings' => 900,
                    'objects' => 800,
                ],
                'truncation' => [
                    'entities' => false,
                    'memberships' => false,
                    'roles' => false,
                    'bindings' => false,
                    'objects' => false,
                    'is_truncated' => false,
                ],
            ];
        }

        $entityLimit = 250;
        $membershipLimit = 600;
        $roleLimit = 250;
        $bindingLimit = 900;
        $msLimit = 300;
        $routeLimit = 500;

        $entities = [];
        $memberships = [];
        $roles = [];
        $bindings = [];
        $microservices = [];
        $routes = [];
        $nodes = [];
        $edges = [];

        try {
            $entities = $this->db->query("SELECT id, uid, s_name, s_type, s_email FROM s_entity WHERE livestatus = '1' ORDER BY id LIMIT " . $entityLimit);
            $memberships = $this->db->query("SELECT id, uid, space_id, s_entity_id, s_role_id, s_scope_level, s_ms_id, s_effective_from, s_effective_to FROM s_space_membership WHERE livestatus = '1' ORDER BY id LIMIT " . $membershipLimit);
            $roles = $this->db->query("SELECT id, uid, s_role_name, s_scope FROM s_role WHERE livestatus = '1' ORDER BY id LIMIT " . $roleLimit);
            $bindings = $this->db->query("SELECT id, uid, s_object_type, s_object_id, s_role_id FROM s_permission_binding WHERE livestatus = '1' ORDER BY id LIMIT " . $bindingLimit);
            $microservices = $this->db->query("SELECT id, uid, s_name FROM s_ms WHERE livestatus = '1' ORDER BY id LIMIT " . $msLimit);
            $routes = $this->db->query("SELECT id, uid, s_name, s_ms_id FROM s_msroute WHERE livestatus = '1' ORDER BY id LIMIT " . $routeLimit);
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }

        $entities = is_array($entities) ? $entities : [];
        $memberships = is_array($memberships) ? $memberships : [];
        $roles = is_array($roles) ? $roles : [];
        $bindings = is_array($bindings) ? $bindings : [];
        $microservices = is_array($microservices) ? $microservices : [];
        $routes = is_array($routes) ? $routes : [];

        $msById = [];
        foreach ($microservices as $ms) {
            $mid = (int)($ms['id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            $msNodeId = 'obj-ms-' . $mid;
            $msById[$mid] = $msNodeId;
            $nodes[] = [
                'id' => $msNodeId,
                'label' => (string)($ms['s_name'] ?? ('MS ' . $mid)),
                'type' => 'object',
                'meta' => [
                    'object_type' => 'ms',
                    'uid' => (string)($ms['uid'] ?? ''),
                    'db_id' => $mid,
                    'detail_path' => '/microservice/detail/' . (string)($ms['uid'] ?? ''),
                ],
            ];
        }

        foreach ($routes as $route) {
            $rid = (int)($route['id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            $routeNodeId = 'obj-route-' . $rid;
            $nodes[] = [
                'id' => $routeNodeId,
                'label' => (string)($route['s_name'] ?? ('Route ' . $rid)),
                'type' => 'object',
                'meta' => [
                    'object_type' => 'route',
                    'uid' => (string)($route['uid'] ?? ''),
                    'db_id' => $rid,
                    'ms_id' => (int)($route['s_ms_id'] ?? 0),
                    'detail_path' => '/route/detail/' . (string)($route['uid'] ?? ''),
                ],
            ];
            $routeMsId = (int)($route['s_ms_id'] ?? 0);
            if ($routeMsId > 0 && isset($msById[$routeMsId])) {
                $edges[] = [
                    'from' => $msById[$routeMsId],
                    'to' => $routeNodeId,
                    'label' => 'contains',
                    'meta' => ['relation' => 'contains'],
                ];
            }
        }

        $roleNodeIds = [];
        foreach ($roles as $role) {
            $roleId = (int)($role['id'] ?? 0);
            if ($roleId <= 0) {
                continue;
            }
            $roleNodeId = 'role-' . $roleId;
            $roleNodeIds[$roleId] = $roleNodeId;
            $nodes[] = [
                'id' => $roleNodeId,
                'label' => (string)($role['s_role_name'] ?? ('Role ' . $roleId)),
                'type' => 'role',
                'meta' => [
                    'uid' => (string)($role['uid'] ?? ''),
                    'db_id' => $roleId,
                    'scope' => (string)($role['s_scope'] ?? ''),
                    'detail_path' => '/role/viewone/' . (string)($role['uid'] ?? ''),
                ],
            ];
        }

        $entityNodeIds = [];
        foreach ($entities as $entity) {
            $entityId = (int)($entity['id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }
            $entityNodeId = 'ent-' . $entityId;
            $entityNodeIds[$entityId] = $entityNodeId;
            $label = trim((string)($entity['s_name'] ?? ''));
            if ($label === '') {
                $label = 'Entity ' . $entityId;
            }
            $nodes[] = [
                'id' => $entityNodeId,
                'label' => $label,
                'type' => 'entity',
                'meta' => [
                    'uid' => (string)($entity['uid'] ?? ''),
                    'db_id' => $entityId,
                    'entity_type' => (string)($entity['s_type'] ?? ''),
                    'email' => (string)($entity['s_email'] ?? ''),
                    'detail_path' => ((string)($entity['s_type'] ?? '') === 'A'
                        ? '/api/viewone/' . (string)($entity['uid'] ?? '')
                        : '/user/viewone/' . (string)($entity['uid'] ?? '')),
                ],
            ];
        }

        foreach ($memberships as $membership) {
            $membershipId = (int)($membership['id'] ?? 0);
            if ($membershipId <= 0) {
                continue;
            }
            $memNodeId = 'mem-' . $membershipId;
            $nodes[] = [
                'id' => $memNodeId,
                'label' => 'Space #' . ((int)($membership['space_id'] ?? 0)),
                'type' => 'membership',
                'meta' => [
                    'uid' => (string)($membership['uid'] ?? ''),
                    'db_id' => $membershipId,
                    'space_id' => (int)($membership['space_id'] ?? 0),
                    'scope_level' => (string)($membership['s_scope_level'] ?? 'workspace'),
                    's_ms_id' => (int)($membership['s_ms_id'] ?? 0),
                    'effective_from' => (string)($membership['s_effective_from'] ?? ''),
                    'effective_to' => (string)($membership['s_effective_to'] ?? ''),
                ],
            ];

            $entityId = (int)($membership['s_entity_id'] ?? 0);
            if ($entityId > 0 && isset($entityNodeIds[$entityId])) {
                $edges[] = [
                    'from' => $entityNodeIds[$entityId],
                    'to' => $memNodeId,
                    'label' => 'member_of',
                    'meta' => ['relation' => 'member_of'],
                ];
            }

            $roleId = (int)($membership['s_role_id'] ?? 0);
            if ($roleId > 0 && isset($roleNodeIds[$roleId])) {
                $edges[] = [
                    'from' => $memNodeId,
                    'to' => $roleNodeIds[$roleId],
                    'label' => 'has_role',
                    'meta' => ['relation' => 'has_role'],
                ];
            }
        }

        foreach ($bindings as $binding) {
            $roleId = (int)($binding['s_role_id'] ?? 0);
            if ($roleId <= 0 || !isset($roleNodeIds[$roleId])) {
                continue;
            }
            $objType = (string)($binding['s_object_type'] ?? '');
            $objId = (int)($binding['s_object_id'] ?? 0);
            if ($objId <= 0 || ($objType !== 'ms' && $objType !== 'route')) {
                continue;
            }
            $targetNodeId = 'obj-' . $objType . '-' . $objId;
            $edges[] = [
                'from' => $roleNodeIds[$roleId],
                'to' => $targetNodeId,
                'label' => 'bound_to',
                'meta' => [
                    'relation' => 'bound_to',
                    'object_type' => $objType,
                    'binding_uid' => (string)($binding['uid'] ?? ''),
                ],
            ];
        }

        $seenNodes = [];
        $uniqueNodes = [];
        foreach ($nodes as $node) {
            $nid = (string)($node['id'] ?? '');
            if ($nid === '' || isset($seenNodes[$nid])) {
                continue;
            }
            $seenNodes[$nid] = true;
            $uniqueNodes[] = $node;
        }

        $nodeMap = [];
        $counts = ['entity' => 0, 'membership' => 0, 'role' => 0, 'object' => 0];
        foreach ($uniqueNodes as $node) {
            $nid = (string)($node['id'] ?? '');
            if ($nid === '') {
                continue;
            }
            $nodeMap[$nid] = $node;
            $type = (string)($node['type'] ?? '');
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        $seenEdges = [];
        $validEdges = [];
        foreach ($edges as $edge) {
            $from = (string)($edge['from'] ?? '');
            $to = (string)($edge['to'] ?? '');
            if ($from === '' || $to === '' || !isset($nodeMap[$from]) || !isset($nodeMap[$to])) {
                continue;
            }
            $key = $from . '|' . $to . '|' . (string)($edge['label'] ?? '');
            if (isset($seenEdges[$key])) {
                continue;
            }
            $seenEdges[$key] = true;
            $validEdges[] = $edge;
        }

        $truncation = [
            'entities' => count($entities) >= $entityLimit,
            'memberships' => count($memberships) >= $membershipLimit,
            'roles' => count($roles) >= $roleLimit,
            'bindings' => count($bindings) >= $bindingLimit,
            'objects' => (count($microservices) >= $msLimit) || (count($routes) >= $routeLimit),
        ];
        $truncation['is_truncated'] = $truncation['entities'] || $truncation['memberships'] || $truncation['roles'] || $truncation['bindings'] || $truncation['objects'];

        return [
            'nodes' => $uniqueNodes,
            'edges' => $validEdges,
            'summary' => [
                'node_total' => count($uniqueNodes),
                'edge_total' => count($validEdges),
                'counts' => $counts,
            ],
            'limits' => [
                'entities' => $entityLimit,
                'memberships' => $membershipLimit,
                'roles' => $roleLimit,
                'bindings' => $bindingLimit,
                'objects' => $msLimit + $routeLimit,
            ],
            'truncation' => $truncation,
            'generated_at' => gmdate('c'),
        ];
    }

    private function buildRequestFlowData(): array {
        $seed = [
            'generated_at' => gmdate('c'),
            'modes' => [
                [
                    'id' => 'web',
                    'label' => 'Web Request',
                    'note' => 'Public site request through GenericController and View rendering.',
                    'steps' => [
                        ['id' => 'boot', 'label' => 'Bootstrap', 'file' => 'public_html/index.php', 'detail' => 'Loads autoloader, config, logger, DB, and shared runData.'],
                        ['id' => 'request', 'label' => 'Request Context', 'file' => 'rad/core/sys/Request.cls.php', 'detail' => 'Sanitizes input, request method, query/post data, and CSRF token.'],
                        ['id' => 'session', 'label' => 'Session Resolve', 'file' => 'rad/core/sys/SessionManager.cls.php', 'detail' => 'Loads session, enforces timeout, resolves entity if logged in.'],
                        ['id' => 'dispatch', 'label' => 'Dispatch', 'file' => 'rad/core/sys/GenericController.cls.php', 'detail' => 'Resolves microservice and route, validates scope and access.'],
                        ['id' => 'routeexec', 'label' => 'Route Execution', 'file' => 'rad/ms/<ms>/route.<id>.(pre|page|post)part.php', 'detail' => 'Runs route pre/page/post parts with runData.'],
                        ['id' => 'render', 'label' => 'Render', 'file' => 'rad/core/sys/View.cls.php', 'detail' => 'Renders theme template and injects route/page data.'],
                    ],
                    'edges' => [
                        ['from' => 'boot', 'to' => 'request'],
                        ['from' => 'request', 'to' => 'session'],
                        ['from' => 'session', 'to' => 'dispatch'],
                        ['from' => 'dispatch', 'to' => 'routeexec'],
                        ['from' => 'routeexec', 'to' => 'render'],
                    ],
                ],
                [
                    'id' => 'api',
                    'label' => 'API Request',
                    'note' => 'Gateway request through ApiController with endpoint/service dispatch.',
                    'steps' => [
                        ['id' => 'boot', 'label' => 'Bootstrap', 'file' => 'public_html/index.php', 'detail' => 'Initializes runtime and resolves /api route family.'],
                        ['id' => 'request', 'label' => 'Request Context', 'file' => 'rad/core/sys/Request.cls.php', 'detail' => 'Parses payload and HTTP metadata.'],
                        ['id' => 'auth', 'label' => 'API Auth', 'file' => 'rad/core/sys/ApiController.cls.php', 'detail' => 'Validates api_key/security_key and allowed api_type/endpoints.'],
                        ['id' => 'dispatch', 'label' => 'API Dispatch', 'file' => 'rad/core/sys/ApiController.cls.php', 'detail' => 'Dispatches to application route or system service/table target.'],
                        ['id' => 'service', 'label' => 'Service Execute', 'file' => 'rad/core/sys/ApiEndpointService.cls.php', 'detail' => 'Executes endpoint/service payload and transforms output.'],
                        ['id' => 'response', 'label' => 'JSON Response', 'file' => 'rad/core/sys/View.cls.php', 'detail' => 'Returns JSON response payload with status/alerts.'],
                    ],
                    'edges' => [
                        ['from' => 'boot', 'to' => 'request'],
                        ['from' => 'request', 'to' => 'auth'],
                        ['from' => 'auth', 'to' => 'dispatch'],
                        ['from' => 'dispatch', 'to' => 'service'],
                        ['from' => 'service', 'to' => 'response'],
                    ],
                ],
                [
                    'id' => 'admin',
                    'label' => 'RAD Admin Request',
                    'note' => 'Admin request through RadAdminController module-event rendering.',
                    'steps' => [
                        ['id' => 'boot', 'label' => 'Bootstrap', 'file' => 'public_html/index.php', 'detail' => 'Initializes runData and resolves /rad-admin path.'],
                        ['id' => 'request', 'label' => 'Request Context', 'file' => 'rad/core/sys/Request.cls.php', 'detail' => 'Loads admin path parts and request metadata.'],
                        ['id' => 'adminctrl', 'label' => 'Admin Controller', 'file' => 'rad/core/sys/RadAdminController.cls.php', 'detail' => 'Checks admin access, resolves module + method.'],
                        ['id' => 'module', 'label' => 'Module Method', 'file' => 'rad/admin/classes/<Module>.cls.php', 'detail' => 'Runs module method (view/add/edit/etc.) and prepares runData.'],
                        ['id' => 'template', 'label' => 'Admin UI Include', 'file' => 'rad/admin/ui/<module>-<method>.html.php', 'detail' => 'Loads page part inside RAD Admin template.'],
                        ['id' => 'render', 'label' => 'Render', 'file' => 'rad/admin/rad-admin.tpl.php', 'detail' => 'Outputs final admin layout, nav, alerts, and page content.'],
                    ],
                    'edges' => [
                        ['from' => 'boot', 'to' => 'request'],
                        ['from' => 'request', 'to' => 'adminctrl'],
                        ['from' => 'adminctrl', 'to' => 'module'],
                        ['from' => 'module', 'to' => 'template'],
                        ['from' => 'template', 'to' => 'render'],
                    ],
                ],
            ],
        ];

        $toolLinks = [
            'public_html/index.php' => [
                'tool' => ['label' => 'Find bootstrap code', 'query' => 'index.php'],
                'docs' => ['label' => 'Open Technical Docs', 'path' => '/techdocs/view'],
            ],
            'rad/core/sys/Request.cls.php' => [
                'tool' => ['label' => 'Find Request class', 'query' => 'Request.cls.php'],
                'docs' => ['label' => 'Open Technical Docs', 'path' => '/techdocs/view'],
            ],
            'rad/core/sys/SessionManager.cls.php' => [
                'tool' => ['label' => 'Find Session manager', 'query' => 'SessionManager.cls.php'],
                'docs' => ['label' => 'Open Access Control Docs', 'path' => '/techdocs/accesscontrol'],
            ],
            'rad/core/sys/GenericController.cls.php' => [
                'tool' => ['label' => 'Find GenericController', 'query' => 'GenericController.cls.php'],
                'docs' => ['label' => 'Open Technical Docs', 'path' => '/techdocs/view'],
            ],
            'rad/core/sys/View.cls.php' => [
                'tool' => ['label' => 'Find View renderer', 'query' => 'View.cls.php'],
                'docs' => ['label' => 'Open Technical Docs', 'path' => '/techdocs/view'],
            ],
            'rad/core/sys/ApiController.cls.php' => [
                'tool' => ['label' => 'Find ApiController', 'query' => 'ApiController.cls.php'],
                'docs' => ['label' => 'Open API Docs', 'path' => '/apiendpoint/docs'],
            ],
            'rad/core/sys/ApiEndpointService.cls.php' => [
                'tool' => ['label' => 'Find ApiEndpointService', 'query' => 'ApiEndpointService.cls.php'],
                'docs' => ['label' => 'Open API Docs', 'path' => '/apiendpoint/docs'],
            ],
            'rad/core/sys/RadAdminController.cls.php' => [
                'tool' => ['label' => 'Find RadAdminController', 'query' => 'RadAdminController.cls.php'],
                'docs' => ['label' => 'Open Technical Docs', 'path' => '/techdocs/view'],
            ],
        ];

        foreach ($seed['modes'] as $mi => $mode) {
            if (empty($mode['steps']) || !is_array($mode['steps'])) {
                continue;
            }
            foreach ($mode['steps'] as $si => $step) {
                $file = (string)($step['file'] ?? '');
                if ($file === '' || !isset($toolLinks[$file])) {
                    continue;
                }
                if (!empty($toolLinks[$file]['tool']['query'])) {
                    $seed['modes'][$mi]['steps'][$si]['tool'] = [
                        'label' => $toolLinks[$file]['tool']['label'] ?? 'Open related tool',
                        'path' => '/observability/findcode?q=' . rawurlencode((string)$toolLinks[$file]['tool']['query']),
                    ];
                }
                if (!empty($toolLinks[$file]['docs']['path'])) {
                    $seed['modes'][$mi]['steps'][$si]['docs'] = [
                        'label' => $toolLinks[$file]['docs']['label'] ?? 'Open docs',
                        'path' => (string)$toolLinks[$file]['docs']['path'],
                    ];
                }
            }
        }
        return $seed;
    }

    private function buildNavmapData(): array {
        if (!$this->db) {
            return [
                'nodes' => [],
                'edges' => [],
                'summary' => [
                    'node_total' => 0,
                    'edge_total' => 0,
                    'counts' => ['navset' => 0, 'navitem' => 0, 'role' => 0, 'object' => 0],
                ],
                'generated_at' => gmdate('c'),
            ];
        }

        $navsets = [];
        $navItems = [];
        $navsetRoles = [];
        $roles = [];
        $bindings = [];
        $microservices = [];
        $routes = [];
        $nodes = [];
        $edges = [];

        try {
            $navsets = $this->db->query("SELECT id, uid, s_name FROM s_navset WHERE livestatus = '1' ORDER BY id LIMIT 300");
            $navItems = $this->db->query("SELECT id, uid, s_navset_id, s_name, s_href, s_parent_nav_id FROM s_nav WHERE livestatus = '1' ORDER BY s_navset_id, s_sort_order, id LIMIT 1200");
            $navsetRoles = $this->db->query("SELECT id, s_navset_id, s_role_id, s_ms_id FROM s_navset_role WHERE livestatus = '1' ORDER BY id LIMIT 1500");
            $roles = $this->db->query("SELECT id, uid, s_role_name, s_scope FROM s_role WHERE livestatus = '1' ORDER BY id LIMIT 350");
            $bindings = $this->db->query("SELECT id, s_object_type, s_object_id, s_role_id FROM s_permission_binding WHERE livestatus = '1' ORDER BY id LIMIT 1600");
            $microservices = $this->db->query("SELECT id, uid, s_name FROM s_ms WHERE livestatus = '1' ORDER BY id LIMIT 500");
            $routes = $this->db->query("SELECT id, uid, s_name FROM s_msroute WHERE livestatus = '1' ORDER BY id LIMIT 1000");
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }

        $navsets = is_array($navsets) ? $navsets : [];
        $navItems = is_array($navItems) ? $navItems : [];
        $navsetRoles = is_array($navsetRoles) ? $navsetRoles : [];
        $roles = is_array($roles) ? $roles : [];
        $bindings = is_array($bindings) ? $bindings : [];
        $microservices = is_array($microservices) ? $microservices : [];
        $routes = is_array($routes) ? $routes : [];

        $msNodeById = [];
        foreach ($microservices as $ms) {
            $id = (int)($ms['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $nodeId = 'obj-ms-' . $id;
            $msNodeById[$id] = $nodeId;
            $nodes[] = [
                'id' => $nodeId,
                'label' => (string)($ms['s_name'] ?? ('MS ' . $id)),
                'type' => 'object',
                'meta' => [
                    'object_type' => 'ms',
                    'uid' => (string)($ms['uid'] ?? ''),
                    'db_id' => $id,
                    'detail_path' => '/microservice/detail/' . (string)($ms['uid'] ?? ''),
                ],
            ];
        }

        foreach ($routes as $route) {
            $id = (int)($route['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $nodes[] = [
                'id' => 'obj-route-' . $id,
                'label' => (string)($route['s_name'] ?? ('Route ' . $id)),
                'type' => 'object',
                'meta' => [
                    'object_type' => 'route',
                    'uid' => (string)($route['uid'] ?? ''),
                    'db_id' => $id,
                    'detail_path' => '/route/detail/' . (string)($route['uid'] ?? ''),
                ],
            ];
        }

        foreach ($roles as $role) {
            $id = (int)($role['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $nodes[] = [
                'id' => 'role-' . $id,
                'label' => (string)($role['s_role_name'] ?? ('Role ' . $id)),
                'type' => 'role',
                'meta' => [
                    'uid' => (string)($role['uid'] ?? ''),
                    'db_id' => $id,
                    'scope' => (string)($role['s_scope'] ?? ''),
                    'detail_path' => '/role/viewone/' . (string)($role['uid'] ?? ''),
                ],
            ];
        }

        foreach ($navsets as $navset) {
            $id = (int)($navset['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $navsetNodeId = 'navset-' . $id;
            $nodes[] = [
                'id' => $navsetNodeId,
                'label' => (string)($navset['s_name'] ?? ('Navset ' . $id)),
                'type' => 'navset',
                'meta' => [
                    'uid' => (string)($navset['uid'] ?? ''),
                    'db_id' => $id,
                ],
            ];
        }

        foreach ($navItems as $item) {
            $id = (int)($item['id'] ?? 0);
            $navsetId = (int)($item['s_navset_id'] ?? 0);
            if ($id <= 0 || $navsetId <= 0) {
                continue;
            }
            $itemNodeId = 'navitem-' . $id;
            $nodes[] = [
                'id' => $itemNodeId,
                'label' => (string)($item['s_name'] ?? ('Nav item ' . $id)),
                'type' => 'navitem',
                'meta' => [
                    'uid' => (string)($item['uid'] ?? ''),
                    'db_id' => $id,
                    'href' => (string)($item['s_href'] ?? ''),
                    'parent_nav_id' => (int)($item['s_parent_nav_id'] ?? 0),
                    'navset_id' => $navsetId,
                ],
            ];

            $edges[] = [
                'from' => 'navset-' . $navsetId,
                'to' => $itemNodeId,
                'label' => 'contains',
                'meta' => ['relation' => 'contains'],
            ];

            $parentNav = (int)($item['s_parent_nav_id'] ?? 0);
            if ($parentNav > 0) {
                $edges[] = [
                    'from' => 'navitem-' . $parentNav,
                    'to' => $itemNodeId,
                    'label' => 'parent_of',
                    'meta' => ['relation' => 'parent_of'],
                ];
            }
        }

        foreach ($navsetRoles as $row) {
            $navsetId = (int)($row['s_navset_id'] ?? 0);
            $roleId = (int)($row['s_role_id'] ?? 0);
            if ($navsetId <= 0 || $roleId <= 0) {
                continue;
            }
            $edges[] = [
                'from' => 'navset-' . $navsetId,
                'to' => 'role-' . $roleId,
                'label' => 'nav_role',
                'meta' => ['relation' => 'nav_role'],
            ];

            $msId = (int)($row['s_ms_id'] ?? 0);
            if ($msId > 0 && isset($msNodeById[$msId])) {
                $edges[] = [
                    'from' => 'navset-' . $navsetId,
                    'to' => $msNodeById[$msId],
                    'label' => 'scoped_to',
                    'meta' => ['relation' => 'scoped_to'],
                ];
            }
        }

        foreach ($bindings as $binding) {
            $roleId = (int)($binding['s_role_id'] ?? 0);
            $objType = (string)($binding['s_object_type'] ?? '');
            $objId = (int)($binding['s_object_id'] ?? 0);
            if ($roleId <= 0 || $objId <= 0 || ($objType !== 'ms' && $objType !== 'route')) {
                continue;
            }
            $edges[] = [
                'from' => 'role-' . $roleId,
                'to' => 'obj-' . $objType . '-' . $objId,
                'label' => 'bound_to',
                'meta' => ['relation' => 'bound_to'],
            ];
        }

        $seenNodes = [];
        $uniqueNodes = [];
        $counts = ['navset' => 0, 'navitem' => 0, 'role' => 0, 'object' => 0];
        $nodeMap = [];
        foreach ($nodes as $node) {
            $id = (string)($node['id'] ?? '');
            if ($id === '' || isset($seenNodes[$id])) {
                continue;
            }
            $seenNodes[$id] = true;
            $uniqueNodes[] = $node;
            $nodeMap[$id] = $node;
            $type = (string)($node['type'] ?? '');
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        $seenEdges = [];
        $validEdges = [];
        foreach ($edges as $edge) {
            $from = (string)($edge['from'] ?? '');
            $to = (string)($edge['to'] ?? '');
            if ($from === '' || $to === '' || !isset($nodeMap[$from]) || !isset($nodeMap[$to])) {
                continue;
            }
            $key = $from . '|' . $to . '|' . (string)($edge['label'] ?? '');
            if (isset($seenEdges[$key])) {
                continue;
            }
            $seenEdges[$key] = true;
            $validEdges[] = $edge;
        }

        return [
            'nodes' => $uniqueNodes,
            'edges' => $validEdges,
            'summary' => [
                'node_total' => count($uniqueNodes),
                'edge_total' => count($validEdges),
                'counts' => $counts,
            ],
            'generated_at' => gmdate('c'),
        ];
    }

    private function normalizeDiagramToken(string $value): string {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/[^a-z0-9]/', '', $value);
        return is_string($value) ? $value : '';
    }

    private function scanClasses(): array {
        $appDir = rtrim($this->runData['config']['dir']['app'] ?? '', '/');
        if ($appDir === '' || !is_dir($appDir)) {
            $coreDir = rtrim($this->runData['config']['dir']['core'] ?? '', '/');
            if ($coreDir !== '') {
                $appDir = $coreDir . '/app';
            }
        }
        if ($appDir === '' || !is_dir($appDir)) {
            return [];
        }

        $files = glob($appDir . '/*.cls.php') ?: [];
        $classes = [];

        foreach ($files as $file) {
            try {
                $info = $this->reflectFile($file);
                if ($info !== null) {
                    $classes[] = $info;
                }
            } catch (\Throwable $e) {
                if ($this->errorHandler) {
                    $this->errorHandler->handleException($e);
                }
            }
        }

        usort($classes, function ($a, $b) {
            return strcmp($a['short_name'], $b['short_name']);
        });

        return $classes;
    }

    private function reflectFile(string $file): ?array {
        $before = get_declared_classes();
        $newClasses = [];
        try {
            require_once $file;
            $after = get_declared_classes();
            $newClasses = array_diff($after, $before);
        } catch (\Throwable $e) {
            // ignore load errors for individual files
            return null;
        }

        foreach ($newClasses as $class) {
            $ref = new \ReflectionClass($class);
            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }
            if (strpos($ref->getNamespaceName(), 'Core\\App') !== 0) {
                continue;
            }
            $classDoc = $this->normalizeDoc($ref->getDocComment());
            $methods = [];
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->getDeclaringClass()->getName() !== $ref->getName()) {
                    continue; // skip inherited
                }
                $rawDoc = $m->getDocComment();
                $paramDocs = $this->parseParamDocs($rawDoc);
                $methods[] = [
                    'name' => $m->getName(),
                    'signature' => $this->formatMethod($m),
                    'doc' => $this->normalizeDoc($rawDoc),
                    'params' => $this->formatParams($m, $paramDocs),
                    'return' => $this->parseReturnDoc($rawDoc, $m->getReturnType()),
                ];
            }
            return [
                'name' => $ref->getName(),
                'short_name' => $ref->getShortName(),
                'file' => $file,
                'methods' => $methods,
                'namespace' => $ref->getNamespaceName(),
                'doc' => $classDoc,
            ];
        }
        return null;
    }

    private function formatMethod(\ReflectionMethod $m): string {
        $parts = [];
        foreach ($m->getParameters() as $p) {
            $param = '';
            if ($p->hasType()) {
                $param .= $p->getType() . ' ';
            }
            $param .= '$' . $p->getName();
            if ($p->isOptional()) {
                $param .= ' = ...';
            }
            $parts[] = $param;
        }
        return $m->getName() . '(' . implode(', ', $parts) . ')';
    }

    private function formatParams(\ReflectionMethod $m, array $docMap = []): array {
        $out = [];
        foreach ($m->getParameters() as $p) {
            $type = $p->hasType() ? (string)$p->getType() : '';
            $default = $p->isOptional() && $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
            $desc = $docMap[$p->getName()] ?? '';
            $out[] = [
                'name' => '$' . $p->getName(),
                'type' => $type,
                'optional' => $p->isOptional(),
                'default' => $default,
                'desc' => $desc,
            ];
        }
        return $out;
    }

    private function normalizeDoc($doc): string {
        if (!$doc || !is_string($doc)) {
            return '';
        }
        $doc = preg_replace('#^/\*\*|\*/$#', '', $doc);
        $lines = preg_split('/\R/', $doc);
        $clean = [];
        foreach ($lines as $line) {
            $line = preg_replace('/^\s*\*\s?/', '', $line);
            $clean[] = rtrim($line);
        }
        $text = trim(implode("\n", $clean));
        return $text;
    }

    private function parseParamDocs($doc): array {
        if (!$doc || !is_string($doc)) {
            return [];
        }
        $map = [];
        if (preg_match_all('/@param\\s+[^\\s]+\\s+\\$?(\\w+)\\s*(.*)$/mi', $doc, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $map[$m[1]] = trim($m[2]);
            }
        }
        return $map;
    }

    private function parseReturnDoc($doc, ?\ReflectionType $type): array {
        $typeStr = $type ? (string)$type : '';
        $desc = '';
        if ($doc && is_string($doc) && preg_match('/@return\\s+([^\\s]+)\\s*(.*)$/mi', $doc, $m)) {
            $typeStr = $typeStr ?: trim($m[1]);
            $desc = trim($m[2]);
        }
        return [
            'type' => $typeStr ?: 'mixed',
            'desc' => $desc,
        ];
    }
}
