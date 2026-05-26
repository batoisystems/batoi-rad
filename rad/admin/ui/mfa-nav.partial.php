<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = $active ?? '';
$links = [
    'dashboard' => ['label' => 'Dashboard', 'path' => '/mfa/dashboard'],
    'policy' => ['label' => 'Policy', 'path' => '/mfa/policy'],
    'channels' => ['label' => 'Channels', 'path' => '/mfa/channels'],
    'providers' => ['label' => 'Providers', 'path' => '/mfa/providers'],
    'security' => ['label' => 'Security', 'path' => '/mfa/security'],
    'ux' => ['label' => 'UX', 'path' => '/mfa/ux'],
];
?>
<div class="card shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap gap-2">
        <?php foreach ($links as $key => $link) { ?>
            <a class="btn btn-sm <?php echo $active === $key ? 'btn-primary' : 'btn-outline-primary'; ?>"
               href="<?php echo $radAdminUrl . $link['path']; ?>">
                <?php echo htmlspecialchars($link['label']); ?>
            </a>
        <?php } ?>
    </div>
</div>
