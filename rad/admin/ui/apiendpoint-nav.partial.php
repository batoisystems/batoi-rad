<?php
$apiNavActive = $apiNavActive ?? 'overview';
$baseUrl = $this->runData['route']['rad_admin_url'] ?? '';
$tabs = [
    'overview' => [
        'label' => 'Overview',
        'icon' => 'bi-house',
        'url' => $baseUrl . '/apiendpoint/view',
    ],
    'docs' => [
        'label' => 'Docs',
        'icon' => 'bi-journal-text',
        'url' => $baseUrl . '/apiendpoint/docs',
    ],
    'verify' => [
        'label' => 'Verify',
        'icon' => 'bi-activity',
        'url' => $baseUrl . '/apiendpoint/verify',
    ],
    'endpoints' => [
        'label' => 'Named Endpoints',
        'icon' => 'bi-hdd-network',
        'url' => $baseUrl . '/apiendpoint/endpoints',
    ],
    'catalog' => [
        'label' => 'System Catalog',
        'icon' => 'bi-collection',
        'url' => $baseUrl . '/apiendpoint/services',
    ],
];
?>
<div class="mb-4">
    <ul class="nav nav-pills gap-2 flex-wrap">
        <?php foreach ($tabs as $key => $tab) { ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $apiNavActive === $key ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tab['url']); ?>">
                    <i class="bi <?php echo $tab['icon']; ?> me-1"></i><?php echo htmlspecialchars($tab['label']); ?>
                </a>
            </li>
        <?php } ?>
    </ul>
</div>
