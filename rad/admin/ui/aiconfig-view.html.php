<?php
$config = $this->runData['data']['ai_config'] ?? [];
$providers = $config['providers'] ?? [];
$default = $config['default_provider'] ?? 'openai';
$profiles = $config['profiles'] ?? [];
$defaultProfile = $config['default_profile'] ?? 'general';
$defaultQuality = $config['default_quality'] ?? 'mini';
$fallbackQuality = $config['fallback_quality'] ?? 'full';
$providerDefinitions = $this->runData['data']['ai_provider_definitions'] ?? [];
$profileDefinitions = $this->runData['data']['ai_profile_definitions'] ?? [];
$qualityDefinitions = $this->runData['data']['ai_quality_definitions'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <h2 class="h4 mb-1">AI Settings</h2>
            <p class="text-muted mb-0">Configure runtime providers and the profile/quality routing used by the AI layers in <code>/rad/core/</code> and RAD Admin.</p>
        </div>
        <div class="ms-lg-auto">
            <a href="<?php echo $radAdminUrl; ?>/aiconfig/view" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reload Current Config
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Notes</h3>
    </div>
    <div class="card-body">
        <ul class="small mb-3">
            <li>Primary config file: <code>rad/config/ai-config.php</code>. This page writes that file.</li>
            <li>Fallback source: legacy <code>rad.config.php</code> <code>ai</code> settings when no dedicated AI config file exists.</li>
            <li>Keys are masked after save. Leave the API key field blank to keep the stored key.</li>
            <li>Profiles decide how the framework routes generic vs coding work. Quality decides whether the request uses the mini or full model.</li>
            <li>Microsoft support in this layer is configured as Azure OpenAI compatible endpoints, not a separate public Copilot Chat API.</li>
        </ul>
        <div class="alert alert-light border small mb-0">
            Provider capability coverage is not identical. OpenAI and Azure/OpenAI-compatible Microsoft endpoints expose the full surface here. Claude and Gemini intentionally expose a smaller subset.
        </div>
    </div>
</div>

<form method="post" action="<?php echo $radAdminUrl; ?>/aiconfig/view">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end mb-3">
                <div class="col-lg-4">
                    <label class="form-label">Default Provider</label>
                    <select name="default_provider" class="form-select">
                        <?php foreach ($providerDefinitions as $key => $definition) { ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $default === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($definition['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-8">
                    <div class="form-text mt-0">
                        This is the provider fallback when a profile does not explicitly choose one.
                    </div>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label">Default Profile</label>
                    <select name="default_profile" class="form-select">
                        <?php foreach ($profileDefinitions as $key => $definition) { ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $defaultProfile === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($definition['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Default Quality</label>
                    <select name="default_quality" class="form-select">
                        <?php foreach ($qualityDefinitions as $key => $definition) { ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $defaultQuality === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($definition['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Fallback Quality</label>
                    <select name="fallback_quality" class="form-select">
                        <?php foreach ($qualityDefinitions as $key => $definition) { ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $fallbackQuality === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($definition['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <?php foreach ($profileDefinitions as $key => $definition) {
        $profile = $profiles[$key] ?? [];
        $prefix = 'profile_' . $key . '_';
        $profileDefaultQuality = $profile['default_quality'] ?? $defaultQuality;
        $profileFallbackQuality = $profile['fallback_quality'] ?? $fallbackQuality;
        $qualityModels = $profile['quality_models'] ?? [];
        $miniModel = $qualityModels['mini']['model'] ?? ($profile['model'] ?? '');
        $fullModel = $qualityModels['full']['model'] ?? ($profile['fallback_model'] ?? '');
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white">
            <h3 class="h6 mb-1"><?php echo htmlspecialchars($definition['label']); ?> Profile</h3>
            <div class="text-muted small"><?php echo htmlspecialchars($definition['summary']); ?></div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Provider</label>
                    <select name="<?php echo htmlspecialchars($prefix); ?>provider" class="form-select">
                        <?php foreach ($providerDefinitions as $providerKey => $providerDefinition) { ?>
                            <option value="<?php echo htmlspecialchars($providerKey); ?>" <?php echo (($profile['provider'] ?? $default) === $providerKey) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($providerDefinition['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Endpoint Type</label>
                    <select name="<?php echo htmlspecialchars($prefix); ?>endpoint_type" class="form-select">
                        <option value="responses" <?php echo (($profile['endpoint_type'] ?? 'responses') === 'responses') ? 'selected' : ''; ?>>Responses</option>
                        <option value="chat" <?php echo (($profile['endpoint_type'] ?? 'responses') === 'chat') ? 'selected' : ''; ?>>Chat Completions</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Timeout (s)</label>
                    <input
                        type="number"
                        class="form-control"
                        name="<?php echo htmlspecialchars($prefix); ?>timeout"
                        value="<?php echo htmlspecialchars((string)($profile['timeout'] ?? 45)); ?>"
                        min="1"
                        max="240"
                    >
                </div>
                <div class="col-md-8">
                    <label class="form-label">Endpoint</label>
                    <input
                        type="text"
                        class="form-control"
                        name="<?php echo htmlspecialchars($prefix); ?>endpoint"
                        value="<?php echo htmlspecialchars($profile['endpoint'] ?? ''); ?>"
                    >
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max Tokens</label>
                    <input
                        type="number"
                        class="form-control"
                        name="<?php echo htmlspecialchars($prefix); ?>max_tokens"
                        value="<?php echo htmlspecialchars((string)($profile['max_tokens'] ?? $definition['default_max_tokens'])); ?>"
                        min="1"
                        max="32000"
                    >
                </div>
                <div class="col-md-3">
                    <label class="form-label">Default Quality</label>
                    <select name="<?php echo htmlspecialchars($prefix); ?>default_quality" class="form-select">
                        <?php foreach ($qualityDefinitions as $qualityKey => $qualityDefinition) { ?>
                            <option value="<?php echo htmlspecialchars($qualityKey); ?>" <?php echo $profileDefaultQuality === $qualityKey ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($qualityDefinition['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fallback Quality</label>
                    <select name="<?php echo htmlspecialchars($prefix); ?>fallback_quality" class="form-select">
                        <?php foreach ($qualityDefinitions as $qualityKey => $qualityDefinition) { ?>
                            <option value="<?php echo htmlspecialchars($qualityKey); ?>" <?php echo $profileFallbackQuality === $qualityKey ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($qualityDefinition['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mini Model</label>
                    <input
                        type="text"
                        class="form-control"
                        name="<?php echo htmlspecialchars($prefix); ?>mini_model"
                        value="<?php echo htmlspecialchars($miniModel); ?>"
                        placeholder="gpt-5.4-mini"
                    >
                </div>
                <div class="col-md-3">
                    <label class="form-label">Full Model</label>
                    <input
                        type="text"
                        class="form-control"
                        name="<?php echo htmlspecialchars($prefix); ?>full_model"
                        value="<?php echo htmlspecialchars($fullModel); ?>"
                        placeholder="gpt-5.4"
                    >
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php foreach ($providerDefinitions as $key => $definition) {
        $provider = $providers[$key] ?? [];
        $advancedFields = $definition['advanced_fields'] ?? [];
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between gap-2">
            <div>
                <h3 class="h6 mb-1"><?php echo htmlspecialchars($definition['label']); ?></h3>
                <div class="text-muted small"><?php echo htmlspecialchars($definition['summary'] ?? ''); ?></div>
            </div>
            <div class="small text-muted">
                <?php foreach (($definition['capabilities'] ?? []) as $capability) { ?>
                    <span class="badge rounded-pill text-bg-light border"><?php echo htmlspecialchars($capability); ?></span>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($definition['notes'])) { ?>
                <div class="alert alert-warning py-2 small"><?php echo htmlspecialchars($definition['notes']); ?></div>
            <?php } ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">API Key</label>
                    <input
                        type="password"
                        class="form-control"
                        name="<?php echo htmlspecialchars($key); ?>_api_key"
                        value="<?php echo htmlspecialchars($provider['api_key'] ?? ''); ?>"
                        placeholder="Leave blank to keep current key"
                    >
                </div>
                <div class="col-md-4">
                    <label class="form-label">Model</label>
                    <input
                        type="text"
                        class="form-control"
                        name="<?php echo htmlspecialchars($key); ?>_model"
                        value="<?php echo htmlspecialchars($provider['model'] ?? ''); ?>"
                    >
                </div>
                <div class="col-md-4">
                    <label class="form-label">Timeout (s)</label>
                    <input
                        type="number"
                        class="form-control"
                        name="<?php echo htmlspecialchars($key); ?>_timeout"
                        value="<?php echo htmlspecialchars((string)($provider['timeout'] ?? 60)); ?>"
                        min="1"
                        max="240"
                    >
                </div>
                <div class="col-12">
                    <label class="form-label">Endpoint</label>
                    <input
                        type="text"
                        class="form-control"
                        name="<?php echo htmlspecialchars($key); ?>_endpoint"
                        value="<?php echo htmlspecialchars($provider['endpoint'] ?? ''); ?>"
                    >
                    <div class="form-text">Use the vendor default unless you are targeting a proxy or vendor-specific resource URL.</div>
                </div>

                <?php if (!empty($advancedFields)) { ?>
                <div class="col-12">
                    <div class="border-top pt-3 mt-2">
                        <div class="small text-muted mb-3">Advanced provider-specific settings</div>
                        <div class="row g-3">
                            <?php foreach ($advancedFields as $field => $fieldMeta) { ?>
                                <div class="col-md-6 col-xl-3">
                                    <label class="form-label"><?php echo htmlspecialchars($fieldMeta['label'] ?? $field); ?></label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        name="<?php echo htmlspecialchars($key . '_' . $field); ?>"
                                        value="<?php echo htmlspecialchars($provider[$field] ?? ''); ?>"
                                        placeholder="<?php echo htmlspecialchars($fieldMeta['placeholder'] ?? ''); ?>"
                                    >
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <div class="text-end mb-4">
        <button type="submit" class="btn btn-primary">Save AI Settings</button>
    </div>
</form>
