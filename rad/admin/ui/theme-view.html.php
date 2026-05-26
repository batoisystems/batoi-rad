<?php
$tplDir = $this->runData['config']['dir']['theme'];
$rawFiles = array_values(array_filter(scandir($tplDir), function ($item) {
    return substr($item, -8) === '.tpl.php';
}));
$templates = [];
$latestFile = null;
$latestTime = 0;
foreach ($rawFiles as $file) {
    $path = $tplDir . '/' . $file;
    $name = substr($file, 0, -8);
    $size = filesize($path);
    $modified = filemtime($path);
    $templates[] = [
        'file' => $file,
        'name' => $name,
        'size' => $size,
        'modified' => $modified,
    ];
    if ($modified > $latestTime) {
        $latestTime = $modified;
        $latestFile = $file;
    }
}
$filters = $this->runData['data']['filters'] ?? ['q' => ''];
if (!empty($filters['q'])) {
    $needle = strtolower($filters['q']);
    $templates = array_values(array_filter($templates, function ($tpl) use ($needle) {
        $blob = strtolower(($tpl['file'] ?? '') . ' ' . ($tpl['name'] ?? ''));
        return strpos($blob, $needle) !== false;
    }));
}
$totalTemplates = count($templates);
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$humanLatest = $latestTime ? \Core\Sys\TimeHelper::formatUtc($latestTime, $timezone) : 'NA';
$filters = $this->runData['data']['filters'] ?? ['q' => ''];
?>
<div class="apex-menucards">
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="btn-toolbar rad-stacked-toolbar" role="toolbar">
                        <div class="btn-group" role="group" aria-label="Theme actions">
                            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/theme/add" class="btn btn-primary text-white">
                                <i class="bi bi-plus-circle me-2"></i>Add Template
                            </a>
                            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/uiassets/view" class="btn btn-outline-primary">
                                <i class="bi bi-images me-2"></i>Theme Assets
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100 border-0 bg-primary-subtle">
                        <div class="card-body">
                            <div class="text-muted small">Templates</div>
                            <div class="fs-3 fw-bold"><?php echo $totalTemplates; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 border-0 bg-info-subtle">
                        <div class="card-body">
                            <div class="text-muted small">Most Recent</div>
                            <div class="fw-semibold"><?php echo $latestFile ? htmlspecialchars($latestFile) : 'NA'; ?></div>
                            <div class="small text-muted"><?php echo $humanLatest; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Template Files</h5>
                            <div class="text-muted small">Select a template to review insights and open the editor from its detail page.</div>
                        </div>
                    </div>
                    <form class="row g-2 align-items-end mb-3" method="get">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label small text-muted mb-1">Search by name</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="template-filter" name="q" placeholder="Filter templates" value="<?php echo htmlspecialchars($filters['q']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i> Apply</button>
                            <?php if ($filters['q'] !== '') { ?>
                                <a class="btn btn-outline-secondary btn-sm flex-grow-1" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/theme/view">Reset</a>
                            <?php } ?>
                        </div>
                    </form>
                    <?php if ($totalTemplates === 0) { ?>
                        <div class="alert alert-warning mb-0">No template files found. Use “Add Template” to create the first one.</div>
                    <?php } else { ?>
                    <div class="row" id="template-grid">
                        <?php foreach ($templates as $tpl) {
                            $insightUrl = $this->runData['route']['rad_admin_url'] . '/theme/viewone/' . urlencode($tpl['name']);
                        ?>
                            <div class="col-md-4 mb-4 template-card" data-template="<?php echo htmlspecialchars($tpl['file']); ?>">
                                <div class="card text-center h-100">
                                    <div class="card-body position-relative">
                                        <a href="<?php echo $insightUrl; ?>" class="stretched-link"></a>
                                        <i class="bi bi-file-earmark-text display-5 text-primary mb-3"></i>
                                        <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($tpl['file']); ?></p>
                                        <div class="small text-muted">
                                            <?php echo round($tpl['size'] / 1024, 1); ?> KB ·
                                            <?php echo \Core\Sys\TimeHelper::formatUtc($tpl['modified'], $timezone); ?>
                                        </div>
                                        <div class="text-primary small mt-3">View details →</div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script>
(function() {
    var filterInput = document.getElementById('template-filter');
    if (!filterInput) return;
    var cards = document.querySelectorAll('.template-card');
    filterInput.addEventListener('input', function() {
        var query = this.value.toLowerCase();
        cards.forEach(function(card) {
            var filename = card.getAttribute('data-template').toLowerCase();
            card.style.display = filename.indexOf(query) !== -1 ? '' : 'none';
        });
    });
})();
</script>
