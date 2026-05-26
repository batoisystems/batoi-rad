<?php
$sections = $this->runData['data']['sections'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
?>

<style>
    .all-admin-card {
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .all-admin-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.08);
        border-color: rgba(13, 110, 253, 0.35) !important;
    }
    .all-admin-category {
        border-left: 4px solid rgba(13, 110, 253, 0.35);
        padding-left: 0.75rem;
    }
</style>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted mb-0">Browse every RAD Admin Module in one place.</p>
            </div>
            <div class="input-group input-group-sm" style="max-width:320px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Search Modules" id="all-admin-search" autocomplete="off">
            </div>
        </div>
    </div>
</div>

<div id="all-admin-sections">
    <?php foreach ($sections as $sectionLabel => $cardsList) { ?>
        <?php $sectionCount = count($cardsList); ?>
        <div class="mb-4" data-section>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 all-admin-category">
                <div class="fw-semibold text-uppercase small text-muted"><?php echo htmlspecialchars($sectionLabel); ?></div>
                <!-- <span class="badge rounded-pill text-bg-light"><?php echo (int)$sectionCount; ?> Modules</span> -->
            </div>
            <div class="row g-3">
                <?php foreach ($cardsList as [$label, $icon, $path]) { ?>
                    <?php
                    $plainLabel = strip_tags($label);
                    $searchKey = strtolower($plainLabel . ' ' . $path . ' ' . $sectionLabel);
                    $pathLabel = ltrim($path, '/');
                    ?>
                    <div class="col-12 col-sm-6 col-xl-4" data-search="<?php echo htmlspecialchars($searchKey); ?>">
                        <a href="<?php echo $radAdminUrl . $path; ?>" class="card border-0 shadow-sm h-100 text-decoration-none all-admin-card">
                            <div class="card-body d-flex gap-3 align-items-start">
                                <div class="rounded-circle bg-light text-primary d-inline-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                                    <i class="<?php echo $icon; ?> fs-4"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($plainLabel); ?></div>
                                    <div class="small text-muted">RAD Admin → <?php echo htmlspecialchars($pathLabel); ?></div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
</div>

<script>
(() => {
    const input = document.getElementById('all-admin-search');
    const items = document.querySelectorAll('#all-admin-sections [data-search]');
    const sections = document.querySelectorAll('#all-admin-sections [data-section]');
    if (!input) return;

    const applyFilter = () => {
        const term = input.value.trim().toLowerCase();
        items.forEach(item => {
            const match = term === '' || (item.dataset.search || '').includes(term);
            item.classList.toggle('d-none', !match);
        });
        sections.forEach(section => {
            const visible = section.querySelectorAll('[data-search]:not(.d-none)').length > 0;
            section.classList.toggle('d-none', !visible);
        });
    };

    input.addEventListener('input', applyFilter);
})();
</script>
