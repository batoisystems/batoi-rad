<?php
$microservices = $this->runData['data']['microservices'] ?? [];
$routes = $this->runData['data']['routes'] ?? [];
$controllers = $this->runData['data']['controllers'] ?? [];
$upgrades = $this->runData['data']['upgrades'] ?? [];
$templates = $this->runData['data']['templates'] ?? [];
$initialPrompt = $this->runData['data']['initial_prompt'] ?? '';
$contextTitle = $this->runData['data']['context_title'] ?? '';
$contextUrlFromNav = $this->runData['data']['context_url'] ?? '';

$contextUrl = $this->runData['route']['rad_admin_url'] . '/aiassist/context';
$chatUrl = $this->runData['route']['rad_admin_url'] . '/aiassist/chat';

$summaryCards = [
    ['label' => 'Microservicelets', 'value' => count($microservices), 'icon' => 'bi-diagram-3', 'caption' => 'Active RAD modules'],
    ['label' => 'Routes', 'value' => count($routes), 'icon' => 'bi-signpost-split', 'caption' => 'Endpoints ready for context'],
    ['label' => 'Controllers', 'value' => count($controllers), 'icon' => 'bi-cpu', 'caption' => 'Business logic units'],
    ['label' => 'Templates', 'value' => count($templates), 'icon' => 'bi-filetype-php', 'caption' => 'Theme snippets'],
];

$promptPresets = [
    'Explain how this controller validates user input.',
    'Summarize what this route does and list dependencies.',
    'Draft PHPUnit tests for this controller based on attached code.',
    'Suggest performance improvements for the selected microservice.',
];

$recentControllers = array_slice($controllers, 0, 5);
?>

<div class="row g-3 mb-3">
    <?php foreach ($summaryCards as $card) { ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-primary display-6"><i class="bi <?php echo $card['icon']; ?>"></i></span>
                        <div>
                            <div class="fs-4 fw-semibold"><?php echo number_format($card['value']); ?></div>
                            <div class="small text-muted text-uppercase"><?php echo htmlspecialchars($card['label']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($card['caption']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<?php if ($contextTitle !== '' || $contextUrlFromNav !== '') { ?>
<div class="alert alert-info border-0 shadow-sm">
    <div class="fw-semibold mb-1">Opened from current RAD Admin context</div>
    <?php if ($contextTitle !== '') { ?>
        <div class="small mb-1"><strong>Page:</strong> <?php echo htmlspecialchars($contextTitle); ?></div>
    <?php } ?>
    <?php if ($contextUrlFromNav !== '') { ?>
        <div class="small"><strong>URL:</strong> <a href="<?php echo htmlspecialchars($contextUrlFromNav); ?>"><?php echo htmlspecialchars($contextUrlFromNav); ?></a></div>
    <?php } ?>
</div>
<?php } ?>

<div class="row g-3" id="ai-assist-app" data-context-url="<?php echo htmlspecialchars($contextUrl); ?>" data-chat-url="<?php echo htmlspecialchars($chatUrl); ?>">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Batoi Intelligence Prompt</h5>
                    <small class="text-muted">Describe what you need help with.</small>
                </div>
                <button class="btn btn-outline-primary btn-sm" id="ai-run-btn">
                    <i class="bi bi-stars me-1"></i>Run Prompt
                </button>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Prompt</label>
                    <textarea class="form-control" id="ai-prompt" rows="10" placeholder="E.g., Explain how this route handles input validation..."><?php echo htmlspecialchars($initialPrompt); ?></textarea>
                </div>
                <div>
                    <label class="form-label">Attached Context</label>
                    <div id="ai-attachments" class="border rounded p-2" style="min-height: 120px;">
                        <div class="text-muted small" id="ai-attachments-empty">No context attached yet. Use the selectors on the right to add code or metadata.</div>
                    </div>
                </div>
                <hr>
                <div>
                    <label class="form-label">Prompt Templates</label>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($promptPresets as $prompt) { ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-start" data-ai-prompt="<?php echo htmlspecialchars($prompt); ?>">
                                <i class="bi bi-stars me-1"></i><?php echo htmlspecialchars($prompt); ?>
                            </button>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Context Library</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Microservicelet</label>
                    <div class="input-group">
                        <select class="form-select" data-context-select="microservice">
                            <option value="">Select microservice...</option>
                            <?php foreach ($microservices as $ms): ?>
                                <option value="<?php echo htmlspecialchars($ms['id']); ?>">
                                    <?php echo htmlspecialchars($ms['s_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-secondary" data-context-add="microservice">Attach</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Route</label>
                    <div class="input-group">
                        <select class="form-select" data-context-select="route">
                            <option value="">Select route...</option>
                            <?php foreach ($routes as $route): ?>
                                <?php
                                    $routeLabel = trim(($route['s_name'] ?? '') . ' — ' . ($route['ms_name'] ?? ''));
                                ?>
                                <option value="<?php echo htmlspecialchars($route['id']); ?>">
                                    <?php echo htmlspecialchars($routeLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-secondary" data-context-add="route">Attach</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Controller</label>
                    <div class="input-group">
                        <select class="form-select" data-context-select="controller">
                            <option value="">Select controller...</option>
                            <?php foreach ($controllers as $controller): ?>
                                <?php
                                    $controllerLabel = trim(($controller['s_name'] ?? '') . ' — ' . ($controller['ms_name'] ?? ''));
                                ?>
                                <option value="<?php echo htmlspecialchars($controller['id']); ?>">
                                    <?php echo htmlspecialchars($controllerLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-secondary" data-context-add="controller">Attach</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Upgrade Script</label>
                    <div class="input-group">
                        <select class="form-select" data-context-select="upgrade">
                            <option value="">Select upgrade...</option>
                            <?php foreach ($upgrades as $upgrade): ?>
                                <option value="<?php echo htmlspecialchars($upgrade); ?>"><?php echo htmlspecialchars($upgrade); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-secondary" data-context-add="upgrade">Attach</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Theme Template</label>
                    <div class="input-group">
                        <select class="form-select" data-context-select="theme">
                            <option value="">Select template...</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo htmlspecialchars($template); ?>"><?php echo htmlspecialchars($template); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-secondary" data-context-add="theme">Attach</button>
                    </div>
                </div>
                <?php if (!empty($recentControllers)) { ?>
                <div class="mt-4">
                    <label class="form-label">Recent Controllers</label>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentControllers as $controller) { ?>
                            <button type="button"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                    data-context-quick="controller"
                                    data-id="<?php echo htmlspecialchars($controller['id']); ?>">
                                <span><?php echo htmlspecialchars(($controller['s_name'] ?? '') . ' — ' . ($controller['ms_name'] ?? '')); ?></span>
                                <i class="bi bi-plus-circle text-primary"></i>
                            </button>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">AI Response</h5>
                    <small class="text-muted" id="ai-response-status">Idle</small>
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" id="ai-copy-response"><i class="bi bi-clipboard"></i></button>
                    <button class="btn btn-outline-secondary" id="ai-clear-response"><i class="bi bi-x-circle"></i></button>
                </div>
            </div>
            <div class="card-body">
                <pre class="bg-dark text-light rounded p-3 h-100" id="ai-response" style="min-height: 320px; white-space: pre-wrap;"></pre>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
            <div class="card-header">
                <strong>How to use Batoi Intelligence</strong>
            </div>
            <div class="card-body">
                <ol class="mb-0 ps-3">
                    <li class="mb-2"><strong>Describe your goal</strong> in the Prompt Builder (left column). This can be a specific question (“Explain how this controller sanitizes input”) or an instruction (“Draft tests for this upgrade”).</li>
                    <li class="mb-2"><strong>Attach relevant context</strong> using the selectors in the middle column. Pick microservices, routes, controllers, upgrades, or theme templates; each attachment includes code/metadata for the AI to reference.</li>
                    <li class="mb-2"><strong>Run the request</strong> with “Run Prompt”. The response appears on the right; use the copy icon to reuse the snippet inside other editors.</li>
                    <li><strong>Iterate</strong>: adjust the prompt or add/remove attachments to refine the answer. Attachments are optional but help the AI stay accurate to your RAD assets.</li>
                </ol>
            </div>
        </div>
