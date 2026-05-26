<?php
$classes = $this->runData['data']['app_classes'] ?? [];
$count = is_array($classes) ? count($classes) : 0;
?>

<style>
    .appclass-layout { display: grid; grid-template-columns: 280px 1fr; gap: 1rem; }
    .appclass-nav { border: 1px solid #e5e7eb; border-radius: .5rem; padding: .75rem; max-height: 70vh; overflow: auto; position: sticky; top: 1rem; background: #fff; }
    .appclass-panel { border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1rem; background: #fff; }
    .appclass-item.active { background: #f0f4ff; border-color: #cbd5ff; color: #111; }
    .appclass-item.active .text-muted { color: #4b5563 !important; }
    .method-accordion .accordion-button { padding: .5rem .75rem; }
    .method-accordion .accordion-body { padding: .75rem; }
    @media (max-width: 991px) {
        .appclass-layout { grid-template-columns: 1fr; }
        .appclass-nav { position: relative; max-height: none; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small"><?php echo $count; ?> class<?php echo $count === 1 ? '' : 'es'; ?> discovered.</span>
    <div class="d-lg-none">
        <button class="btn btn-outline-secondary btn-sm" id="toggleNav">Classes</button>
    </div>
</div>

<?php if (empty($classes)) { ?>
    <div class="alert alert-warning mb-0">
        No classes found under <code>rad/core/app</code>. Ensure files use the <code>.cls.php</code> convention and namespace <code>Core\App</code>.
    </div>
<?php } else { ?>
    <div class="appclass-layout">
        <div class="appclass-nav" id="appclassNav">
            <div class="mb-2">
                <input type="text" class="form-control form-control-sm" id="classFilter" placeholder="Filter classes…" />
            </div>
            <div class="list-group list-group-flush" id="classList">
                <?php foreach ($classes as $idx => $class) { ?>
                    <?php $slug = htmlspecialchars($class['short_name']); ?>
                    <button type="button"
                            class="list-group-item list-group-item-action appclass-item<?php echo $idx === 0 ? ' active' : ''; ?>"
                            data-class="<?php echo $slug; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold"><?php echo $slug; ?></span>
                            <span class="badge bg-light text-dark"><?php echo count($class['methods']); ?></span>
                        </div>
                        <div class="text-muted small mt-1" title="<?php echo htmlspecialchars($class['name']); ?>"><?php echo htmlspecialchars($class['name']); ?></div>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <span class="text-muted small">View code</span>
                            <a class="btn btn-outline-primary btn-sm" target="_blank" href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url']); ?>/devguide/appclass/<?php echo $slug; ?>">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Open
                            </a>
                        </div>
                    </button>
                <?php } ?>
            </div>
        </div>

        <div class="appclass-panel">
            <?php foreach ($classes as $idx => $class) { ?>
                <?php $slug = htmlspecialchars($class['short_name']); ?>
                <div class="class-panel<?php echo $idx === 0 ? '' : ' d-none'; ?>" data-class-panel="<?php echo $slug; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h4 class="mb-1"><?php echo $slug; ?></h4>
                            <div class="text-muted small mb-1"><?php echo htmlspecialchars($class['name']); ?></div>
                            <div class="text-muted small">File: <code><?php echo htmlspecialchars($class['file']); ?></code></div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary"><?php echo count($class['methods']); ?> methods</span>
                    </div>
                    <?php if (!empty($class['doc'])) { ?>
                        <p class="text-muted small" style="white-space:pre-wrap;"><?php echo htmlspecialchars($class['doc']); ?></p>
                    <?php } ?>

                    <?php if (!empty($class['methods'])) { ?>
                        <div class="accordion method-accordion" id="accordion-<?php echo $slug; ?>">
                            <?php foreach ($class['methods'] as $mIdx => $method) { ?>
                                <?php $methodId = $slug . '-' . $mIdx; ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-<?php echo $methodId; ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $methodId; ?>">
                                            <code class="me-2"><?php echo htmlspecialchars($method['signature']); ?></code>
                                        </button>
                                    </h2>
                                    <div id="collapse-<?php echo $methodId; ?>" class="accordion-collapse collapse" data-bs-parent="#accordion-<?php echo $slug; ?>">
                                        <div class="accordion-body">
                                            <?php if (!empty($method['doc'])) { ?>
                                                <div class="text-muted small mb-2" style="white-space:pre-wrap;"><?php echo htmlspecialchars($method['doc']); ?></div>
                                            <?php } ?>
                                            <?php if (!empty($method['params'])) { ?>
                                                <div class="mb-2">
                                                    <div class="text-muted small mb-1">Arguments (name : type — description):</div>
                                                    <ul class="mb-0 small">
                                                        <?php foreach ($method['params'] as $param) { ?>
                                                            <li>
                                                                <code><?php echo htmlspecialchars($param['name']); ?></code>
                                                                <?php if ($param['type']) { ?> <span class="text-muted">: <?php echo htmlspecialchars($param['type']); ?></span><?php } ?>
                                                                <?php if (!empty($param['desc'])) { ?> — <span class="text-muted"><?php echo htmlspecialchars($param['desc']); ?></span><?php } ?>
                                                                <?php if ($param['optional']) { ?> <span class="badge bg-light text-dark">optional</span><?php } ?>
                                                                <?php if ($param['optional'] && $param['default'] !== null) { ?>
                                                                    <span class="text-muted">default: <?php echo htmlspecialchars(var_export($param['default'], true)); ?></span>
                                                                <?php } ?>
                                                            </li>
                                                        <?php } ?>
                                                    </ul>
                                                </div>
                                            <?php } else { ?>
                                                <div class="text-muted small">Arguments: none</div>
                                            <?php } ?>
                                            <div>
                                                <div class="text-muted small mb-1">Returns:</div>
                                                <div class="small">
                                                    <?php if (!empty($method['return'])) { ?>
                                                        <code><?php echo htmlspecialchars($method['return']['type'] ?? 'mixed'); ?></code>
                                                        <?php if (!empty($method['return']['desc'])) { ?>
                                                            <span class="text-muted">— <?php echo htmlspecialchars($method['return']['desc']); ?></span>
                                                        <?php } ?>
                                                    <?php } else { ?>
                                                        <code>mixed</code> <span class="text-muted">— not documented</span>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <p class="text-muted small mb-0">No public methods declared.</p>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>

    <script>
        (function() {
            const classItems = Array.from(document.querySelectorAll('.appclass-item'));
            const panels = Array.from(document.querySelectorAll('.class-panel'));
            const filterInput = document.getElementById('classFilter');
            const nav = document.getElementById('appclassNav');
            const toggleNav = document.getElementById('toggleNav');

            function activate(slug) {
                classItems.forEach(btn => {
                    const isActive = btn.dataset.class === slug;
                    btn.classList.toggle('active', isActive);
                    if (isActive) { btn.scrollIntoView({block: 'nearest'}); }
                });
                panels.forEach(panel => {
                    panel.classList.toggle('d-none', panel.dataset.classPanel !== slug);
                });
                if (location.hash.replace('#','') !== slug) {
                    history.replaceState(null, '', '#' + slug);
                }
            }

            classItems.forEach(btn => {
                btn.addEventListener('click', () => activate(btn.dataset.class));
            });

            if (filterInput) {
                filterInput.addEventListener('input', (e) => {
                    const term = e.target.value.toLowerCase();
                    classItems.forEach(btn => {
                        const match = btn.textContent.toLowerCase().includes(term);
                        btn.classList.toggle('d-none', !match);
                    });
                });
            }

            if (toggleNav && nav) {
                toggleNav.addEventListener('click', () => {
                    nav.classList.toggle('d-none');
                });
            }

            // Deep link support: #ClassName
            const hash = location.hash ? location.hash.substring(1) : '';
            if (hash) {
                const match = classItems.find(btn => btn.dataset.class === hash);
                if (match) {
                    activate(hash);
                }
            }
        })();
    </script>
<?php } ?>
