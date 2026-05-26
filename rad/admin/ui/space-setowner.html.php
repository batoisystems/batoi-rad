<?php
$space = $this->runData['data']['space'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$csrf = htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES);
$searchEndpoint = $this->runData['data']['search_endpoint'] ?? ($radAdminUrl . '/membership/searchEntities');
?>

<div class="card border-0 shadow-sm mb-4 bg-body-tertiary">
    <div class="card-body p-4">
        <div class="row g-3 align-items-start">
            <div class="col-lg-8">
                <div class="text-uppercase text-muted small fw-semibold mb-2">Ownership</div>
                <h2 class="h3 mb-2">Assign a primary owner for <?php echo htmlspecialchars((string)($space['s_name'] ?? 'this workspace')); ?></h2>
                <p class="text-muted mb-0">Pick an active user to serve as the accountable owner for governance, maintenance, and operational follow-up.</p>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted">Workspace UID</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars((string)($space['uid'] ?? '')); ?></div>
                        <div class="small text-muted mt-2">Current slug</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars((string)($space['s_slug'] ?? '—')); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <div class="row g-3">
                        <div class="col-12 position-relative" id="owner-picker" data-search-endpoint="<?php echo htmlspecialchars($searchEndpoint); ?>">
                            <label class="form-label">Owner (active user)</label>
                            <input type="hidden" name="s_owner_entity_id" value="">
                            <input type="text" class="form-control" placeholder="Search by name, email, username, or UID" autocomplete="off" data-owner-input required>
                            <div class="list-group position-absolute w-100 shadow-sm d-none mt-1" data-owner-results style="max-height:220px; overflow:auto; z-index:1020;"></div>
                            <div class="form-text">Search and choose a single active user. The selected result will populate the hidden owner ID.</div>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Owner</button>
                            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($radAdminUrl . '/space/viewone/' . ($space['uid'] ?? '')); ?>">
                                <i class="bi bi-arrow-left-circle me-1"></i>Back to Workspace
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h5 mb-3">What this affects</h3>
                <ul class="small text-muted mb-0 ps-3">
                    <li>The owner becomes the default accountable user shown on workspace detail pages.</li>
                    <li>Missing-owner diagnostics on the workspace list disappear once assigned.</li>
                    <li>You can reassign ownership later without changing memberships.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const container = document.getElementById('owner-picker');
    if (!container) return;
    const input = container.querySelector('[data-owner-input]');
    const resultsEl = container.querySelector('[data-owner-results]');
    const hiddenId = container.querySelector('input[name="s_owner_entity_id"]');
    const endpoint = container.dataset.searchEndpoint || '';
    const csrf = document.querySelector('meta[name="rad-csrf"]')?.getAttribute('content') || '';
    let debounceTimer = null;

    const clearResults = () => {
        resultsEl.innerHTML = '';
        resultsEl.classList.add('d-none');
    };

    const setSelection = (id, label) => {
        hiddenId.value = id;
        input.value = label;
        clearResults();
    };

    const renderResults = (items) => {
        resultsEl.innerHTML = '';
        if (!items.length) {
            clearResults();
            return;
        }
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            const label = `${item.s_name || 'User'} (@${item.s_identity || ''}) — #${item.id}`;
            button.textContent = label;
            button.addEventListener('click', () => setSelection(item.id, label));
            resultsEl.appendChild(button);
        });
        resultsEl.classList.remove('d-none');
    };

    const search = async (term) => {
        if (!endpoint || term.length < 2) {
            clearResults();
            return;
        }
        try {
            const res = await fetch(`${endpoint}?q=${encodeURIComponent(term)}`, {
                headers: csrf ? {'X-CSRF-Token': csrf} : {},
            });
            if (!res.ok) {
                clearResults();
                return;
            }
            const data = await res.json();
            renderResults(Array.isArray(data) ? data : []);
        } catch (e) {
            clearResults();
        }
    };

    input.addEventListener('input', () => {
        hiddenId.value = '';
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => search(input.value.trim()), 250);
    });

    document.addEventListener('click', (event) => {
        if (!container.contains(event.target)) {
            clearResults();
        }
    });
})();
</script>
