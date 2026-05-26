<?php
$sections = $this->runData['data']['rundata_sections'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>

<div id="top"></div>
<div class="row g-3">
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="fw-semibold mb-2">On this page</div>
                <div class="list-group list-group-flush small">
                    <?php foreach ($sections as $section): ?>
                        <a class="list-group-item list-group-item-action px-0" href="#<?php echo htmlspecialchars($section['id']); ?>">
                            <?php echo htmlspecialchars($section['title']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="text-muted small mt-3">
                    All examples use <code>$this->runData</code> access syntax.
                </div>
                <div class="mt-3 small text-muted">
                    Link from Dev Guide: <a href="<?php echo htmlspecialchars($radAdminUrl); ?>/devguide/view">RAD Dev Guide</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <?php foreach ($sections as $section): ?>
            <div class="card mb-3" id="<?php echo htmlspecialchars($section['id']); ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h2 class="h6 mb-1"><?php echo htmlspecialchars($section['title']); ?></h2>
                            <?php if (!empty($section['note'])): ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($section['note']); ?></div>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-outline-secondary btn-sm" href="#top">Top</a>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 28%;">Key</th>
                                    <th style="width: 32%;">Usage</th>
                                    <th>Description</th>
                                    <th style="width: 90px;">Copy</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($section['items'] as $item): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($item['key']); ?></code></td>
                                        <td class="small"><code><?php echo htmlspecialchars($item['code'] ?? ''); ?></code></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($item['desc']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-outline-primary btn-sm rundata-copy" data-key="<?php echo htmlspecialchars($item['code'] ?? $item['key']); ?>">Copy</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.rundata-copy');
    if (!buttons.length) return;
    buttons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const key = btn.getAttribute('data-key') || '';
            try {
                await navigator.clipboard.writeText(key);
                const prev = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(() => { btn.textContent = prev; }, 900);
            } catch (e) {
                btn.textContent = 'Failed';
                setTimeout(() => { btn.textContent = 'Copy'; }, 900);
            }
        });
    });
});
</script>
