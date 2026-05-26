<?php
$types = $this->runData['data']['field_types'] ?? [];
?>

<?php if (empty($types)) { ?>
    <div class="alert alert-warning mb-0">No data field types found in <code>s_data_field_type</code>. Add definitions to view them here.</div>
<?php } else { ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="text-muted small"><?php echo count($types); ?> field types</div>
        <input type="search" id="typesFilter" class="form-control form-control-sm" placeholder="Filter by name or description" style="max-width:280px;">
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle" id="typesTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Definition</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($types as $row): ?>
                    <tr data-name="<?php echo htmlspecialchars(strtolower(($row['s_name'] ?? '') . ' ' . ($row['s_description'] ?? ''))); ?>">
                        <td class="fw-semibold"><?php echo htmlspecialchars($row['s_name'] ?? ''); ?></td>
                        <td class="text-muted small"><?php echo htmlspecialchars($row['s_description'] ?? ''); ?></td>
                        <td class="small"><code><?php
                            $def = $row['s_definition'] ?? '';
                            $decoded = json_decode($def, true);
                            echo htmlspecialchars($decoded ? json_encode($decoded, JSON_PRETTY_PRINT) : (string)$def);
                        ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('typesFilter');
    const rows = document.querySelectorAll('#typesTable tbody tr');
    if (!input) return;
    input.addEventListener('input', function() {
        const q = (input.value || '').toLowerCase();
        rows.forEach(row => {
            const hay = row.getAttribute('data-name') || '';
            row.classList.toggle('d-none', q && !hay.includes(q));
        });
    });
});
</script>
