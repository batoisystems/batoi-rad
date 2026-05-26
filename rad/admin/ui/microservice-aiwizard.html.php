<?php
$planJson = $this->runData['data']['plan_json'] ?? '';
$applied = $this->runData['data']['applied'] ?? null;
$navsets = $this->runData['data']['navsets'] ?? [];
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h5 mb-2">AI Microservicelet Wizard</h2>
        <p class="text-muted mb-0">Describe what you need in plain language. The wizard will draft a microservicelet with routes, controllers, data models, and optional navigation. Review, refine the JSON plan, and apply to create everything in one go.</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/aiwizard" id="aiwizard-generate-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <input type="hidden" name="action" value="generate">
            <div class="mb-3">
                <label class="form-label fw-semibold">Natural language spec</label>
                <textarea class="form-control" name="spec" rows="5" placeholder="Example: A Tasks microservicelet with CRUD routes, task list page, assign users, due dates, statuses. Needs data model (title, description, due_date, status, assignee). Nav entry under Project."></textarea>
                <div class="form-text">Mention routes, data, and nav placement if relevant. AI returns JSON you can edit.</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label fw-semibold mb-1">Response size</label>
                    <select class="form-select" name="response_size">
                        <option value="small">Small</option>
                        <option value="medium" selected>Medium</option>
                        <option value="large">Large</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" id="aiwizard-generate-btn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                    <i class="bi bi-stars me-1"></i>
                    <span class="btn-label">Generate Proposal</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">Plan JSON (editable)</h3>
        <span class="text-muted small">Keys: microservice, routes[], controllers[], nav[]</span>
    </div>
    <div class="card-body">
        <form method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/aiwizard">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <input type="hidden" name="action" value="apply">
            <textarea class="form-control font-monospace" name="plan_json" rows="16" spellcheck="false" placeholder="Generated plan will appear here."><?php echo htmlspecialchars($planJson); ?></textarea>
            <div class="form-text">Nav items can include an optional <code>navset_id</code> (existing) or set will be created and linked to this microservicelet. Trim unused routes/controllers/nav entries before applying.</div>
            <div class="mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-success" <?php echo $planJson ? '' : 'disabled'; ?>>
                    <i class="bi bi-play-fill me-1"></i>Apply Plan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('aiwizard-generate-form');
    const btn = document.getElementById('aiwizard-generate-btn');
    if (!form || !btn) return;
    const spinner = btn.querySelector('.spinner-border');
    const label = btn.querySelector('.btn-label');

    form.addEventListener('submit', () => {
        if (spinner) spinner.classList.remove('d-none');
        if (label) label.textContent = 'Generating...';
        btn.setAttribute('disabled', 'disabled');
    }, { once: true });
});
</script>
<?php if ($applied) { ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Result</h3>
    </div>
    <div class="card-body">
        <p class="mb-2">Microservicelet ID: <code><?php echo (int)($applied['ms_id'] ?? 0); ?></code></p>
        <p class="mb-1 text-muted small">Routes: <?php echo htmlspecialchars(json_encode($applied['routes_created'] ?? [])); ?></p>
        <p class="mb-1 text-muted small">Controllers: <?php echo htmlspecialchars(json_encode($applied['controllers_created'] ?? [])); ?></p>
        <p class="mb-1 text-muted small">Navset: <?php echo htmlspecialchars(json_encode($applied['navset'] ?? [])); ?></p>
        <p class="mb-0 text-muted small">Nav items: <?php echo htmlspecialchars(json_encode($applied['nav_items'] ?? [])); ?></p>
        <div class="mt-2">
            <a class="btn btn-outline-primary btn-sm" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/view">Go to Microservicelets</a>
        </div>
    </div>
</div>
<?php } ?>
