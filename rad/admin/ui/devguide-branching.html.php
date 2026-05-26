<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>

<div class="card mb-4" id="governed-release-channels">
    <div class="card-body">
        <h5 class="mb-3">Governed Release Channels (Live/Beta)</h5>
        <p class="mb-2">
            RAD implements <strong>Governed Release Channels</strong> to enable safe development,
            testing, and controlled promotion of application changes without disrupting
            production traffic.
        </p>
        <p class="mb-3">
            This mechanism embeds DevSecOps principles—explicit promotion, isolation,
            auditability, and access control—directly into the RAD runtime.
        </p>
        <div class="row g-3">
            <div class="col-md-6">
                <h6 class="mb-2">Channel Overview</h6>
                <ul class="mb-0">
                    <li><strong>Live (Stable)</strong> — Production channel for all end users</li>
                    <li><strong>Beta (Staging)</strong> — Isolated channel for admin-led development</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="mb-2">Access Control</h6>
                <p class="mb-2">Only System Admins or Access Admins may enter the beta channel. End users always remain on live.</p>
                <div class="small text-muted">Use:</div>
                <pre class="mb-0"><code>?branch=beta</code></pre>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Channel Resolution Rules</div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Live is the default channel.</li>
                    <li>Beta must be explicitly requested.</li>
                    <li>Branch is stored per session.</li>
                    <li>Cache keys include the channel identifier.</li>
                    <li>No runtime artifacts are shared.</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Assets Covered</div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Routes</li>
                    <li>Controllers / Business Classes</li>
                    <li>Content Blocks</li>
                    <li>Data Model Schema</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white fw-semibold">How It Works (Implementation Summary)</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <h6>Routes & Controllers (code)</h6>
                <ul>
                    <li>Live files stay in place (e.g., <code>rad/ms/{ms}/route.{id}.php</code>).</li>
                    <li>Beta files live under <code>rad/ms/{ms}/_beta/</code>.</li>
                    <li>Version history is kept separately for live and beta.</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Content Blocks</h6>
                <ul>
                    <li>Live uses <code>s_content</code> fields directly.</li>
                    <li>Beta is stored inside <code>s_content.s_additional_info.branch_beta</code>.</li>
                    <li>Merge moves beta payload into live fields.</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Data Model Schema (s_data_field + DDL)</h6>
                <ul>
                    <li>Beta schema is stored under <code>s_mscontroller.s_definition.schema_branch_beta</code>.</li>
                    <li>Beta field IDs are negative until merged.</li>
                    <li>Merge applies DDL (add/update/delete) to <code>a_*</code> tables.</li>
                    <li>No live DDL runs while working in beta.</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Branch Events & Audit</h6>
                <ul>
                    <li>All branch actions are logged in <code>s_branch</code>.</li>
                    <li>Events include create, merge, discard, and schema changes.</li>
                    <li>DevSecOps reports can filter on branch events.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white fw-semibold">Promotion Model</div>
    <div class="card-body">
        <p class="mb-0">
            Changes move from Beta to Live only through an explicit
            <strong>Promote (Beta → Live)</strong> action. Beta may also be discarded
            without affecting live.
        </p>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white fw-semibold">Audit and Compliance</div>
    <div class="card-body">
        <p class="mb-0">
            All channel actions are logged and available for audit,
            DevSecOps reporting, and compliance review.
        </p>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white fw-semibold">Where to Manage</div>
    <div class="card-body">
        <ul class="mb-0">
            <li>Routes: <code><?php echo htmlspecialchars($radAdminUrl); ?>/route/edit/{uid}?branch=beta</code></li>
            <li>Controllers: <code><?php echo htmlspecialchars($radAdminUrl); ?>/controller/code/{ms}/{controller}?branch=beta</code></li>
            <li>Content Blocks: <code><?php echo htmlspecialchars($radAdminUrl); ?>/content/edit/{uid}?branch=beta</code></li>
            <li>Schema: <code><?php echo htmlspecialchars($radAdminUrl); ?>/controller/viewschema/{controller_uid}/{ms_uid}?branch=beta</code></li>
        </ul>
    </div>
</div>
