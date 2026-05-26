<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = $active ?? '';
$links = [
    'overview' => ['label' => 'Overview', 'path' => '/profile/overview'],
    'mfa' => ['label' => 'MFA', 'path' => '/profile/mfa'],
    'changepwd' => ['label' => 'Change Password', 'path' => '/profile/changepwd'],
    'sessions' => ['label' => 'Sessions', 'path' => '/profile/sessions'],
    'preferences' => ['label' => 'Preferences', 'path' => '/profile/preferences'],
    'notifications' => ['label' => 'Notifications', 'path' => '/profile/notifications'],
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
