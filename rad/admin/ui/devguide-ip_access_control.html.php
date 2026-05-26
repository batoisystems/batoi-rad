<?php
$sections = $this->runData['data']['ip_access_sections'] ?? [];
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
                        <a class="list-group-item list-group-item-action px-0" href="#<?php echo htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 small text-muted">
                    Dev Guide section:
                    <a href="<?php echo htmlspecialchars($radAdminUrl . '/devguide/view', ENT_QUOTES, 'UTF-8'); ?>">RAD Dev Guide</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <?php foreach ($sections as $section): ?>
            <div class="card mb-3" id="<?php echo htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h2 class="h5 mb-1"><?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <?php if (!empty($section['note'])): ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($section['note'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-outline-secondary btn-sm" href="#top">Top</a>
                    </div>

                    <?php if (!empty($section['bullets'])): ?>
                        <ul class="text-muted small mt-3 mb-0">
                            <?php foreach ($section['bullets'] as $bullet): ?>
                                <li><?php echo htmlspecialchars($bullet, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($section['cards'])): ?>
                        <div class="row g-3 mt-1">
                            <?php foreach ($section['cards'] as $card): ?>
                                <div class="col-md-6">
                                    <div class="border rounded h-100 p-3">
                                        <h3 class="h6 mb-2"><?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <?php if (!empty($card['body'])): ?>
                                            <p class="text-muted small"><?php echo htmlspecialchars($card['body'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($card['items'])): ?>
                                            <ul class="text-muted small mb-0">
                                                <?php foreach ($card['items'] as $item): ?>
                                                    <li><?php echo htmlspecialchars($item, ENT_QUOTES, 'UTF-8'); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['examples'])): ?>
                        <div class="mt-3">
                            <?php foreach ($section['examples'] as $example): ?>
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="fw-semibold small"><?php echo htmlspecialchars($example['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <button type="button" class="btn btn-outline-primary btn-sm ip-guide-copy" data-copy="<?php echo htmlspecialchars($example['code'], ENT_QUOTES, 'UTF-8'); ?>">Copy</button>
                                    </div>
                                    <pre class="mb-0"><code><?php echo htmlspecialchars($example['code'], ENT_QUOTES, 'UTF-8'); ?></code></pre>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['rows'])): ?>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 32%;">Item</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($section['rows'] as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><code><?php echo htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8'); ?></code></td>
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
    const buttons = document.querySelectorAll('.ip-guide-copy');
    if (!buttons.length) return;
    buttons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const payload = btn.getAttribute('data-copy') || '';
            try {
                await navigator.clipboard.writeText(payload);
                const previous = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(() => { btn.textContent = previous; }, 900);
            } catch (e) {
                btn.textContent = 'Failed';
                setTimeout(() => { btn.textContent = 'Copy'; }, 900);
            }
        });
    });
});
</script>
