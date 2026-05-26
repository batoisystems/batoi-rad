<!-- JS for Monaco Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/loader.js"></script>
<script>
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs' }});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.nls.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.js"></script>
<!-- JS for Monaco Editor -->

<script>
    var editorClass;
    var controllerWrapEnabled = false;
    var controllerAiStatusEl = document.getElementById('controller-ai-status');
    var controllerAiButton = document.getElementById('controller-ai-btn');
    var controllerAiBusy = false;
    var controllerSaveStatusEl = document.getElementById('controller-save-status');
    var controllerSaveResetTimer = null;
    var controllerVersionButton = document.getElementById('controller-version-btn');
    var controllerTabEditorBtn = document.getElementById('controller-tab-editor');
    var controllerTabPatchBtn = document.getElementById('controller-tab-patch');
    var controllerTabVersionsBtn = document.getElementById('controller-tab-versions');
    var controllerTabPatchBadgeEl = document.getElementById('controller-tab-patch-badge');
    var controllerPaneEditorEl = document.getElementById('controller-pane-editor');
    var controllerPanePatchEl = document.getElementById('controller-pane-patch');
    var controllerPaneVersionsEl = document.getElementById('controller-pane-versions');
    var controllerOpenVersionsTopBtn = document.getElementById('controller-open-versions-top');
    var controllerOpenVersionsEditorBtn = document.getElementById('controller-open-versions-editor');
    var controllerEditorShellEl = document.getElementById('controller-editor-shell');
    var controllerFullscreenButton = document.getElementById('controller-fullscreen-btn');
    var controllerFullscreenIcon = document.getElementById('controller-fullscreen-icon');
    var controllerAgentContextUrl = <?php echo json_encode($agentContextUrl); ?>;
    var controllerAgentPlanUrl = <?php echo json_encode($agentPlanUrl); ?>;
    var controllerAgentPatchUrl = <?php echo json_encode($agentPatchUrl); ?>;
    var controllerAgentRefreshBtn = document.getElementById('controller-agent-refresh');
    var controllerAgentPlanBtn = document.getElementById('controller-agent-plan');
    var controllerAgentPatchBtn = document.getElementById('controller-agent-patch');
    var controllerAgentTaskEl = document.getElementById('controller-agent-task');
    var controllerAgentScopeEl = document.getElementById('controller-agent-scope');
    var controllerAgentStatusEl = document.getElementById('controller-agent-status');
    var controllerAgentProgressEl = document.getElementById('controller-agent-progress');
    var controllerAgentProgressSpinnerEl = document.getElementById('controller-agent-progress-spinner');
    var controllerAgentProgressDetailEl = document.getElementById('controller-agent-progress-detail');
    var controllerAgentContextMetaEl = document.getElementById('controller-agent-context-meta');
    var controllerAgentContextSummaryEl = document.getElementById('controller-agent-context-summary');
    var controllerAgentContextTagsEl = document.getElementById('controller-agent-context-tags');
    var controllerAgentContextListEl = document.getElementById('controller-agent-context-list');
    var controllerAgentPlanMetaEl = document.getElementById('controller-agent-plan-meta');
    var controllerAgentPlanSummaryEl = document.getElementById('controller-agent-plan-summary');
    var controllerAgentPlanStepsEl = document.getElementById('controller-agent-plan-steps');
    var controllerAgentPlanRisksEl = document.getElementById('controller-agent-plan-risks');
    var controllerAgentPlanFilesEl = document.getElementById('controller-agent-plan-files');
    var controllerAgentDiffPanelEl = document.getElementById('controller-agent-diff-panel');
    var controllerAgentDiffSummaryEl = document.getElementById('controller-agent-diff-summary');
    var controllerAgentDiffMetaEl = document.getElementById('controller-agent-diff-meta');
    var controllerAgentDiffWarningsEl = document.getElementById('controller-agent-diff-warnings');
    var controllerAgentDiffEditorEl = document.getElementById('controller-agent-diff-editor');
    var controllerAgentDiffDiscardBtn = document.getElementById('controller-agent-diff-discard');
    var controllerAgentApplyBtn = document.getElementById('controller-agent-apply');
    var controllerAgentApplyLabelEl = document.getElementById('controller-agent-apply-label');
    var controllerVersionsEmptyEl = document.getElementById('controller-versions-empty');
    var controllerVersionsTableWrapEl = document.getElementById('controller-versions-table-wrap');
    var controllerVersionsBodyEl = document.getElementById('controller-versions-body');
    var controllerAgentContextCache = null;
    var controllerAgentBusy = false;
    var controllerAgentPatchState = null;
    var controllerAgentDiffEditor = null;
    var controllerAgentDiffModels = { original: null, modified: null };
    var controllerAgentLastApplySteps = [];
    var controllerStepContextEl = document.getElementById('controller-step-context');
    var controllerStepPlanEl = document.getElementById('controller-step-plan');
    var controllerStepPatchEl = document.getElementById('controller-step-patch');
    var controllerStepApplyEl = document.getElementById('controller-step-apply');
    var controllerStepVersionEl = document.getElementById('controller-step-version');
    var controllerAgentButtonDefaults = {
        refresh: controllerAgentRefreshBtn ? controllerAgentRefreshBtn.innerHTML : '',
        plan: controllerAgentPlanBtn ? controllerAgentPlanBtn.innerHTML : '',
        patch: controllerAgentPatchBtn ? controllerAgentPatchBtn.innerHTML : '',
        apply: controllerAgentApplyLabelEl ? controllerAgentApplyLabelEl.textContent : 'Apply + Lint + Version',
        discard: controllerAgentDiffDiscardBtn ? controllerAgentDiffDiscardBtn.innerHTML : ''
    };

    
    require(['vs/editor/editor.main'], function() {
        editorClass = monaco.editor.create(document.getElementById('code_class'), {
            value: <?php echo json_encode($this->runData["data"]["code_class"]); ?>,
            language: 'php',
            theme: 'vs-dark',
            automaticLayout: true
        });

        // Add change event listeners to each editor
        editorClass.onDidChangeModelContent(function() {
            saveContent('code_class', editorClass.getValue(), false);
        });

        // Add Code Assistance Trigger for each editor
        [editorClass].forEach(editor => {
            editor.onKeyUp(function(e) {
                // Check for Shift + Space
                if (e.shiftKey && e.code === 'Space') {
                    e.preventDefault();
                    e.stopPropagation();
                    requestCodeAssistance(editor);
                }
            });
        });
    });

    if (controllerAiButton) {
        controllerAiButton.addEventListener('click', function(e) {
            e.preventDefault();
            if (editorClass) {
                requestCodeAssistance(editorClass);
                editorClass.focus();
            }
        });
    }

    if (controllerVersionButton) {
        controllerVersionButton.addEventListener('click', function() {
            if (!editorClass) {
                setControllerSaveStatus('Editor not ready.', 'danger');
                return;
            }
            saveContent('code_class', editorClass.getValue(), true);
        });
    }

    if (controllerTabEditorBtn) {
        controllerTabEditorBtn.addEventListener('click', function() {
            activateControllerWorkspaceTab('editor');
        });
    }

    if (controllerTabPatchBtn) {
        controllerTabPatchBtn.addEventListener('click', function() {
            activateControllerWorkspaceTab('patch');
        });
    }

    if (controllerTabVersionsBtn) {
        controllerTabVersionsBtn.addEventListener('click', function() {
            activateControllerWorkspaceTab('versions');
        });
    }

    if (controllerOpenVersionsTopBtn) {
        controllerOpenVersionsTopBtn.addEventListener('click', function() {
            activateControllerWorkspaceTab('versions');
        });
    }

    if (controllerOpenVersionsEditorBtn) {
        controllerOpenVersionsEditorBtn.addEventListener('click', function() {
            activateControllerWorkspaceTab('versions');
        });
    }

    if (controllerAgentRefreshBtn) {
        controllerAgentRefreshBtn.addEventListener('click', function() {
            loadControllerAgentContext(true);
        });
    }

    if (controllerAgentPlanBtn) {
        controllerAgentPlanBtn.addEventListener('click', function() {
            requestControllerAgentPlan();
        });
    }

    if (controllerAgentPatchBtn) {
        controllerAgentPatchBtn.addEventListener('click', function() {
            requestControllerAgentPatch();
        });
    }

    if (controllerAgentDiffDiscardBtn) {
        controllerAgentDiffDiscardBtn.addEventListener('click', function() {
            discardControllerAgentPatch();
        });
    }

    if (controllerAgentApplyBtn) {
        controllerAgentApplyBtn.addEventListener('click', function(e) {
            applyControllerAgentPatch(e);
        });
    }

    function saveContent(type, content, createVersion) {
        setControllerSaveStatus(createVersion ? 'Saving & versioning controller…' : 'Saving controller code…', 'info');
        fetch('<?php print $postThroughAjaxUrl;?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                type: type,
                content: content,
                create_version: createVersion ? 1 : 0
            })
        })
        .then(response => response.json())
        .then(data => {
            setControllerSaveStatus('All changes saved.', 'success');
            scheduleControllerSaveReset();
        })
        .catch((error) => {
            console.error('Error:', error);
            setControllerSaveStatus('Save failed. Changes may be pending…', 'danger');
        });
    }

    function requestCodeAssistance(editor) {
        if (!editor) {
            setControllerAiStatus('No editor available for AI assist.', 'danger');
            return;
        }

        const content = editor.getValue();
        if (!content.trim()) {
            setControllerAiStatus('Add some controller code before requesting AI.', 'danger');
            return;
        }

        if (controllerAiBusy) {
            setControllerAiStatus('AI request in progress…', 'warning');
            return;
        }

        controllerAiBusy = true;
        setControllerAiStatus('Contacting AI…', 'info');

        fetch('<?php print $aiAssistanceUrl;?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ content: content })
        })
        .then(async response => {
            const raw = await response.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (e) {
                throw new Error('Unexpected AI response from server.');
            }
            if (!response.ok) {
                throw new Error(data.error || data.message || 'AI service request failed.');
            }
            return data;
        })
        .then(data => {
            if (data.suggestion) {
                // Insert suggestion at the current cursor position
                const currentPosition = editor.getPosition();
                editor.executeEdits('', [{ range: new monaco.Range(currentPosition.lineNumber, currentPosition.column, currentPosition.lineNumber, currentPosition.column), text: data.suggestion }]);
                setControllerAiStatus('Suggestion inserted. Press Ctrl+Z to undo.', 'success');
            } else {
                setControllerAiStatus(data.error || data.message || 'AI did not return a suggestion.', 'danger');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            setControllerAiStatus(error.message || 'Unable to reach AI service.', 'danger');
        })
        .finally(() => {
            controllerAiBusy = false;
        });
    }

    function loadControllerAgentContext(force) {
        if (!controllerAgentContextUrl || (!force && controllerAgentContextCache)) {
            if (controllerAgentContextCache) {
                renderControllerAgentContext(controllerAgentContextCache);
            }
            return Promise.resolve(controllerAgentContextCache);
        }
        setControllerAgentButtonsBusy('context');
        setControllerAgentStatus('Loading controller workspace context…', 'info');
        setControllerAgentProgressDetail('Fetching current controller, related routes, data models, and recent versions.');
        return fetch(controllerAgentContextUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(async response => {
            const raw = await response.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (e) {
                throw new Error('Unexpected controller context response.');
            }
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Unable to load controller context.');
            }
            return data.context || null;
        })
        .then(context => {
            controllerAgentContextCache = context;
            renderControllerAgentContext(context);
            setControllerWorkflowState('context');
            setControllerAgentStatus('Controller context loaded.', 'success');
            setControllerAgentProgressDetail('Context is ready for planning or patch generation.');
            return context;
        })
        .catch(error => {
            console.error('Controller agent context error:', error);
            setControllerWorkflowState('task');
            setControllerAgentStatus(error.message || 'Unable to load controller context.', 'danger');
            setControllerAgentProgressDetail('Context load failed.');
            if (controllerAgentContextSummaryEl) {
                controllerAgentContextSummaryEl.textContent = 'Controller context could not be loaded.';
            }
            if (controllerAgentContextListEl) {
                controllerAgentContextListEl.innerHTML = '<li class="list-group-item text-danger">Context load failed.</li>';
            }
            return null;
        })
        .finally(() => {
            setControllerAgentButtonsIdle();
        });
    }

    function requestControllerAgentPlan() {
        if (controllerAgentBusy) {
            setControllerAgentStatus('Agent planning is already in progress…', 'warning');
            return;
        }
        const task = controllerAgentTaskEl ? controllerAgentTaskEl.value.trim() : '';
        const scope = controllerAgentScopeEl ? controllerAgentScopeEl.value : 'controller_only';
        if (!task) {
            setControllerAgentStatus('Describe the task before generating a plan.', 'danger');
            if (controllerAgentTaskEl) {
                controllerAgentTaskEl.focus();
            }
            return;
        }

        controllerAgentBusy = true;
        setControllerAgentButtonsBusy('plan');
        setControllerWorkflowState('plan');
        setControllerAgentStatus('Generating controller plan…', 'info');
        setControllerAgentProgressDetail('Reviewing context and outlining implementation steps, risks, and files.');

        fetch(controllerAgentPlanUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                task: task,
                scope: scope
            })
        })
        .then(async response => {
            const raw = await response.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (e) {
                throw new Error('Unexpected controller plan response.');
            }
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Unable to generate controller plan.');
            }
            return data;
        })
        .then(data => {
            if (data.context) {
                controllerAgentContextCache = data.context;
                renderControllerAgentContext(data.context);
            }
            renderControllerAgentPlan(data.plan || {});
            setControllerWorkflowState('plan_ready');
            setControllerAgentStatus('Controller plan generated.', 'success');
            setControllerAgentProgressDetail('Plan ready. Review the steps and risks before generating a patch.');
        })
        .catch(error => {
            console.error('Controller agent plan error:', error);
            setControllerWorkflowState('context');
            setControllerAgentStatus(error.message || 'Unable to generate controller plan.', 'danger');
            setControllerAgentProgressDetail('Plan generation failed.');
        })
        .finally(() => {
            controllerAgentBusy = false;
            setControllerAgentButtonsIdle();
        });
    }

    function renderControllerAgentContext(context) {
        if (!context) {
            return;
        }
        const controller = context.controller || {};
        const microservice = context.microservice || {};
        const routes = context.related_routes || [];
        const dms = context.data_models || [];
        const versions = context.recent_versions || [];

        if (controllerAgentContextMetaEl) {
            controllerAgentContextMetaEl.textContent = (controller.branch || 'live').toUpperCase() + ' branch';
        }
        if (controllerAgentContextSummaryEl) {
            controllerAgentContextSummaryEl.textContent =
                (controller.name || 'Controller') + ' in ' + (microservice.name || 'microservicelet') +
                ' with ' + routes.length + ' route(s), ' + dms.length + ' data model(s), and ' + versions.length + ' recent version snapshot(s).';
        }
        if (controllerAgentContextTagsEl) {
            var tags = [
                '<span class="controller-context-pill">' + escapeHtml((controller.branch || 'live').toUpperCase()) + ' branch</span>',
                '<span class="controller-context-pill">' + escapeHtml(controller.source_file || 'n/a') + '</span>'
            ];
            if (routes.length) {
                tags.push('<span class="controller-context-pill">' + escapeHtml(String(routes.length)) + ' routes</span>');
            }
            if (dms.length) {
                tags.push('<span class="controller-context-pill">' + escapeHtml(String(dms.length)) + ' data models</span>');
            }
            if (versions.length) {
                tags.push('<span class="controller-context-pill">' + escapeHtml(String(versions.length)) + ' recent versions</span>');
            }
            controllerAgentContextTagsEl.innerHTML = tags.join('');
        }
        if (controllerAgentContextListEl) {
            const items = [
                '<li class="list-group-item"><strong>Controller</strong>: ' + escapeHtml(controller.name || 'n/a') + '</li>',
                '<li class="list-group-item"><strong>Source File</strong>: ' + escapeHtml(controller.source_file || 'n/a') + '</li>',
                '<li class="list-group-item"><strong>Class Name</strong>: ' + escapeHtml(controller.class_name || 'n/a') + '</li>',
                '<li class="list-group-item"><strong>Microservicelet</strong>: ' + escapeHtml(microservice.name || 'n/a') + '</li>',
                '<li class="list-group-item"><strong>Related Routes</strong>: ' + escapeHtml(routes.slice(0, 6).map(r => r.name).join(', ') || 'None') + '</li>',
                '<li class="list-group-item"><strong>Data Models</strong>: ' + escapeHtml(dms.slice(0, 6).map(r => r.name).join(', ') || 'None') + '</li>'
            ];
            controllerAgentContextListEl.innerHTML = items.join('');
        }
    }

    function renderControllerAgentPlan(plan) {
        const steps = Array.isArray(plan.steps) ? plan.steps : [];
        const risks = Array.isArray(plan.risks) ? plan.risks : [];
        const files = Array.isArray(plan.suggested_files) ? plan.suggested_files : [];

        if (controllerAgentPlanMetaEl) {
            controllerAgentPlanMetaEl.textContent = (plan.scope || 'controller_only').replace(/_/g, ' ');
        }
        if (controllerAgentPlanSummaryEl) {
            controllerAgentPlanSummaryEl.textContent = plan.summary || plan.objective || 'Plan generated.';
        }
        if (controllerAgentPlanStepsEl) {
            controllerAgentPlanStepsEl.innerHTML = steps.length
                ? steps.map(step => '<li>' + escapeHtml(step) + '</li>').join('')
                : '<li>No plan steps returned.</li>';
        }
        if (controllerAgentPlanRisksEl) {
            controllerAgentPlanRisksEl.innerHTML = risks.length
                ? risks.map(risk => '<li>' + escapeHtml(risk) + '</li>').join('')
                : '<li>No specific risks identified.</li>';
        }
        if (controllerAgentPlanFilesEl) {
            controllerAgentPlanFilesEl.innerHTML = files.length
                ? files.map(file => '<li><code>' + escapeHtml(file.path || 'n/a') + '</code>' + (file.reason ? ' <span class="text-muted">(' + escapeHtml(file.reason) + ')</span>' : '') + '</li>').join('')
                : '<li>No files proposed yet.</li>';
        }
    }

    function requestControllerAgentPatch() {
        if (controllerAgentBusy) {
            setControllerAgentStatus('An agent action is already in progress…', 'warning');
            return;
        }
        const task = controllerAgentTaskEl ? controllerAgentTaskEl.value.trim() : '';
        const scope = controllerAgentScopeEl ? controllerAgentScopeEl.value : 'controller_only';
        if (!task) {
            setControllerAgentStatus('Describe the task before generating a patch.', 'danger');
            if (controllerAgentTaskEl) {
                controllerAgentTaskEl.focus();
            }
            return;
        }

        controllerAgentBusy = true;
        setControllerAgentButtonsBusy('patch');
        setControllerWorkflowState('patch');
        setControllerAgentStatus('Generating controller patch…', 'info');
        setControllerAgentProgressDetail('Sending the current controller and task to AI to prepare a patch proposal.');
        fetch(controllerAgentPatchUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                task: task,
                scope: scope
            })
        })
        .then(async response => {
            const raw = await response.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (e) {
                throw new Error('Unexpected controller patch response.');
            }
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Unable to generate controller patch.');
            }
            return data;
        })
        .then(data => {
            if (data.context) {
                controllerAgentContextCache = data.context;
                renderControllerAgentContext(data.context);
            }
            controllerAgentPatchState = data.proposal || null;
            renderControllerAgentPatch(controllerAgentPatchState);
            setControllerWorkflowState('patch_ready');
            setControllerAgentStatus('Controller patch proposal ready for review.', 'success');
            setControllerAgentProgressDetail('Patch preview is ready. Review the diff, then apply when satisfied.');
        })
        .catch(error => {
            console.error('Controller agent patch error:', error);
            setControllerWorkflowState('plan_ready');
            setControllerAgentStatus(error.message || 'Unable to generate controller patch.', 'danger');
            setControllerAgentProgressDetail('Patch generation failed.');
        })
        .finally(() => {
            controllerAgentBusy = false;
            setControllerAgentButtonsIdle();
        });
    }

    function ensureControllerAgentDiffEditor() {
        if (controllerAgentDiffEditor || !window.monaco || !controllerAgentDiffEditorEl) {
            return;
        }
        controllerAgentDiffEditor = monaco.editor.createDiffEditor(controllerAgentDiffEditorEl, {
            automaticLayout: true,
            readOnly: true,
            renderSideBySide: true,
            minimap: { enabled: false }
        });
    }

    function renderControllerAgentPatch(proposal) {
        if (!proposal || !controllerAgentDiffPanelEl) {
            return;
        }
        if (controllerAgentDiffSummaryEl) {
            controllerAgentDiffSummaryEl.textContent = proposal.summary || 'Controller patch ready.';
        }
        if (controllerAgentDiffMetaEl) {
            controllerAgentDiffMetaEl.textContent = 'Branch: ' + String(proposal.branch || 'live').toUpperCase() + ' · File: ' + (proposal.file_path || 'n/a');
        }
        if (controllerAgentDiffWarningsEl) {
            const warnings = Array.isArray(proposal.warnings) ? proposal.warnings : [];
            controllerAgentDiffWarningsEl.innerHTML = warnings.length
                ? '<div class="alert alert-warning mb-0 small"><strong>Advisories:</strong> ' + warnings.map(escapeHtml).join(' ') + '</div>'
                : '<div class="alert alert-light border mb-0 small">Review the generated diff before applying it to the controller file.</div>';
        }
        ensureControllerAgentDiffEditor();
        if (!controllerAgentDiffEditor) {
            return;
        }
        if (controllerAgentDiffModels.original) {
            controllerAgentDiffModels.original.dispose();
        }
        if (controllerAgentDiffModels.modified) {
            controllerAgentDiffModels.modified.dispose();
        }
        controllerAgentDiffModels.original = monaco.editor.createModel(proposal.original_content || '', 'php');
        controllerAgentDiffModels.modified = monaco.editor.createModel(proposal.proposed_content || '', 'php');
        controllerAgentDiffEditor.setModel({
            original: controllerAgentDiffModels.original,
            modified: controllerAgentDiffModels.modified
        });
        controllerAgentDiffEditor.layout();
        if (controllerTabPatchBadgeEl) {
            controllerTabPatchBadgeEl.classList.remove('d-none');
        }
        if (controllerTabPatchBadgeEl) {
            controllerTabPatchBadgeEl.textContent = 'Ready';
        }
        activateControllerWorkspaceTab('patch');
        controllerAgentDiffPanelEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function discardControllerAgentPatch() {
        controllerAgentPatchState = null;
        if (controllerAgentDiffModels.original) {
            controllerAgentDiffModels.original.dispose();
            controllerAgentDiffModels.original = null;
        }
        if (controllerAgentDiffModels.modified) {
            controllerAgentDiffModels.modified.dispose();
            controllerAgentDiffModels.modified = null;
        }
        if (controllerAgentDiffSummaryEl) {
            controllerAgentDiffSummaryEl.textContent = 'No patch generated yet.';
        }
        if (controllerAgentDiffMetaEl) {
            controllerAgentDiffMetaEl.textContent = 'Generate a patch to review it here while keeping the code editor unchanged in the Code Editor tab.';
        }
        if (controllerAgentDiffWarningsEl) {
            controllerAgentDiffWarningsEl.innerHTML = '<div class="alert alert-light border mb-0 small">Patch review opens in this tab. Nothing is written back into the editor until you explicitly apply.</div>';
        }
        if (controllerTabPatchBadgeEl) {
            controllerTabPatchBadgeEl.classList.add('d-none');
        }
        setControllerWorkflowState(controllerAgentContextCache ? 'context' : 'task');
        setControllerAgentStatus('Controller patch proposal discarded.', 'muted');
        activateControllerWorkspaceTab('editor');
    }

    function resetControllerAgentWorkspaceAfterApply() {
        discardControllerAgentPatch();
        controllerAgentLastApplySteps = [];
        controllerAgentBusy = false;
        if (controllerAgentTaskEl) {
            controllerAgentTaskEl.value = '';
        }
        setControllerWorkflowState('versioned');
        activateControllerWorkspaceTab('editor');
        setControllerAgentButtonsIdle();
        setControllerAgentStatus('Controller patch applied successfully.', 'success');
        setControllerAgentProgressDetail('Controller file saved, linted, and versioned. Enter the next prompt to continue.');
        if (controllerAgentTaskEl) {
            setTimeout(function() {
                controllerAgentTaskEl.focus();
            }, 50);
        }
    }

    function applyControllerAgentPatch(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        if (e && typeof e.stopPropagation === 'function') {
            e.stopPropagation();
        }
        if (!controllerAgentPatchState) {
            setControllerAgentStatus('Generate a patch before applying it.', 'danger');
            return false;
        }
        if (controllerAgentBusy) {
            setControllerAgentStatus('An agent action is already in progress…', 'warning');
            return false;
        }

        controllerAgentBusy = true;
        setControllerAgentButtonsBusy('apply');
        setControllerWorkflowState('apply');
        setControllerAgentStatus('Submitting patch for apply, lint, and version…', 'info');
        setControllerAgentProgressDetail('Saving the approved controller code, running PHP lint, and creating a version snapshot.');
        controllerAgentLastApplySteps = [];
        fetch('<?php print $postThroughAjaxUrl;?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                type: 'code_class',
                content: controllerAgentPatchState.proposed_content || '',
                create_version: 1,
                expected_checksum: controllerAgentPatchState.base_checksum || '',
                lint_before_save: 1
            })
        })
        .then(async response => {
            const raw = await response.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (e) {
                throw new Error('Unexpected controller save response.');
            }
            if (!response.ok) {
                const lintMsg = data.lint && data.lint.output ? (' ' + data.lint.output) : '';
                throw new Error((data.message || 'Unable to save approved patch.') + lintMsg);
            }
            return data;
        })
        .then(data => {
            if (editorClass && typeof controllerAgentPatchState.proposed_content === 'string') {
                editorClass.getModel().setValue(controllerAgentPatchState.proposed_content);
            }
            setControllerSaveStatus('Agent apply completed and versioned.', 'success');
            scheduleControllerSaveReset();
            if (data.latest_version) {
                prependControllerVersionRow(data.latest_version);
            }
            resetControllerAgentWorkspaceAfterApply();
        })
        .catch(error => {
            console.error('Controller agent apply error:', error);
            setControllerWorkflowState('patch_ready');
            setControllerAgentStatus(error.message || 'Unable to apply controller patch.', 'danger');
            setControllerAgentProgressDetail('Apply failed before completion.');
            controllerAgentBusy = false;
            setControllerAgentButtonsIdle();
        });
        return false;
    }

    function setControllerAgentStatus(message, tone) {
        if (!controllerAgentStatusEl) {
            return;
        }
        const toneMap = {
            success: 'text-success',
            danger: 'text-danger',
            info: 'text-primary',
            warning: 'text-warning'
        };
        controllerAgentStatusEl.className = 'small ' + (toneMap[tone] || 'text-muted');
        controllerAgentStatusEl.textContent = message;
        if (controllerAgentProgressEl) {
            controllerAgentProgressEl.classList.toggle('is-busy', tone === 'info' || tone === 'warning');
        }
        if (controllerAgentProgressSpinnerEl) {
            controllerAgentProgressSpinnerEl.classList.toggle('d-none', !(tone === 'info' || tone === 'warning'));
        }
    }

    function setControllerAgentProgressDetail(message) {
        if (!controllerAgentProgressDetailEl) {
            return;
        }
        controllerAgentProgressDetailEl.textContent = message || 'Idle.';
    }

    function renderControllerAgentSteps(steps) {
        if (!Array.isArray(steps) || !steps.length) {
            return '';
        }
        return steps.map(function(step, index) {
            var msg = step && step.message ? step.message : ('Step ' + (index + 1));
            return (index + 1) + '. ' + msg;
        }).join(' ');
    }

    function activateControllerWorkspaceTab(tab) {
        var editorActive = tab === 'editor';
        var patchActive = tab === 'patch';
        var versionsActive = tab === 'versions';
        if (controllerTabEditorBtn) {
            controllerTabEditorBtn.classList.toggle('active', editorActive);
            controllerTabEditorBtn.setAttribute('aria-selected', editorActive ? 'true' : 'false');
        }
        if (controllerTabPatchBtn) {
            controllerTabPatchBtn.classList.toggle('active', patchActive);
            controllerTabPatchBtn.setAttribute('aria-selected', patchActive ? 'true' : 'false');
        }
        if (controllerTabVersionsBtn) {
            controllerTabVersionsBtn.classList.toggle('active', versionsActive);
            controllerTabVersionsBtn.setAttribute('aria-selected', versionsActive ? 'true' : 'false');
        }
        if (controllerPaneEditorEl) {
            controllerPaneEditorEl.classList.toggle('active', editorActive);
        }
        if (controllerPanePatchEl) {
            controllerPanePatchEl.classList.toggle('active', patchActive);
        }
        if (controllerPaneVersionsEl) {
            controllerPaneVersionsEl.classList.toggle('active', versionsActive);
        }
        if (editorActive && editorClass) {
            setTimeout(function() {
                editorClass.layout();
            }, 50);
        }
        if (patchActive && controllerAgentDiffEditor) {
            setTimeout(function() {
                controllerAgentDiffEditor.layout();
            }, 50);
        }
    }

    function setControllerWorkflowState(state) {
        var map = {
            task: [controllerStepContextEl],
            context: [controllerStepContextEl, controllerStepPlanEl],
            plan: [controllerStepContextEl, controllerStepPlanEl],
            plan_ready: [controllerStepContextEl, controllerStepPlanEl, controllerStepPatchEl],
            patch: [controllerStepContextEl, controllerStepPlanEl, controllerStepPatchEl],
            patch_ready: [controllerStepContextEl, controllerStepPlanEl, controllerStepPatchEl, controllerStepApplyEl],
            apply: [controllerStepContextEl, controllerStepPlanEl, controllerStepPatchEl, controllerStepApplyEl],
            versioned: [controllerStepContextEl, controllerStepPlanEl, controllerStepPatchEl, controllerStepApplyEl, controllerStepVersionEl]
        };
        var activeLookup = {
            task: controllerStepContextEl,
            context: controllerStepPlanEl,
            plan: controllerStepPlanEl,
            plan_ready: controllerStepPatchEl,
            patch: controllerStepPatchEl,
            patch_ready: controllerStepApplyEl,
            apply: controllerStepApplyEl,
            versioned: controllerStepVersionEl
        };
        var steps = [controllerStepContextEl, controllerStepPlanEl, controllerStepPatchEl, controllerStepApplyEl, controllerStepVersionEl];
        steps.forEach(function(el) {
            if (!el) {
                return;
            }
            el.classList.remove('is-active');
            el.classList.remove('is-done');
        });
        (map[state] || []).forEach(function(el) {
            if (el) {
                el.classList.add('is-done');
            }
        });
        if (activeLookup[state]) {
            activeLookup[state].classList.remove('is-done');
            activeLookup[state].classList.add('is-active');
        }
    }

    function prependControllerVersionRow(entry) {
        if (!entry || !controllerVersionsBodyEl) {
            return;
        }
        if (controllerVersionsEmptyEl) {
            controllerVersionsEmptyEl.classList.add('d-none');
        }
        if (controllerVersionsTableWrapEl) {
            controllerVersionsTableWrapEl.classList.remove('d-none');
        }
        if (entry.id) {
            var existingRow = controllerVersionsBodyEl.querySelector('tr[data-version-id="' + entry.id.replace(/"/g, '\\"') + '"]');
            if (existingRow) {
                existingRow.remove();
            }
        }
        var row = document.createElement('tr');
        row.setAttribute('data-version-id', entry.id || '');
        row.innerHTML =
            '<td class="fw-semibold">' + escapeHtml(entry.id || '') + '</td>' +
            '<td>' + escapeHtml(formatControllerVersionTimestamp(entry.timestamp)) + '</td>' +
            '<td>' + escapeHtml(entry.user || 'RAD Admin') + '</td>' +
            '<td>' + escapeHtml(entry.size_human || '—') + '</td>' +
            '<td>' + escapeHtml(entry.note || '') + '</td>' +
            '<td class="text-end"><span class="badge bg-success-subtle text-success border">Latest</span></td>';
        controllerVersionsBodyEl.insertBefore(row, controllerVersionsBodyEl.firstChild);
    }

    function formatControllerVersionTimestamp(timestamp) {
        var date = new Date(Number(timestamp || 0) * 1000);
        if (isNaN(date.getTime())) {
            return '—';
        }
        return date.getFullYear() + '-' +
            String(date.getMonth() + 1).padStart(2, '0') + '-' +
            String(date.getDate()).padStart(2, '0') + ' ' +
            String(date.getHours()).padStart(2, '0') + ':' +
            String(date.getMinutes()).padStart(2, '0') + ':' +
            String(date.getSeconds()).padStart(2, '0');
    }

    function isControllerFullscreenActive() {
        return document.fullscreenElement === controllerEditorShellEl ||
            document.webkitFullscreenElement === controllerEditorShellEl;
    }

    function updateControllerFullscreenUi() {
        var active = isControllerFullscreenActive();
        if (controllerFullscreenButton) {
            controllerFullscreenButton.setAttribute('title', active ? 'Exit full screen mode' : 'Full screen mode');
        }
        if (controllerFullscreenIcon) {
            controllerFullscreenIcon.className = active ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-angle-expand';
        }
        if (editorClass) {
            setTimeout(function() {
                editorClass.layout();
            }, 80);
        }
    }

    function setControllerAgentButtonsBusy(mode) {
        if (controllerAgentRefreshBtn) {
            controllerAgentRefreshBtn.disabled = true;
        }
        if (controllerAgentPlanBtn) {
            controllerAgentPlanBtn.disabled = true;
        }
        if (controllerAgentPatchBtn) {
            controllerAgentPatchBtn.disabled = true;
        }
        if (controllerAgentApplyBtn) {
            controllerAgentApplyBtn.disabled = true;
        }
        if (controllerAgentDiffDiscardBtn) {
            controllerAgentDiffDiscardBtn.disabled = true;
        }
        if (controllerAgentRefreshBtn && mode === 'context') {
            controllerAgentRefreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Refreshing…';
        }
        if (controllerAgentPlanBtn && mode === 'plan') {
            controllerAgentPlanBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Planning…';
        }
        if (controllerAgentPatchBtn && mode === 'patch') {
            controllerAgentPatchBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Generating…';
        }
        if (controllerAgentApplyLabelEl && mode === 'apply') {
            controllerAgentApplyLabelEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Applying…';
        }
        if (controllerAgentDiffDiscardBtn && mode === 'apply') {
            controllerAgentDiffDiscardBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Locked';
        }
    }

    function setControllerAgentButtonsIdle() {
        if (controllerAgentRefreshBtn) {
            controllerAgentRefreshBtn.disabled = false;
            controllerAgentRefreshBtn.innerHTML = controllerAgentButtonDefaults.refresh;
        }
        if (controllerAgentPlanBtn) {
            controllerAgentPlanBtn.disabled = false;
            controllerAgentPlanBtn.innerHTML = controllerAgentButtonDefaults.plan;
        }
        if (controllerAgentPatchBtn) {
            controllerAgentPatchBtn.disabled = false;
            controllerAgentPatchBtn.innerHTML = controllerAgentButtonDefaults.patch;
        }
        if (controllerAgentApplyBtn) {
            controllerAgentApplyBtn.disabled = !controllerAgentPatchState;
        }
        if (controllerAgentApplyLabelEl) {
            controllerAgentApplyLabelEl.textContent = controllerAgentButtonDefaults.apply;
        }
        if (controllerAgentDiffDiscardBtn) {
            controllerAgentDiffDiscardBtn.disabled = !controllerAgentPatchState;
            controllerAgentDiffDiscardBtn.innerHTML = controllerAgentButtonDefaults.discard;
        }
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(ch) {
            switch (ch) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case "'": return '&#39;';
                default: return ch;
            }
        });
    }

    function setControllerAiStatus(message, tone = 'muted') {
        if (!controllerAiStatusEl) {
            return;
        }
        const toneMap = {
            success: 'text-success',
            danger: 'text-danger',
            info: 'text-primary',
            warning: 'text-warning'
        };
        controllerAiStatusEl.className = 'small ' + (toneMap[tone] || 'text-muted');
        controllerAiStatusEl.textContent = message;
    }

    function setControllerSaveStatus(message, tone = 'muted') {
        if (!controllerSaveStatusEl) {
            return;
        }
        const toneMap = {
            success: 'text-success',
            danger: 'text-danger',
            info: 'text-primary',
            warning: 'text-warning'
        };
        controllerSaveStatusEl.className = 'small text-nowrap ' + (toneMap[tone] || 'text-muted');
        controllerSaveStatusEl.textContent = message;
    }

    function scheduleControllerSaveReset() {
        if (controllerSaveResetTimer) {
            clearTimeout(controllerSaveResetTimer);
        }
        controllerSaveResetTimer = setTimeout(function() {
            setControllerSaveStatus('All changes saved.', 'muted');
        }, 2000);
    }

    window.controllerPerformUndo = function() {
        if (editorClass) {
            editorClass.trigger('controller-toolbar', 'undo', null);
        }
    };

    window.controllerPerformRedo = function() {
        if (editorClass) {
            editorClass.trigger('controller-toolbar', 'redo', null);
        }
    };

    window.controllerToggleComment = function() {
        if (editorClass) {
            editorClass.getAction('editor.action.commentLine').run();
        }
    };

    window.controllerFormatCode = function() {
        if (editorClass) {
            editorClass.getAction('editor.action.formatDocument').run();
        }
    };

    window.controllerGoToLine = function() {
        if (!editorClass) {
            return;
        }
        const current = editorClass.getPosition();
        const target = prompt('Go to line:', current ? current.lineNumber : 1);
        const lineNumber = parseInt(target, 10);
        if (!isNaN(lineNumber)) {
            editorClass.revealLineInCenter(lineNumber);
            editorClass.setPosition({ lineNumber: lineNumber, column: 1 });
            editorClass.focus();
        }
    };

    window.controllerToggleWrap = function() {
        if (!editorClass) {
            return;
        }
        controllerWrapEnabled = !controllerWrapEnabled;
        editorClass.updateOptions({ wordWrap: controllerWrapEnabled ? 'on' : 'off' });
    };

    window.controllerFindReplace = function() {
        if (editorClass) {
            editorClass.getAction('editor.action.startFindReplaceAction').run();
        }
    };

    window.controllerToggleExpand = function() {
        if (!controllerEditorShellEl || !editorClass) {
            return;
        }
        if (isControllerFullscreenActive()) {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
            return;
        }
        if (controllerEditorShellEl.requestFullscreen) {
            controllerEditorShellEl.requestFullscreen().catch(function() {
                const container = document.getElementById('code_class');
                if (container) {
                    container.classList.toggle('controller-expanded');
                    setTimeout(function() {
                        editorClass.layout();
                    }, 150);
                }
            });
        } else if (controllerEditorShellEl.webkitRequestFullscreen) {
            controllerEditorShellEl.webkitRequestFullscreen();
        } else {
            const container = document.getElementById('code_class');
            if (container) {
                container.classList.toggle('controller-expanded');
                setTimeout(function() {
                    editorClass.layout();
                }, 150);
            }
        }
    };

    window.controllerApplyAgentPatch = applyControllerAgentPatch;

    document.addEventListener('fullscreenchange', updateControllerFullscreenUi);
    document.addEventListener('webkitfullscreenchange', updateControllerFullscreenUi);

    loadControllerAgentContext(false);
    setControllerAgentButtonsIdle();
    setControllerWorkflowState('task');
    updateControllerFullscreenUi();
</script>
