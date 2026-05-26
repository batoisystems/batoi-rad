<?php
$sections = $this->runData['data']['cache_sections'] ?? [];
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

                    <?php if (!empty($section['bullets'])): ?>
                        <ul class="text-muted small mt-3 mb-0">
                            <?php foreach ($section['bullets'] as $bullet): ?>
                                <li><?php echo htmlspecialchars($bullet); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($section['examples'])): ?>
                        <div class="mt-3">
                            <?php foreach ($section['examples'] as $example): ?>
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="fw-semibold small"><?php echo htmlspecialchars($example['label']); ?></div>
                                        <button type="button" class="btn btn-outline-primary btn-sm cache-copy" data-copy="<?php echo htmlspecialchars($example['code']); ?>">Copy</button>
                                    </div>
                                    <pre class="mb-0"><code><?php echo htmlspecialchars($example['code']); ?></code></pre>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['rows'])): ?>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 22%;">Handle</th>
                                        <th>Description</th>
                                        <th style="width: 28%;">Example</th>
                                        <th style="width: 90px;">Copy</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($section['rows'] as $row): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($row['handle']); ?></code></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($row['desc']); ?></td>
                                            <td class="small"><code><?php echo htmlspecialchars($row['example']); ?></code></td>
                                            <td>
                                                <button type="button" class="btn btn-outline-primary btn-sm cache-copy" data-copy="<?php echo htmlspecialchars($row['example']); ?>">Copy</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.cache-copy');
    if (!buttons.length) return;
    buttons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const payload = btn.getAttribute('data-copy') || '';
            try {
                await navigator.clipboard.writeText(payload);
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
