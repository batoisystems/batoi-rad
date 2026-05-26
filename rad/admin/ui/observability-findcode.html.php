<?php
$query = $this->runData['data']['query'] ?? '';
$caseSensitive = !empty($this->runData['data']['case_sensitive']);
$scopes = $this->runData['data']['scopes'] ?? ['ms' => true, 'theme' => true, 'assets' => true];
$results = $this->runData['data']['results'] ?? [];
$stats = $this->runData['data']['stats'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';

$highlightMatch = static function ($text, $query, $caseSensitive) {
    if ($query === '') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $pattern = '/' . preg_quote($query, '/') . '/' . ($caseSensitive ? '' : 'i');
    return preg_replace($pattern, '<mark>$0</mark>', $escaped);
};

$formatDuration = static function ($ms) {
    if ($ms >= 1000) {
        return number_format($ms / 1000, 2) . 's';
    }
    return number_format($ms) . 'ms';
};
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h4 class="mb-1">Find Code</h4>
            <p class="mb-0 text-muted">Search across microservicelets, theme templates, and asset files. Results include direct edit links when available.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            <a href="<?php echo $radAdminUrl; ?>/observability/findcode" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-lg-6">
                <label class="form-label small text-muted">Search query</label>
                <input type="text" name="q" class="form-control" placeholder="e.g. workspace_slug_prefix, data-binding, .alert" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label small text-muted">Scope</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="scope_ms" id="scope_ms" value="1" <?php echo !empty($scopes['ms']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="scope_ms">Microservicelets</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="scope_theme" id="scope_theme" value="1" <?php echo !empty($scopes['theme']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="scope_theme">Theme templates</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="scope_assets" id="scope_assets" value="1" <?php echo !empty($scopes['assets']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="scope_assets">Assets (.css/.js)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="cs" id="case_sensitive" value="1" <?php echo $caseSensitive ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="case_sensitive">Case sensitive</label>
                    </div>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                <?php if ($query !== '') { ?>
                    <a href="<?php echo $radAdminUrl; ?>/observability/findcode" class="btn btn-outline-secondary">Clear</a>
                <?php } ?>
            </div>
        </form>
    </div>
</div>

<?php if ($query !== '') { ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                <div>
                    <div class="fw-semibold">Results summary</div>
                    <div class="text-muted small">
                        <?php echo number_format($stats['matches'] ?? 0); ?> matches across
                        <?php echo number_format($stats['files_with_matches'] ?? 0); ?> files
                        (scanned <?php echo number_format($stats['files_scanned'] ?? 0); ?> files in <?php echo $formatDuration($stats['duration_ms'] ?? 0); ?>).
                    </div>
                </div>
                <?php if (!empty($stats['limit_reached'])) { ?>
                    <span class="badge bg-warning text-dark">Limit reached</span>
                <?php } ?>
            </div>
        </div>
    </div>

    <?php if (empty($results)) { ?>
        <div class="alert alert-info">No matches found for this query.</div>
    <?php } else { ?>
        <?php foreach ($results as $file) { ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                        <div>
                            <div class="fw-semibold">
                                <?php echo htmlspecialchars($file['relative'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="text-muted small">
                                <?php echo htmlspecialchars($file['type'] ?? 'file', ENT_QUOTES, 'UTF-8'); ?> ·
                                <?php echo count($file['matches'] ?? []); ?> match<?php echo count($file['matches'] ?? []) === 1 ? '' : 'es'; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <?php if (!empty($file['edit_url'])) { ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($file['edit_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                    <?php echo htmlspecialchars($file['edit_label'] ?? 'Edit', ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php } else { ?>
                                <span class="badge bg-light text-muted border">Edit link unavailable</span>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="text-muted small">
                                <tr>
                                    <th style="width: 90px;">Line</th>
                                    <th>Preview</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($file['matches'] ?? []) as $match) { ?>
                                    <tr>
                                        <td class="text-muted">#<?php echo (int)($match['line'] ?? 0); ?></td>
                                        <td class="font-monospace small"><?php echo $highlightMatch($match['text'] ?? '', $query, $caseSensitive); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
<?php } ?>
