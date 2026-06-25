<?php $canManage = !empty($this->runData['data']['can_idm_manage']); ?>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-2">
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/view" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-plug-fill me-1"></i>Gateway Overview
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/docs" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i>API Docs
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/verify" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-activity me-1"></i>Verify Payload
        </a>
        <?php if ($canManage) { ?>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/api/add" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Add API Key
            </a>
        <?php } ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $stats = $this->runData['data']['stats'] ?? ['total' => 0, 'saas' => 0, 'non_saas' => 0];
    $statCards = [
        ['label' => 'Active API Keys', 'value' => $stats['total'], 'icon' => 'bi-key', 'class' => 'bg-primary-subtle'],
        ['label' => 'SaaS Accounts', 'value' => $stats['saas'], 'icon' => 'bi-cloud-check', 'class' => 'bg-info-subtle'],
        ['label' => 'Non-SaaS Accounts', 'value' => $stats['non_saas'], 'icon' => 'bi-shield-lock', 'class' => 'bg-success-subtle'],
    ];
    foreach ($statCards as $card) { ?>
        <div class="col-md-4">
            <div class="card h-100 border-0 <?php echo $card['class']; ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi <?php echo $card['icon']; ?> fs-3 text-secondary"></i>
                        </div>
                        <div>
                            <div class="text-muted small"><?php echo $card['label']; ?></div>
                            <div class="fs-4 fw-bold mb-0"><?php echo $card['value']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title mb-2">Managing Keys</h6>
        <ul class="small ps-3 mb-0">
            <li>Each API key maps to an entity of type <code>A</code>. Protect the security key—only share it via secure channels.</li>
            <li>SaaS keys can be restricted to specific Workspaces (spaces). Non-SaaS keys always act globally.</li>
            <li>Define comma-separated <strong>Allowed IPs</strong> to reduce attack surface. Leave blank to allow any origin.</li>
            <li>Rotate secrets regularly. Editing a key and providing a new secret revokes the previous one instantly.</li>
        </ul>
    </div>
</div>

<?php
$numOfApis = count($this->runData['data']['apis']);
if ($numOfApis > 0) {
?>
    <div class="d-flex justify-content-between align-items-center pb-1 border-bottom">
        <p class="pt-3">
            <?php echo 'Total Number of APIs: ' . $numOfApis; ?>
        </p>
        <?php if ($canManage) { ?>
            <div class="btn-group">
                <a href="<?php print $this->runData['route']['rad_admin_url'] . '/api/add'; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-plus-square me-2"></i>Add API</a>
            </div>
        <?php } ?>
    </div>

    <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                UID copied to clipboard.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <div class="rad-table-tools input-group input-group-sm mt-3 mb-2 ms-auto">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control" placeholder="Search API keys" data-uif-table-filter="#apiTable">
    </div>

    <!-- API table -->
    <table 
        class="table table-hover table-bordered table-sm mt-2" 
        id="apiTable"
        data-uif="table"
    >
        <thead>
            <tr>
                <th data-uif-sort="asc">ID</th>
                <th data-uif-sort="asc">API Types</th>
                <th data-uif-sort="asc">UID</th>
                <th data-uif-sort="asc">Name</th>
                <th data-uif-sort="asc">Identity</th>
                <th data-uif-sort="asc">IPs</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($this->runData['data']['apis'] as $api) {
                $authInfo = [
                    'access_ips' => $api['s_access_ips'] ?? '',
                    'api_types' => $api['api_types'] ?? null,
                ];
            ?>
                <tr>
                    <td><?php print $api['id']; ?></td>
                    <td>
                        <?php
                        $apiTypes = $api['api_types'] ?? ($authInfo['api_types'] ?? ['application']);
                        foreach ($apiTypes as $type) {
                            $label = $type === 'system' ? 'System' : 'Application';
                            $badgeClass = $type === 'system' ? 'text-bg-dark' : 'text-bg-primary';
                            echo '<span class="badge '.$badgeClass.' me-1 mb-1">'. $label .'</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php print $api['uid']; ?>
                        <a href="#" class="copy-uid" data-uid="<?php print $api['uid']; ?>">
                            <i class="bi bi-clipboard"></i>
                        </a>
                    </td>
                    <td><?php print $api['s_name']; ?></td>
                    <td>
                        <code><?php print $api['s_identity']; ?></code>
                        <a href="#" class="copy-uid ms-1" data-uid="<?php print $api['s_identity']; ?>">
                            <i class="bi bi-clipboard"></i>
                        </a>
                    </td>
                    <td>
                        <?php if (!empty($authInfo['access_ips'])) {
                            $ips = explode(',', $authInfo['access_ips']);
                            foreach ($ips as $ip) {
                                echo '<span class="badge text-bg-light me-1 mb-1">'.htmlspecialchars(trim($ip)).'</span>';
                            }
                        } else {
                            echo 'Any';
                        } ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <!-- <a href="<?php print $this->runData['route']['rad_admin_url'] . '/api/viewone/' . $api['uid']; ?>" class="btn btn-outline-primary"><i class="bi bi-eye"></i></a> -->
                            <a href="<?php print $this->runData['route']['rad_admin_url'] . '/api/edit/' . $api['uid']; ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                            <a href="<?php print $this->runData['route']['rad_admin_url'] . '/api/archive/' . $api['uid']; ?>" class="btn btn-outline-danger"><i class="bi bi-archive"></i></a>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php
} else {
?>
    <div class="text-center mb-5">
        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 12rem;"></i>
        <p class="lead">No API available. Please add an API using the <strong>Add API</strong> function.</p>
        <a href="<?php print $this->runData['route']['rad_admin_url'] . '/api/add'; ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-square me-2"></i>Add API</a>
    </div>
<?php
}
?>
