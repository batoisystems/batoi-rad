<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <p class="text-muted mb-0">Reference for how access control is enforced across users, APIs, workspaces, microservicelets, routes, and navigation.</p>
        </div>
        <div class="ms-lg-auto d-flex gap-2 flex-wrap">
            <a href="<?php echo $radAdminUrl; ?>/techdocs/view" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Tech Docs</a>
            <a href="<?php echo $radAdminUrl; ?>/devguide/appclasses" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-code me-1"></i>Application Classes</a>
            <a href="<?php echo $radAdminUrl; ?>/techdocs/aclreport" class="btn btn-outline-primary btn-sm"><i class="bi bi-clipboard-check me-1"></i>Access Control Report</a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Quick Reference</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:20%">Category</th>
                        <th>Definition / Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row">Role scope</th>
                        <td>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Non-SaaS roles</strong> (scope = <code>platform</code>): primary roles for users; used for non-SaaS access. One required per user.<br>
                                    <strong>SaaS roles</strong> (scope = <code>workspace</code>): assigned via workspace memberships.
                                </div>
                                <div class="col-md-6">
                                    <strong>Portal role</strong>: deprecated; use <code>s_nonsaas_role_id</code> on <code>s_entity</code>.
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Microservicelet scope</th>
                        <td>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>platform</strong>: non-SaaS; no space UID required.<br>
                                    <strong>workspace</strong>: SaaS; space UID segment is mandatory in routes (regardless of user, including superuser).
                                </div>
                                <div class="col-md-6">
                                    <strong>global</strong>: treated as public (maps to access_scope = public). All other scopes imply private + binding checks.
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Principal types</th>
                        <td>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>User</strong> (<code>s_entity</code>, <code>s_type='U'</code>): has primary role_id + memberships.<br>
                                    <strong>API Key</strong> (<code>s_type='A'</code>): auth info stores IP limits and allowed types/services (no role_type).
                                </div>
                                <div class="col-md-6">
                                    <strong>Roles</strong>: primary role on user plus workspace memberships for SaaS scope.
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Memberships</th>
                        <td>
                            <strong>s_space_membership</strong>: links a principal (user/team) to a workspace.<br>
                            <strong>s_space_membership</strong> (role fields): assigns a role/scope to the membership (one SaaS role per workspace).
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bindings (authoritative)</th>
                        <td>
                            <strong>s_permission_binding</strong>: <code>s_object_type</code> = <code>ms</code> or <code>route</code>; access level is stored as <code>use</code> (legacy view/use/admin collapsed).<br>
                            Bindings are authoritative; legacy CSV access fields have been removed.
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Superuser</th>
                        <td><strong>entity_id = 1</strong>: bypasses permission checks but still requires a space UID on SaaS routes.</td>
                    </tr>
                    <tr>
                        <th scope="row">SaaS routing</th>
                        <td>Workspace scope routes require a space identifier: UID/ID use space UID as the 3rd segment; STA uses space slug as the 3rd segment; DYN uses space slug as the 1st segment (/{space_slug}/{ms_name}/{route_name}/...). Missing identifier renders a workspace-required error (superuser included).</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h5 class="fw-semibold">Request-time enforcement</h5>
        <ul class="mb-4">
            <li><strong>GenericController</strong>: Uses <code>PermissionService::canAccess</code> against <code>s_permission_binding</code>; legacy CSV is ignored.</li>
            <li><strong>Space binding (SaaS)</strong>: Requires space identifier (UID for UID/ID; slug for STA; slug as first segment for DYN). Membership roles resolve via <code>PermissionService</code>; superuser still needs the identifier but bypasses membership.</li>
            <li><strong>Nav</strong>: Access evaluated via bindings (nav object type) when present.</li>
        </ul>

        <h5 class="fw-semibold">Data flow & storage</h5>
        <ul class="mb-4">
            <li><strong>User primary role</strong>: Stored as <code>s_nonsaas_role_id</code> in <code>s_entity</code>; workspace roles live in membership tables (user-level <code>role_type</code> is deprecated).</li>
            <li><strong>API keys</strong>: Stored in <code>s_entity</code> (<code>s_type='A'</code>); auth info carries IP limits and allowed types/services.</li>
            <li><strong>Bindings</strong>: Keep bindings on all private ms/routes/nav items; CSV fields may remain in data but are ignored at runtime.</li>
        </ul>

        <h5 class="fw-semibold">How to grant access (RAD Admin)</h5>
        <ol class="mb-4">
            <li>Create roles with correct <code>s_scope</code> (platform = non-SaaS; workspace = SaaS) and default route if needed.</li>
            <li>Assign users a primary non-SaaS role; for SaaS access, add memberships with SaaS roles (one per workspace).</li>
            <li>Add permission bindings on microservicelets (<code>ms</code>) and routes (<code>route</code>) with the desired roles and access level (use/admin). For navigation gating, add bindings on <code>nav</code> objects.</li>
            <li>For SaaS routes, ensure URLs carry the space UID segment; missing UID renders a workspace-required error.</li>
        </ol>

        <h5 class="fw-semibold">Troubleshooting tips</h5>
        <ul class="mb-0">
            <li>If access is denied, inspect <code>s_permission_binding</code> (authoritative).</li>
            <li>Ensure memberships exist for the space and include the intended SaaS role; superuser is only <code>entity_id=1</code>.</li>
            <li>Verify <code>s_role.s_scope</code> is set; latest upgrade normalizes invalid values to platform.</li>
            <li>For SaaS routes, confirm the space UID segment is present.</li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">RBAC Matrix</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small">Scopes vs. objects. Superuser (entity_id=1) bypasses checks but still needs space UID on SaaS routes.</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Scope</th>
                        <th>Microservicelet (ms)</th>
                        <th>Route</th>
                        <th>Navigation</th>
                        <th>Workspace</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="text-nowrap">Platform (Non-SaaS)</th>
                        <td>Private ms requires bindings with platform roles; public ms open.</td>
                        <td>Private routes require bindings with platform roles; default route redirects if no path.</td>
                        <td>Bindings on nav items enforce platform roles.</td>
                        <td>N/A (workspace is SaaS).</td>
                    </tr>
                    <tr>
                        <th class="text-nowrap">Workspace / App / Member Org (SaaS)</th>
                        <td>SaaS ms requires space UID and bindings; roles must be SaaS-scoped.</td>
                        <td>SaaS routes require space UID; bindings evaluated with workspace role.</td>
                        <td>Nav bindings can include SaaS roles; space context may apply in app UX.</td>
                        <td>Workspace memberships hold SaaS roles (one per workspace).</td>
                    </tr>
                    <tr>
                        <th class="text-nowrap">Superuser (entity_id=1)</th>
                        <td colspan="4">Bypasses permission checks but must include space UID on SaaS routes.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
