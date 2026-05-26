<?php
$tools = $this->runData['data']['tools'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>

<?php if (empty($tools)) { ?>
    <div class="alert alert-warning mb-0">No tools listed yet.</div>
<?php } else { ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="text-muted small"><?php echo count($tools); ?> tools</div>
        <input type="search" id="toolsFilter" class="form-control form-control-sm" placeholder="Filter tools by name or path" style="max-width:280px;">
    </div>
    <div class="list-group" id="toolsList">
        <?php foreach ($tools as $tool): ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start tool-item" href="<?php echo htmlspecialchars($radAdminUrl . $tool['path']); ?>" data-name="<?php echo htmlspecialchars(strtolower($tool['name'] . ' ' . $tool['path'] . ' ' . $tool['desc'])); ?>">
                <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($tool['name']); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($tool['desc']); ?></div>
                    <div class="text-muted small">Path: <code><?php echo htmlspecialchars($tool['path']); ?></code></div>
                </div>
                <i class="bi bi-arrow-right-short fs-4 text-muted"></i>
            </a>
        <?php endforeach; ?>
    </div>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('toolsFilter');
    const items = document.querySelectorAll('.tool-item');
    if (!input) return;
    input.addEventListener('input', function() {
        const q = (input.value || '').toLowerCase();
        items.forEach(el => {
            const hay = el.getAttribute('data-name') || '';
            el.classList.toggle('d-none', q && !hay.includes(q));
        });
    });
});
</script>
