<!-- JS for Monaco Editor -->
<script src="<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>/loader.js"></script>
<script>
    require.config({ paths: { 'vs': '<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>' }});
</script>
<script src="<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>/editor/editor.main.nls.js"></script>
<script src="<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>/editor/editor.main.js"></script>
<!-- JS for Monaco Editor -->

<script>
    var themeEditor;
    var themeWrapEnabled = true;
    var themeModal;
    var themeSaveUrl = <?php echo json_encode($saveUrl); ?>;
    var themeAiUrl = <?php echo json_encode($aiUrl); ?>;
    var themeAgentContextUrl = <?php echo json_encode($agentContextUrl); ?>;
    var themeAgentPlanUrl = <?php echo json_encode($agentPlanUrl); ?>;
    var themeAgentPatchUrl = <?php echo json_encode($agentPatchUrl); ?>;
    var themeAiStatusEl = document.getElementById('theme-ai-status');
    var themeAiButton = document.getElementById('theme-ai-btn');
    var themeVersionButton = document.getElementById('theme-version-btn');
    var themeSaveStatusEl = document.getElementById('theme-save-status');
    var themeOpenVersionsTopBtn = document.getElementById('theme-open-versions-top');
    var themeOpenVersionsEditorBtn = document.getElementById('theme-open-versions-editor');
    var themeTabEditorBtn = document.getElementById('theme-tab-editor');
    var themeTabPatchBtn = document.getElementById('theme-tab-patch');
    var themeTabVersionsBtn = document.getElementById('theme-tab-versions');
    var themePaneEditorEl = document.getElementById('theme-pane-editor');
    var themePanePatchEl = document.getElementById('theme-pane-patch');
    var themePaneVersionsEl = document.getElementById('theme-pane-versions');
    var themeTabPatchBadgeEl = document.getElementById('theme-tab-patch-badge');
    var themeVersionsEmptyEl = document.getElementById('theme-versions-empty');
    var themeVersionsTableWrapEl = document.getElementById('theme-versions-table-wrap');
    var themeVersionsBodyEl = document.getElementById('theme-versions-body');
    var themeAgentRefreshBtn = document.getElementById('theme-agent-refresh');
    var themeAgentPlanBtn = document.getElementById('theme-agent-plan');
    var themeAgentPatchBtn = document.getElementById('theme-agent-patch');
    var themeAgentTaskEl = document.getElementById('theme-agent-task');
    var themeAgentScopeEl = document.getElementById('theme-agent-scope');
    var themeAgentRelatedTemplateSelectEl = document.getElementById('theme-agent-related-template-select');
    var themeAgentRelatedTemplateAddBtn = document.getElementById('theme-agent-related-template-add');
    var themeAgentRelatedTemplatesEl = document.getElementById('theme-agent-related-templates');
    var themeAgentStatusEl = document.getElementById('theme-agent-status');
    var themeAgentProgressEl = document.getElementById('theme-agent-progress');
    var themeAgentProgressSpinnerEl = document.getElementById('theme-agent-progress-spinner');
    var themeAgentProgressDetailEl = document.getElementById('theme-agent-progress-detail');
    var themeAgentContextMetaEl = document.getElementById('theme-agent-context-meta');
    var themeAgentContextSummaryEl = document.getElementById('theme-agent-context-summary');
    var themeAgentContextTagsEl = document.getElementById('theme-agent-context-tags');
    var themeAgentContextListEl = document.getElementById('theme-agent-context-list');
    var themeAgentPlanMetaEl = document.getElementById('theme-agent-plan-meta');
    var themeAgentPlanSummaryEl = document.getElementById('theme-agent-plan-summary');
    var themeAgentPlanStepsEl = document.getElementById('theme-agent-plan-steps');
    var themeAgentPlanRisksEl = document.getElementById('theme-agent-plan-risks');
    var themeAgentDiffSummaryEl = document.getElementById('theme-agent-diff-summary');
    var themeAgentDiffMetaEl = document.getElementById('theme-agent-diff-meta');
    var themeAgentDiffWarningsEl = document.getElementById('theme-agent-diff-warnings');
    var themeAgentDiffEditorEl = document.getElementById('theme-agent-diff-editor');
    var themeAgentDiffDiscardBtn = document.getElementById('theme-agent-diff-discard');
    var themeAgentApplyBtn = document.getElementById('theme-agent-apply');
    var themeAgentApplyLabelEl = document.getElementById('theme-agent-apply-label');
    var themeFullscreenShellEl = document.getElementById('theme-code-fullscreen');
    var themeFullscreenButton = document.getElementById('theme-fullscreen-btn');
    var themeFullscreenIcon = document.getElementById('theme-fullscreen-icon');
    var themeStepContextEl = document.getElementById('theme-step-context');
    var themeStepPlanEl = document.getElementById('theme-step-plan');
    var themeStepPatchEl = document.getElementById('theme-step-patch');
    var themeStepApplyEl = document.getElementById('theme-step-apply');
    var themeStepVersionEl = document.getElementById('theme-step-version');
    var themeAiBusy = false;
    var themeAgentBusy = false;
    var themeAgentContextCache = null;
    var themeAgentPatchState = null;
    var themeAgentSelectedRelatedTemplates = [];
    var themeAgentDiffEditor = null;
    var themeAgentDiffModels = { original: null, modified: null };
    var themeSaveResetTimer = null;
    var themeAgentRequestSeq = 0;
    var themeButtonDefaults = {
        refresh: themeAgentRefreshBtn ? themeAgentRefreshBtn.innerHTML : '',
        plan: themeAgentPlanBtn ? themeAgentPlanBtn.innerHTML : '',
        patch: themeAgentPatchBtn ? themeAgentPatchBtn.innerHTML : '',
        apply: themeAgentApplyLabelEl ? themeAgentApplyLabelEl.textContent : 'Apply + Version',
        discard: themeAgentDiffDiscardBtn ? themeAgentDiffDiscardBtn.innerHTML : ''
    };

    document.addEventListener('DOMContentLoaded', function() {
        themeModal = window.RadAdminUI ? window.RadAdminUI.getModal(document.getElementById('themeFindReplaceModal')) : null;
    });

    require(['vs/editor/editor.main'], function() {
        themeEditor = monaco.editor.create(document.getElementById('theme_code_editor'), {
            value: <?php echo json_encode($templateCode); ?>,
            language: 'html',
            theme: 'vs-dark',
            automaticLayout: true,
            wordWrap: 'on'
        });

        themeEditor.onDidChangeModelContent(function() {
            saveThemeContent(themeEditor.getValue(), false);
        });

        themeEditor.onKeyUp(function(e) {
            if (e.shiftKey && e.code === 'Space') {
                e.preventDefault();
                e.stopPropagation();
                requestThemeAiAssist();
            }
        });
    });

    if (themeAiButton) {
        themeAiButton.addEventListener('click', function(e) {
            e.preventDefault();
            requestThemeAiAssist();
        });
    }
    if (themeVersionButton) {
        themeVersionButton.addEventListener('click', function() {
            if (!themeEditor) return;
            saveThemeContent(themeEditor.getValue(), true);
        });
    }
    if (themeOpenVersionsTopBtn) {
        themeOpenVersionsTopBtn.addEventListener('click', function() { activateThemeWorkspaceTab('versions'); });
    }
    if (themeOpenVersionsEditorBtn) {
        themeOpenVersionsEditorBtn.addEventListener('click', function() { activateThemeWorkspaceTab('versions'); });
    }
    if (themeTabEditorBtn) {
        themeTabEditorBtn.addEventListener('click', function() { activateThemeWorkspaceTab('editor'); });
    }
    if (themeTabPatchBtn) {
        themeTabPatchBtn.addEventListener('click', function() { activateThemeWorkspaceTab('patch'); });
    }
    if (themeTabVersionsBtn) {
        themeTabVersionsBtn.addEventListener('click', function() { activateThemeWorkspaceTab('versions'); });
    }
    if (themeAgentRefreshBtn) {
        themeAgentRefreshBtn.addEventListener('click', function() { loadThemeAgentContext(true); });
    }
    if (themeAgentPlanBtn) {
        themeAgentPlanBtn.addEventListener('click', function() { requestThemeAgentPlan(); });
    }
    if (themeAgentPatchBtn) {
        themeAgentPatchBtn.addEventListener('click', function() { requestThemeAgentPatch(); });
    }
    if (themeAgentDiffDiscardBtn) {
        themeAgentDiffDiscardBtn.addEventListener('click', function() { discardThemeAgentPatch(); });
    }
    if (themeAgentApplyBtn) {
        themeAgentApplyBtn.addEventListener('click', function(e) { applyThemeAgentPatch(e); });
    }
    if (themeAgentRelatedTemplateAddBtn) {
        themeAgentRelatedTemplateAddBtn.addEventListener('click', function() {
            var value = themeAgentRelatedTemplateSelectEl ? themeAgentRelatedTemplateSelectEl.value : '';
            if (!value) return;
            if (themeAgentSelectedRelatedTemplates.indexOf(value) === -1) {
                themeAgentSelectedRelatedTemplates.push(value);
            }
            if (themeAgentRelatedTemplateSelectEl) {
                themeAgentRelatedTemplateSelectEl.value = '';
            }
            renderThemeSelectedRelatedTemplates();
            clearThemeAgentFeedbackIfIdle();
        });
    }
    if (themeAgentTaskEl) {
        themeAgentTaskEl.addEventListener('input', function() {
            clearThemeAgentFeedbackIfIdle();
        });
    }
    if (themeAgentScopeInput) {
        themeAgentScopeInput.addEventListener('change', function() {
            clearThemeAgentFeedbackIfIdle();
        });
    }
    if (themeAgentRelatedTemplateSelectEl) {
        themeAgentRelatedTemplateSelectEl.addEventListener('change', function() {
            clearThemeAgentFeedbackIfIdle();
        });
    }

    function saveThemeContent(content, createVersion, expectedChecksum) {
        setThemeSaveStatus(createVersion ? 'Saving & versioning template…' : 'Saving template…', 'info');
        return fetch(themeSaveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                type: 'tpl',
                content: content,
                create_version: createVersion ? 1 : 0,
                expected_checksum: expectedChecksum || ''
            })
        })
        .then(async function(response) {
            var data = await parseThemeJsonResponse(response);
            if (!response.ok) {
                throw new Error(data.message || 'Unable to save template.');
            }
            setThemeSaveStatus('All changes saved.', 'success');
            scheduleThemeSaveReset();
            return data;
        })
        .catch(function(error) {
            console.error(error);
            setThemeSaveStatus(error.message || 'Save failed.', 'danger');
            throw error;
        });
    }

    function requestThemeAiAssist() {
        if (!themeEditor) {
            setThemeAiStatus('Editor unavailable for AI assist.', 'danger');
            return;
        }
        var content = themeEditor.getValue();
        if (!content.trim()) {
            setThemeAiStatus('Enter some template code before requesting AI.', 'danger');
            return;
        }
        if (themeAiBusy) {
            setThemeAiStatus('AI request already running…', 'warning');
            return;
        }
        themeAiBusy = true;
        setThemeAiStatus('Contacting AI…', 'info');
        fetch(themeAiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content: content })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.suggestion) {
                var position = themeEditor.getPosition();
                themeEditor.executeEdits('', [{ range: new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column), text: data.suggestion }]);
                setThemeAiStatus('Suggestion inserted. Press Ctrl+Z to undo.', 'success');
            } else {
                setThemeAiStatus(data.error || 'AI did not return a suggestion.', 'danger');
            }
        })
        .catch(function(error) {
            console.error(error);
            setThemeAiStatus('Unable to reach AI service.', 'danger');
        })
        .finally(function() {
            themeAiBusy = false;
        });
    }

    function loadThemeAgentContext(force) {
        if (!themeAgentContextUrl || (!force && themeAgentContextCache)) {
            if (themeAgentContextCache) renderThemeAgentContext(themeAgentContextCache);
            return Promise.resolve(themeAgentContextCache);
        }
        var requestId = ++themeAgentRequestSeq;
        setThemeAgentButtonsBusy('context');
        setThemeWorkflowState('context');
        setThemeAgentStatus('Loading theme workspace context…', 'info');
        setThemeAgentProgressDetail('Fetching template metadata, usage, history, and recent versions.');
        return fetch(themeAgentContextUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                task: themeAgentTaskEl ? themeAgentTaskEl.value.trim() : '',
                scope: themeAgentScopeEl ? themeAgentScopeEl.value : 'template_only',
                related_templates: themeAgentSelectedRelatedTemplates
            })
        })
        .then(async function(response) {
            var data = await parseThemeJsonResponse(response);
            if (!response.ok) throw new Error(data.error || 'Unable to load theme context.');
            return data.context || null;
        })
            .then(function(context) {
                if (requestId !== themeAgentRequestSeq) return null;
                themeAgentContextCache = context;
                renderThemeAgentContext(context);
                setThemeWorkflowState('context');
                setThemeAgentStatus('Theme context loaded.', 'success');
                setThemeAgentProgressDetail('Context is ready for planning or patch generation.');
                return context;
            })
            .catch(function(error) {
                if (requestId !== themeAgentRequestSeq) return null;
                console.error(error);
                setThemeWorkflowState('task');
                setThemeAgentStatus(error.message || 'Unable to load theme context.', 'danger');
                setThemeAgentProgressDetail('Context load failed.');
                return null;
            })
            .finally(function() {
                if (requestId !== themeAgentRequestSeq) return;
                setThemeAgentButtonsIdle();
            });
    }

    function requestThemeAgentPlan() {
        if (themeAgentBusy) {
            setThemeAgentStatus('An agent action is already in progress…', 'warning');
            return;
        }
        var task = themeAgentTaskEl ? themeAgentTaskEl.value.trim() : '';
        var scope = themeAgentScopeEl ? themeAgentScopeEl.value : 'template_only';
        if (!task) {
            setThemeAgentStatus('Describe the task before generating a plan.', 'danger');
            if (themeAgentTaskEl) themeAgentTaskEl.focus();
            return;
        }
        themeAgentBusy = true;
        var requestId = ++themeAgentRequestSeq;
        setThemeAgentButtonsBusy('plan');
        setThemeWorkflowState('plan');
        setThemeAgentStatus('Generating theme plan…', 'info');
        setThemeAgentProgressDetail('Reviewing shared usage, layout scope, and implementation risks.');
        fetch(themeAgentPlanUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ task: task, scope: scope, related_templates: themeAgentSelectedRelatedTemplates })
        })
        .then(async function(response) {
            var data = await parseThemeJsonResponse(response);
            if (!response.ok) throw new Error(data.error || 'Unable to generate theme plan.');
            return data;
        })
        .then(function(data) {
            if (requestId !== themeAgentRequestSeq) return;
            if (data.context) {
                themeAgentContextCache = data.context;
                renderThemeAgentContext(data.context);
            }
            renderThemeAgentPlan(data.plan || {});
            setThemeWorkflowState('plan_ready');
            setThemeAgentStatus('Theme plan generated.', 'success');
            setThemeAgentProgressDetail('Plan ready. Review it before generating a patch.');
        })
        .catch(function(error) {
            if (requestId !== themeAgentRequestSeq) return;
            console.error(error);
            setThemeWorkflowState('context');
            setThemeAgentStatus(error.message || 'Unable to generate theme plan.', 'danger');
            setThemeAgentProgressDetail('Plan generation failed.');
        })
        .finally(function() {
            if (requestId !== themeAgentRequestSeq) return;
            themeAgentBusy = false;
            setThemeAgentButtonsIdle();
        });
    }

    function requestThemeAgentPatch() {
        if (themeAgentBusy) {
            setThemeAgentStatus('An agent action is already in progress…', 'warning');
            return;
        }
        var task = themeAgentTaskEl ? themeAgentTaskEl.value.trim() : '';
        var scope = themeAgentScopeEl ? themeAgentScopeEl.value : 'template_only';
        if (!task) {
            setThemeAgentStatus('Describe the task before generating a patch.', 'danger');
            if (themeAgentTaskEl) themeAgentTaskEl.focus();
            return;
        }
        themeAgentBusy = true;
        var requestId = ++themeAgentRequestSeq;
        setThemeAgentButtonsBusy('patch');
        setThemeWorkflowState('patch');
        setThemeAgentStatus('Generating template patch…', 'info');
        setThemeAgentProgressDetail('Preparing a full updated template file for review.');
        fetch(themeAgentPatchUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ task: task, scope: scope, related_templates: themeAgentSelectedRelatedTemplates })
        })
        .then(async function(response) {
            var data = await parseThemeJsonResponse(response);
            if (!response.ok) throw new Error(data.error || 'Unable to generate theme patch.');
            return data;
        })
        .then(function(data) {
            if (requestId !== themeAgentRequestSeq) return;
            if (data.context) {
                themeAgentContextCache = data.context;
                renderThemeAgentContext(data.context);
            }
            themeAgentPatchState = data.proposal || null;
            renderThemeAgentPatch(themeAgentPatchState);
            setThemeWorkflowState('patch_ready');
            setThemeAgentStatus('Template patch ready for review.', 'success');
            setThemeAgentProgressDetail('Patch preview is ready. Review the diff, then apply when satisfied.');
        })
        .catch(function(error) {
            if (requestId !== themeAgentRequestSeq) return;
            console.error(error);
            setThemeWorkflowState('plan_ready');
            setThemeAgentStatus(error.message || 'Unable to generate theme patch.', 'danger');
            setThemeAgentProgressDetail('Patch generation failed.');
        })
        .finally(function() {
            if (requestId !== themeAgentRequestSeq) return;
            themeAgentBusy = false;
            setThemeAgentButtonsIdle();
        });
    }

    function renderThemeAgentContext(context) {
        if (!context) return;
        var tpl = context.template || {};
        var usage = context.usage || [];
        var versions = context.recent_versions || [];
        if (themeAgentContextMetaEl) {
            themeAgentContextMetaEl.textContent = tpl.file || 'Template';
        }
        if (themeAgentContextSummaryEl) {
            var relatedCount = (context.related_templates || []).length;
            themeAgentContextSummaryEl.textContent = (tpl.file || 'Template') + ' with ' + usage.length + ' consuming microservicelet(s), ' + versions.length + ' recent version snapshot(s), and ' + relatedCount + ' related template(s) in context.';
        }
        if (themeAgentContextTagsEl) {
            var tags = [
                '<span class="theme-context-pill">' + escapeHtml(tpl.size_human || 'n/a') + '</span>',
                '<span class="theme-context-pill">' + escapeHtml(String(tpl.lines || 0)) + ' lines</span>',
                '<span class="theme-context-pill">' + escapeHtml(String(usage.length)) + ' usages</span>',
                '<span class="theme-context-pill">' + escapeHtml(String(versions.length)) + ' versions</span>',
                '<span class="theme-context-pill">' + escapeHtml(String((context.related_templates || []).length)) + ' related templates</span>'
            ];
            themeAgentContextTagsEl.innerHTML = tags.join('');
        }
        if (themeAgentContextListEl) {
            var items = [
                '<li class="list-group-item"><strong>Template</strong>: ' + escapeHtml(tpl.file || 'n/a') + '</li>',
                '<li class="list-group-item"><strong>Path</strong>: ' + escapeHtml(tpl.path || 'n/a') + '</li>',
                '<li class="list-group-item"><strong>Usage</strong>: ' + escapeHtml(usage.slice(0, 8).map(function(row){ return row.name; }).join(', ') || 'None') + '</li>',
                '<li class="list-group-item"><strong>Recent Versions</strong>: ' + escapeHtml(versions.slice(0, 5).map(function(row){ return row.id; }).join(', ') || 'None') + '</li>',
                '<li class="list-group-item"><strong>Related Templates</strong>: ' + escapeHtml((context.related_templates || []).map(function(row){ return row.name + " (" + row.mode + ")"; }).join(', ') || 'None') + '</li>'
            ];
            themeAgentContextListEl.innerHTML = items.join('');
        }
        syncThemeSelectedRelatedTemplatesFromContext(context);
    }

    function renderThemeAgentPlan(plan) {
        var steps = Array.isArray(plan.steps) ? plan.steps : [];
        var risks = Array.isArray(plan.risks) ? plan.risks : [];
        if (themeAgentPlanMetaEl) themeAgentPlanMetaEl.textContent = (plan.scope || 'template_only').replace(/_/g, ' ');
        if (themeAgentPlanSummaryEl) themeAgentPlanSummaryEl.textContent = plan.summary || 'Plan generated.';
        if (themeAgentPlanStepsEl) themeAgentPlanStepsEl.innerHTML = steps.length ? steps.map(function(step) { return '<li>' + escapeHtml(step) + '</li>'; }).join('') : '<li>No plan steps returned.</li>';
        if (themeAgentPlanRisksEl) themeAgentPlanRisksEl.innerHTML = risks.length ? risks.map(function(risk) { return '<li>' + escapeHtml(risk) + '</li>'; }).join('') : '<li>No specific risks identified.</li>';
    }

    function ensureThemeDiffEditor() {
        if (themeAgentDiffEditor || !window.monaco || !themeAgentDiffEditorEl) return;
        themeAgentDiffEditor = monaco.editor.createDiffEditor(themeAgentDiffEditorEl, {
            automaticLayout: true,
            readOnly: true,
            renderSideBySide: true,
            minimap: { enabled: false }
        });
    }

    function renderThemeAgentPatch(proposal) {
        if (!proposal) return;
        if (themeAgentDiffSummaryEl) themeAgentDiffSummaryEl.textContent = proposal.summary || 'Template patch ready.';
        if (themeAgentDiffMetaEl) themeAgentDiffMetaEl.textContent = 'File: ' + (proposal.file_path || 'n/a');
        if (themeAgentDiffWarningsEl) {
            var warnings = Array.isArray(proposal.warnings) ? proposal.warnings : [];
            themeAgentDiffWarningsEl.innerHTML = warnings.length
                ? '<div class="alert alert-warning mb-0 small"><strong>Advisories:</strong> ' + warnings.map(escapeHtml).join(' ') + '</div>'
                : '<div class="alert alert-light border mb-0 small">Review the generated diff before applying it to the template.</div>';
        }
        ensureThemeDiffEditor();
        if (!themeAgentDiffEditor) return;
        if (themeAgentDiffModels.original) themeAgentDiffModels.original.dispose();
        if (themeAgentDiffModels.modified) themeAgentDiffModels.modified.dispose();
        themeAgentDiffModels.original = monaco.editor.createModel(proposal.original_content || '', 'html');
        themeAgentDiffModels.modified = monaco.editor.createModel(proposal.proposed_content || '', 'html');
        themeAgentDiffEditor.setModel({ original: themeAgentDiffModels.original, modified: themeAgentDiffModels.modified });
        themeAgentDiffEditor.layout();
        if (themeTabPatchBadgeEl) {
            themeTabPatchBadgeEl.classList.remove('d-none');
            themeTabPatchBadgeEl.textContent = 'Ready';
        }
        setThemeAgentStatus('Template patch ready for review.', 'success');
        setThemeAgentProgressDetail('Patch preview is ready. Review the diff, then apply when satisfied.');
        activateThemeWorkspaceTab('patch');
    }

    function discardThemeAgentPatch() {
        themeAgentPatchState = null;
        if (themeAgentDiffModels.original) {
            themeAgentDiffModels.original.dispose();
            themeAgentDiffModels.original = null;
        }
        if (themeAgentDiffModels.modified) {
            themeAgentDiffModels.modified.dispose();
            themeAgentDiffModels.modified = null;
        }
        if (themeAgentDiffSummaryEl) themeAgentDiffSummaryEl.textContent = 'No patch generated yet.';
        if (themeAgentDiffMetaEl) themeAgentDiffMetaEl.textContent = 'Generate a patch to review it here while keeping the template editor unchanged.';
        if (themeAgentDiffWarningsEl) themeAgentDiffWarningsEl.innerHTML = '<div class="alert alert-light border mb-0 small">Patch review opens in this tab. Nothing is written back until you explicitly apply.</div>';
        if (themeTabPatchBadgeEl) themeTabPatchBadgeEl.classList.add('d-none');
        setThemeWorkflowState(themeAgentContextCache ? 'context' : 'task');
        setThemeAgentStatus('Template patch discarded.', 'muted');
        activateThemeWorkspaceTab('editor');
    }

    function applyThemeAgentPatch(e) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        if (!themeAgentPatchState) {
            setThemeAgentStatus('Generate a patch before applying it.', 'danger');
            return false;
        }
        if (themeAgentBusy) {
            setThemeAgentStatus('An agent action is already in progress…', 'warning');
            return false;
        }
        themeAgentBusy = true;
        setThemeAgentButtonsBusy('apply');
        setThemeWorkflowState('apply');
        setThemeAgentStatus('Applying template patch…', 'info');
        setThemeAgentProgressDetail('Saving the approved template and creating a version snapshot.');
        saveThemeContent(themeAgentPatchState.proposed_content || '', true, themeAgentPatchState.base_checksum || '')
            .then(function(data) {
                if (themeEditor && typeof themeAgentPatchState.proposed_content === 'string') {
                    themeEditor.getModel().setValue(themeAgentPatchState.proposed_content);
                }
                if (data.latest_version) {
                    prependThemeVersionRow(data.latest_version);
                }
                resetThemeAgentWorkspaceAfterApply();
            })
            .catch(function(error) {
                console.error(error);
                setThemeWorkflowState('patch_ready');
                setThemeAgentStatus(error.message || 'Unable to apply template patch.', 'danger');
                setThemeAgentProgressDetail('Apply failed before completion.');
                themeAgentBusy = false;
                setThemeAgentButtonsIdle();
            });
        return false;
    }

    function prependThemeVersionRow(entry) {
        if (!entry || !themeVersionsBodyEl) return;
        if (themeVersionsEmptyEl) themeVersionsEmptyEl.classList.add('d-none');
        if (themeVersionsTableWrapEl) themeVersionsTableWrapEl.classList.remove('d-none');
        var row = document.createElement('tr');
        row.setAttribute('data-version-id', entry.id || '');
        row.innerHTML =
            '<td class="fw-semibold theme-version-nowrap">' + escapeHtml(entry.id || '') + '</td>' +
            '<td class="theme-version-nowrap">' + escapeHtml(formatThemeTimestamp(entry.timestamp)) + '</td>' +
            '<td class="theme-version-nowrap">' + escapeHtml(entry.user || 'RAD Admin') + '</td>' +
            '<td class="theme-version-nowrap">' + escapeHtml(entry.size_human || '—') + '</td>' +
            '<td>' + escapeHtml(entry.note || '') + '</td>' +
            '<td class="text-end theme-version-nowrap"><span class="badge bg-success-subtle text-success border">Latest</span></td>';
        themeVersionsBodyEl.insertBefore(row, themeVersionsBodyEl.firstChild);
    }

    function resetThemeAgentWorkspaceAfterApply() {
        discardThemeAgentPatch();
        themeAgentBusy = false;
        if (themeAgentTaskEl) themeAgentTaskEl.value = '';
        activateThemeWorkspaceTab('editor');
        setThemeAgentButtonsIdle();
        setThemeWorkflowState('versioned');
        setThemeAgentStatus('Template patch applied successfully.', 'success');
        setThemeAgentProgressDetail('Template saved and versioned. Enter the next prompt to continue.');
        if (themeAgentTaskEl) {
            setTimeout(function() { themeAgentTaskEl.focus(); }, 50);
        }
    }

    function clearThemeAgentFeedbackIfIdle() {
        if (themeAgentBusy) return;
        if (themeAgentPatchState) {
            setThemeAgentStatus('Template patch ready for review.', 'success');
            setThemeAgentProgressDetail('Patch preview is ready. Review the diff, then apply when satisfied.');
            return;
        }
        if (themeAgentContextCache) {
            setThemeAgentStatus('Theme context loaded.', 'success');
            setThemeAgentProgressDetail('Context is ready for planning or patch generation.');
            return;
        }
        setThemeAgentStatus('Theme workspace ready.', 'muted');
        setThemeAgentProgressDetail('Describe the task, then generate a plan or patch.');
    }

    function setThemeAgentStatus(message, tone) {
        if (!themeAgentStatusEl) return;
        var toneMap = { success: 'text-success', danger: 'text-danger', info: 'text-primary', warning: 'text-warning' };
        themeAgentStatusEl.className = 'small ' + (toneMap[tone] || 'text-muted');
        themeAgentStatusEl.textContent = message;
        if (themeAgentProgressEl) {
            themeAgentProgressEl.classList.toggle('is-busy', tone === 'info' || tone === 'warning');
        }
        if (themeAgentProgressSpinnerEl) {
            themeAgentProgressSpinnerEl.classList.toggle('d-none', !(tone === 'info' || tone === 'warning'));
        }
    }

    function setThemeAgentProgressDetail(message) {
        if (themeAgentProgressDetailEl) themeAgentProgressDetailEl.textContent = message || 'Idle.';
    }

    function activateThemeWorkspaceTab(tab) {
        var editorActive = tab === 'editor';
        var patchActive = tab === 'patch';
        var versionsActive = tab === 'versions';
        if (themeTabEditorBtn) {
            themeTabEditorBtn.classList.toggle('active', editorActive);
            themeTabEditorBtn.setAttribute('aria-selected', editorActive ? 'true' : 'false');
        }
        if (themeTabPatchBtn) {
            themeTabPatchBtn.classList.toggle('active', patchActive);
            themeTabPatchBtn.setAttribute('aria-selected', patchActive ? 'true' : 'false');
        }
        if (themeTabVersionsBtn) {
            themeTabVersionsBtn.classList.toggle('active', versionsActive);
            themeTabVersionsBtn.setAttribute('aria-selected', versionsActive ? 'true' : 'false');
        }
        if (themePaneEditorEl) themePaneEditorEl.classList.toggle('active', editorActive);
        if (themePanePatchEl) themePanePatchEl.classList.toggle('active', patchActive);
        if (themePaneVersionsEl) themePaneVersionsEl.classList.toggle('active', versionsActive);
        if (editorActive && themeEditor) setTimeout(function() { themeEditor.layout(); }, 50);
        if (patchActive && themeAgentDiffEditor) setTimeout(function() { themeAgentDiffEditor.layout(); }, 50);
    }

    function setThemeWorkflowState(state) {
        var steps = [themeStepContextEl, themeStepPlanEl, themeStepPatchEl, themeStepApplyEl, themeStepVersionEl];
        var doneMap = {
            task: [themeStepContextEl],
            context: [themeStepContextEl, themeStepPlanEl],
            plan: [themeStepContextEl, themeStepPlanEl],
            plan_ready: [themeStepContextEl, themeStepPlanEl, themeStepPatchEl],
            patch: [themeStepContextEl, themeStepPlanEl, themeStepPatchEl],
            patch_ready: [themeStepContextEl, themeStepPlanEl, themeStepPatchEl, themeStepApplyEl],
            apply: [themeStepContextEl, themeStepPlanEl, themeStepPatchEl, themeStepApplyEl],
            versioned: [themeStepContextEl, themeStepPlanEl, themeStepPatchEl, themeStepApplyEl, themeStepVersionEl]
        };
        var activeMap = {
            task: themeStepContextEl,
            context: themeStepPlanEl,
            plan: themeStepPlanEl,
            plan_ready: themeStepPatchEl,
            patch: themeStepPatchEl,
            patch_ready: themeStepApplyEl,
            apply: themeStepApplyEl,
            versioned: themeStepVersionEl
        };
        steps.forEach(function(el) {
            if (!el) return;
            el.classList.remove('is-active');
            el.classList.remove('is-done');
        });
        (doneMap[state] || []).forEach(function(el) {
            if (el) el.classList.add('is-done');
        });
        if (activeMap[state]) {
            activeMap[state].classList.remove('is-done');
            activeMap[state].classList.add('is-active');
        }
    }

    function setThemeAgentButtonsBusy(mode) {
        [themeAgentRefreshBtn, themeAgentPlanBtn, themeAgentPatchBtn, themeAgentApplyBtn, themeAgentDiffDiscardBtn].forEach(function(btn) {
            if (btn) btn.disabled = true;
        });
        if (themeAgentRefreshBtn && mode === 'context') themeAgentRefreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing…';
        if (themeAgentPlanBtn && mode === 'plan') themeAgentPlanBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Planning…';
        if (themeAgentPatchBtn && mode === 'patch') themeAgentPatchBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Patching…';
        if (themeAgentApplyLabelEl && mode === 'apply') themeAgentApplyLabelEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying…';
    }

    function setThemeAgentButtonsIdle() {
        if (themeAgentRefreshBtn) { themeAgentRefreshBtn.disabled = false; themeAgentRefreshBtn.innerHTML = themeButtonDefaults.refresh; }
        if (themeAgentPlanBtn) { themeAgentPlanBtn.disabled = false; themeAgentPlanBtn.innerHTML = themeButtonDefaults.plan; }
        if (themeAgentPatchBtn) { themeAgentPatchBtn.disabled = false; themeAgentPatchBtn.innerHTML = themeButtonDefaults.patch; }
        if (themeAgentApplyBtn) themeAgentApplyBtn.disabled = !themeAgentPatchState;
        if (themeAgentApplyLabelEl) themeAgentApplyLabelEl.textContent = themeButtonDefaults.apply;
        if (themeAgentDiffDiscardBtn) {
            themeAgentDiffDiscardBtn.disabled = !themeAgentPatchState;
            themeAgentDiffDiscardBtn.innerHTML = themeButtonDefaults.discard;
        }
    }

    function renderThemeSelectedRelatedTemplates() {
        if (!themeAgentRelatedTemplatesEl) return;
        if (!themeAgentSelectedRelatedTemplates.length) {
            themeAgentRelatedTemplatesEl.innerHTML = '<span class="small text-muted">No related templates attached.</span>';
            return;
        }
        themeAgentRelatedTemplatesEl.innerHTML = themeAgentSelectedRelatedTemplates.map(function(name) {
            return '<span class="theme-context-pill">' + escapeHtml(name) + ' <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none" data-related-template-remove="' + escapeHtml(name) + '" aria-label="Remove ' + escapeHtml(name) + '">&times;</button></span>';
        }).join('');
        themeAgentRelatedTemplatesEl.querySelectorAll('[data-related-template-remove]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var name = btn.getAttribute('data-related-template-remove');
                themeAgentSelectedRelatedTemplates = themeAgentSelectedRelatedTemplates.filter(function(item) { return item !== name; });
                renderThemeSelectedRelatedTemplates();
            });
        });
    }

    function syncThemeSelectedRelatedTemplatesFromContext(context) {
        var names = Array.isArray(context.related_template_names) ? context.related_template_names : [];
        var selected = Array.isArray(context.related_template_summary && context.related_template_summary.selected)
            ? context.related_template_summary.selected
            : [];
        themeAgentSelectedRelatedTemplates = Array.from(new Set(selected.concat(themeAgentSelectedRelatedTemplates.filter(function(name) {
            return names.indexOf(name) !== -1;
        }))));
        renderThemeSelectedRelatedTemplates();
    }

    function setThemeAiStatus(message, tone) {
        if (!themeAiStatusEl) return;
        var toneMap = { success: 'text-success', danger: 'text-danger', info: 'text-primary', warning: 'text-warning' };
        themeAiStatusEl.className = 'small ' + (toneMap[tone] || 'text-muted');
        themeAiStatusEl.textContent = message;
    }

    function setThemeSaveStatus(message, tone) {
        if (!themeSaveStatusEl) return;
        var toneMap = { success: 'text-success', danger: 'text-danger', info: 'text-primary', warning: 'text-warning' };
        themeSaveStatusEl.className = 'small text-nowrap ' + (toneMap[tone] || 'text-muted');
        themeSaveStatusEl.textContent = message;
    }

    function scheduleThemeSaveReset() {
        if (themeSaveResetTimer) clearTimeout(themeSaveResetTimer);
        themeSaveResetTimer = setTimeout(function() { setThemeSaveStatus('All changes saved.', 'muted'); }, 2000);
    }

    function formatThemeTimestamp(timestamp) {
        var date = new Date(Number(timestamp || 0) * 1000);
        if (isNaN(date.getTime())) return '—';
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') + ' ' + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0') + ':' + String(date.getSeconds()).padStart(2, '0');
    }

    async function parseThemeJsonResponse(response) {
        var raw = await response.text();
        if (!raw) {
            return {};
        }
        try {
            return JSON.parse(raw);
        } catch (error) {
            var compact = raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
            if (compact) {
                throw new Error(compact.slice(0, 240));
            }
            throw error;
        }
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch] || ch;
        });
    }

    function isThemeFullscreenActive() {
        return document.fullscreenElement === themeFullscreenShellEl || document.webkitFullscreenElement === themeFullscreenShellEl;
    }

    function updateThemeFullscreenUi() {
        var active = isThemeFullscreenActive();
        if (themeFullscreenButton) themeFullscreenButton.setAttribute('title', active ? 'Exit full screen mode' : 'Full screen mode');
        if (themeFullscreenIcon) themeFullscreenIcon.className = active ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-angle-expand';
        if (themeEditor) setTimeout(function() { themeEditor.layout(); }, 80);
    }

    window.themePerformUndo = function() { if (themeEditor) themeEditor.trigger('theme-toolbar', 'undo', null); };
    window.themePerformRedo = function() { if (themeEditor) themeEditor.trigger('theme-toolbar', 'redo', null); };
    window.themeToggleComment = function() { if (themeEditor) themeEditor.getAction('editor.action.commentLine').run(); };
    window.themeFormatCode = function() { if (themeEditor) themeEditor.getAction('editor.action.formatDocument').run(); };
    window.themeGoToLine = function() {
        if (!themeEditor) return;
        var current = themeEditor.getPosition();
        var target = prompt('Go to line:', current ? current.lineNumber : 1);
        var lineNumber = parseInt(target, 10);
        if (!isNaN(lineNumber)) {
            themeEditor.revealLineInCenter(lineNumber);
            themeEditor.setPosition({ lineNumber: lineNumber, column: 1 });
            themeEditor.focus();
        }
    };
    window.themeToggleWrap = function() {
        if (!themeEditor) return;
        themeWrapEnabled = !themeWrapEnabled;
        themeEditor.updateOptions({ wordWrap: themeWrapEnabled ? 'on' : 'off' });
    };
    window.themeFindReplace = function() {
        if (themeModal) themeModal.show();
    };
    window.themeFindReplaceApply = function() {
        if (!themeEditor) return;
        var findTerm = document.getElementById('themeFindTerm').value;
        var replaceTerm = document.getElementById('themeReplaceTerm').value;
        if (!findTerm) return;
        var model = themeEditor.getModel();
        var matches = model.findMatches(findTerm, false, false, false, null, true);
        var edits = matches.map(function(match) {
            return { range: match.range, text: replaceTerm };
        });
        model.pushEditOperations([], edits, function() { return null; });
        if (themeModal) themeModal.hide();
    };
    window.themeToggleFullscreen = function() {
        if (!themeFullscreenShellEl || !themeEditor) return;
        if (isThemeFullscreenActive()) {
            if (document.exitFullscreen) document.exitFullscreen();
            else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
            return;
        }
        if (themeFullscreenShellEl.requestFullscreen) themeFullscreenShellEl.requestFullscreen().catch(function() {});
        else if (themeFullscreenShellEl.webkitRequestFullscreen) themeFullscreenShellEl.webkitRequestFullscreen();
    };

    document.addEventListener('fullscreenchange', updateThemeFullscreenUi);
    document.addEventListener('webkitfullscreenchange', updateThemeFullscreenUi);

    loadThemeAgentContext(false);
    renderThemeSelectedRelatedTemplates();
    setThemeAgentButtonsIdle();
    setThemeWorkflowState('task');
    clearThemeAgentFeedbackIfIdle();
    updateThemeFullscreenUi();
</script>
