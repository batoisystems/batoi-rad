<?php
$presets = $this->runData['data']['presets'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <!-- <h2 class="h4 mb-0">SSO Setup Wizard</h2> -->
        <div class="text-muted small">Choose a provider to launch a guided configuration flow.</div>
    </div>
    <a href="<?php echo $radAdminUrl; ?>/sso/view" class="btn btn-outline-secondary">Back to Providers</a>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($presets as $key => $preset) { ?>
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <h3 class="h6 mb-1"><?php echo htmlspecialchars((string)($preset['label'] ?? ucfirst($key))); ?></h3>
                    <p class="small text-muted mb-3"><?php echo htmlspecialchars((string)($preset['description'] ?? '')); ?></p>
                    <div class="alert alert-light border small py-2">
                        This guided setup is designed for non-technical admins. Follow each step in order and copy values exactly.
                    </div>
                    <ul class="small mb-3">
                        <?php foreach (($preset['instructions'] ?? []) as $instruction) { ?>
                            <li><?php echo htmlspecialchars((string)$instruction); ?></li>
                        <?php } ?>
                    </ul>
                    <?php
                    $resources = $preset['resources'] ?? [];
                    $docsUrl = (string)($resources['docs_url'] ?? '');
                    $videoUrl = (string)($resources['video_url'] ?? '');
                    ?>
                    <?php if ($docsUrl !== '' || $videoUrl !== '') { ?>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php if ($docsUrl !== '') { ?>
                                <a href="<?php echo htmlspecialchars($docsUrl); ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Docs</a>
                            <?php } ?>
                            <?php if ($videoUrl !== '') { ?>
                                <a href="<?php echo htmlspecialchars($videoUrl); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Video</a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <div class="mt-auto">
                        <a href="<?php echo $radAdminUrl; ?>/sso/wizard?provider=<?php echo urlencode((string)$key); ?>" class="btn btn-primary btn-sm">Start Wizard</a>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h3 class="h6 mb-2">Need full control?</h3>
        <p class="small text-muted mb-3">Use the advanced form when your provider requires non-standard parameters.</p>
        <a href="<?php echo $radAdminUrl; ?>/sso/add" class="btn btn-outline-primary btn-sm">Open Advanced Setup</a>
    </div>
</div>
