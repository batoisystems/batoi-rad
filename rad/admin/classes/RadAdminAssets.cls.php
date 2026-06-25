<?php
namespace RadAdmin;

class RadAdminAssets
{
    public static function isUifEnabled(array $runData): bool
    {
        $envValue = getenv('RAD_ADMIN_UIF_ENABLED');
        if ($envValue !== false && trim((string)$envValue) !== '') {
            return self::isTruthy($envValue);
        }

        return self::isTruthy($runData['config']['sys']['rad_admin_uif_enabled'] ?? 'N');
    }

    public static function renderUifHead(array $runData): string
    {
        if (!self::isUifEnabled($runData)) {
            return '';
        }

        $assetUrl = self::radAssetsUrl($runData);
        if ($assetUrl === '') {
            return '';
        }

        return '<link href="' . self::escape($assetUrl . '/uif/uif.css') . '" rel="stylesheet">' . "\n"
            . '<script src="' . self::escape($assetUrl . '/uif/uif.iife.js') . '"></script>' . "\n"
            . self::renderAdminChartHelpers();
    }

    public static function renderUifBody(array $runData): string
    {
        if (!self::isUifEnabled($runData)) {
            return '<script>window.RadAdminUI = window.RadAdminUI || {}; window.RadAdminUI.getModal = window.RadAdminUI.getModal || function(el) { el = typeof el === "string" ? document.querySelector(el) : el; var bs = window["bootstrap"]; var Modal = bs && bs.Modal; return Modal && el ? Modal.getOrCreateInstance(el) : { show: function(){}, hide: function(){} }; };</script>' . "\n";
        }

        $assetUrl = self::radAssetsUrl($runData);
        if ($assetUrl === '') {
            return '';
        }

        return '<script>if (window.BatoiUIF && typeof window.BatoiUIF.autoStart === "function") { window.BatoiUIF.autoStart(); } if (window.RadAdminUI && typeof window.RadAdminUI.initBootstrapBehavior === "function") { window.RadAdminUI.initBootstrapBehavior(document); }</script>' . "\n";
    }

    public static function monacoBaseUrl(array $runData): string
    {
        $configuredUrl = trim((string)($runData['config']['sys']['rad_admin_monaco_base_url'] ?? ''));
        if ($configuredUrl !== '') {
            return $configuredUrl;
        }

        $assetUrl = self::radAssetsUrl($runData);
        if ($assetUrl !== '') {
            return $assetUrl . '/monaco/min/vs';
        }

        return '';
    }

    public static function renderMonacoLoaderConfig(array $runData): string
    {
        $baseUrl = self::monacoBaseUrl($runData);
        if ($baseUrl === '') {
            return '';
        }

        return '<script>window.RAD_ADMIN_MONACO_BASE_URL = "' . self::escapeJs($baseUrl) . '";</script>' . "\n";
    }

    private static function radAssetsUrl(array $runData): string
    {
        $routeAssetUrl = trim((string)($runData['route']['rad_assets_url'] ?? ''));
        if ($routeAssetUrl !== '') {
            return rtrim($routeAssetUrl, '/');
        }

        $baseUrl = trim((string)($runData['config']['sys']['base_url'] ?? ''));
        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/') . '/rad-admin/assets';
        }

        return '';
    }

    private static function isTruthy($value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['1', 'y', 'yes', 'true', 'on', 'enabled'], true);
    }

    private static function renderAdminChartHelpers(): string
    {
        return <<<'HTML'
<script>
(function(){
    if (window.RadAdminCharts || !window.BatoiUIF || typeof window.BatoiUIF.renderChart !== 'function') {
        return;
    }
    function chartData(config) {
        var labels = (config.data && config.data.labels) || [];
        var datasets = (config.data && config.data.datasets) || [];
        if (!datasets.length) {
            return [];
        }
        if (datasets.length === 1) {
            var values = datasets[0].data || [];
            return labels.map(function(label, index) {
                return { label: String(label), value: Number(values[index] || 0) };
            });
        }
        return labels.map(function(label, index) {
            var values = {};
            datasets.forEach(function(dataset) {
                values[String(dataset.label || 'Series')] = Number((dataset.data || [])[index] || 0);
            });
            return { label: String(label), values: values };
        });
    }
    function chartType(config) {
        var type = config.type || (config.data && config.data.datasets && config.data.datasets[0] && config.data.datasets[0].type) || 'bar';
        if (type === 'doughnut') {
            return 'donut';
        }
        if (type === 'line') {
            return 'line';
        }
        if (type === 'pie') {
            return 'pie';
        }
        if (config.data && config.data.datasets && config.data.datasets.length > 1) {
            return 'grouped-bar';
        }
        return 'bar';
    }
    function svgDataUrl(source) {
        var svg = source && source.tagName && source.tagName.toLowerCase() === 'svg' ? source : source && source.querySelector ? source.querySelector('svg') : null;
        if (!svg || typeof XMLSerializer === 'undefined') {
            return '';
        }
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(svg));
    }
    function render(target, config) {
        var source = target && target.getContext ? target : (target && target.canvas ? target.canvas : target);
        var host = document.createElement('div');
        var data = chartData(config || {});
        var options = {
            type: chartType(config || {}),
            legend: true,
            table: false,
            height: source ? Number(source.getAttribute('height') || 220) : 220
        };
        if (source && source.parentNode) {
            source.parentNode.replaceChild(host, source);
        }
        host.className = 'rad-uif-chart';
        host.innerHTML = window.BatoiUIF.renderChart(data, options);
        var instance = {
            el: host,
            canvas: host.querySelector('svg') || host,
            data: data,
            options: options,
            destroy: function() { host.remove(); },
            update: function() { host.innerHTML = window.BatoiUIF.renderChart(this.data, this.options); },
            toDataURL: function() { return svgDataUrl(host); }
        };
        if (instance.canvas && typeof instance.canvas.toDataURL !== 'function') {
            instance.canvas.toDataURL = function() {
                return svgDataUrl(host);
            };
        }
        return instance;
    }
    window.RadAdminCharts = {
        render: render,
        toDataURL: function(instance) {
            return instance && typeof instance.toDataURL === 'function' ? instance.toDataURL() : svgDataUrl(instance && instance.el ? instance.el : instance);
        }
    };
    window.RadAdminUI = window.RadAdminUI || {};
    window.RadAdminUI.showToast = function(message, type) {
        if (window.BatoiUIF && typeof window.BatoiUIF.showToast === 'function') {
            window.BatoiUIF.showToast(message || 'Done.', { type: type || 'success' });
            return;
        }
        var fallback = document.createElement('div');
        fallback.className = 'alert alert-' + (type || 'success');
        fallback.textContent = message || 'Done.';
        document.body.appendChild(fallback);
        setTimeout(function() { fallback.remove(); }, 1800);
    };
    window.RadAdminUI.initTooltips = function(root) {
        if (!window.BatoiUIF || !window.BatoiUIF.tooltip || typeof window.BatoiUIF.tooltip.init !== 'function') {
            return;
        }
        Array.from((root || document).querySelectorAll('[data-uif="tooltip"], [data-bs-toggle="tooltip"]')).forEach(function(el) {
            if (el.dataset.radTooltipReady === '1') {
                return;
            }
            el.dataset.radTooltipReady = '1';
            el.dataset.uif = 'tooltip';
            window.BatoiUIF.tooltip.init(el);
        });
    };
    function initComponent(el) {
        if (!el || !window.BatoiUIF || typeof window.BatoiUIF.initComponent !== 'function' || el.dataset.radUifReady === '1') {
            return;
        }
        el.dataset.radUifReady = '1';
        window.BatoiUIF.initComponent(el);
    }
    function normalizeTarget(source) {
        return source.getAttribute('data-uif-target') || source.getAttribute('data-bs-target') || source.getAttribute('href') || '';
    }
    function prepareModal(modalEl) {
        if (!modalEl) {
            return null;
        }
        modalEl.dataset.uif = 'modal';
        modalEl.setAttribute('aria-hidden', modalEl.getAttribute('aria-hidden') || 'true');
        var dialog = modalEl.querySelector('.modal-dialog') || modalEl;
        dialog.dataset.uifRole = 'dialog';
        modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(button) {
            button.dataset.uifAction = 'close';
        });
        initComponent(modalEl);
        return modalEl;
    }
    function prepareCollapse(targetEl) {
        if (!targetEl) {
            return null;
        }
        targetEl.dataset.uif = 'collapse';
        if (!targetEl.classList.contains('show') && !targetEl.hidden) {
            targetEl.hidden = true;
        }
        targetEl.dataset.uifState = targetEl.classList.contains('show') ? 'expanded' : 'collapsed';
        initComponent(targetEl);
        return targetEl;
    }
    function prepareDropdown(trigger) {
        var root = trigger.closest('.dropdown') || trigger.parentElement;
        var panel = root ? root.querySelector('.dropdown-menu') : null;
        if (!root || !panel) {
            return;
        }
        root.dataset.uif = 'dropdown';
        trigger.dataset.uifRole = 'trigger';
        panel.dataset.uifRole = 'panel';
        Array.from(panel.querySelectorAll('.dropdown-item')).forEach(function(item) {
            item.dataset.uifRole = 'item';
        });
        initComponent(root);
    }
    function prepareTabs(root) {
        if (!root) {
            return;
        }
        root.dataset.uif = 'tabs';
        var tabs = Array.from(root.querySelectorAll('[data-bs-toggle="tab"]'));
        tabs.forEach(function(tab) {
            var targetSelector = tab.getAttribute('data-bs-target') || tab.getAttribute('href');
            var panel = targetSelector ? document.querySelector(targetSelector) : null;
            tab.dataset.uifRole = 'tab';
            if (panel) {
                panel.dataset.uifRole = 'tabpanel';
            }
        });
        initComponent(root);
        if (root.dataset.radTabsBridge !== '1') {
            root.dataset.radTabsBridge = '1';
            root.addEventListener('click', function(event) {
                var tab = event.target instanceof HTMLElement ? event.target.closest('[data-bs-toggle="tab"]') : null;
                if (!tab || !root.contains(tab)) {
                    return;
                }
                var targetSelector = tab.getAttribute('data-bs-target') || tab.getAttribute('href');
                var panel = targetSelector ? document.querySelector(targetSelector) : null;
                event.preventDefault();
                tabs.forEach(function(item) {
                    var itemSelector = item.getAttribute('data-bs-target') || item.getAttribute('href');
                    var itemPanel = itemSelector ? document.querySelector(itemSelector) : null;
                    var active = item === tab;
                    item.classList.toggle('active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                    if (itemPanel) {
                        itemPanel.classList.toggle('active', active);
                        itemPanel.classList.toggle('show', active);
                        itemPanel.hidden = !active;
                    }
                });
                if (panel) {
                    tab.dispatchEvent(new Event('shown.bs.tab'));
                }
            });
        }
    }
    window.RadAdminUI.getModal = function(modalEl) {
        modalEl = typeof modalEl === 'string' ? document.querySelector(modalEl) : modalEl;
        prepareModal(modalEl);
        return {
            show: function() {
                if (modalEl) {
                    window.BatoiUIF.openOverlay(modalEl, { modal: true, restoreFocus: true });
                    modalEl.hidden = false;
                    modalEl.dataset.uifState = 'open';
                    modalEl.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('uif-modal-open');
                    modalEl.dispatchEvent(new Event('shown.bs.modal'));
                }
            },
            hide: function() {
                if (modalEl) {
                    window.BatoiUIF.closeOverlay(modalEl);
                    modalEl.dataset.uifState = 'closed';
                    modalEl.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('uif-modal-open');
                    modalEl.dispatchEvent(new Event('hidden.bs.modal'));
                }
            }
        };
    };
    window.RadAdminUI.openModal = function(modalEl) {
        window.RadAdminUI.getModal(modalEl).show();
    };
    window.RadAdminUI.closeModal = function(modalEl) {
        window.RadAdminUI.getModal(modalEl).hide();
    };
    window.RadAdminUI.initBootstrapBehavior = function(root) {
        root = root || document;
        window.RadAdminUI.initTooltips(root);
        Array.from(root.querySelectorAll('[data-bs-toggle="modal"]')).forEach(function(trigger) {
            var target = document.querySelector(normalizeTarget(trigger));
            if (!prepareModal(target)) {
                return;
            }
            trigger.dataset.uifAction = 'open';
            trigger.dataset.uifTarget = normalizeTarget(trigger);
        });
        Array.from(root.querySelectorAll('[data-bs-dismiss="modal"]')).forEach(function(button) {
            button.dataset.uifAction = 'close';
        });
        Array.from(root.querySelectorAll('[data-bs-toggle="collapse"]')).forEach(function(trigger) {
            var targetSelector = normalizeTarget(trigger);
            var target = targetSelector ? document.querySelector(targetSelector) : null;
            if (!prepareCollapse(target)) {
                return;
            }
            trigger.dataset.uifAction = 'toggle';
            trigger.dataset.uifTarget = targetSelector;
        });
        Array.from(root.querySelectorAll('[data-bs-toggle="dropdown"]')).forEach(function(trigger) {
            prepareDropdown(trigger);
        });
        var tabRoots = new Set();
        Array.from(root.querySelectorAll('[data-bs-toggle="tab"]')).forEach(function(tab) {
            var nav = tab.closest('.nav-tabs, .nav-pills, [role="tablist"]') || tab.parentElement;
            var candidate = nav && nav.parentElement && nav.parentElement.querySelector('.tab-content') ? nav.parentElement : nav;
            if (candidate) {
                tabRoots.add(candidate);
            }
        });
        tabRoots.forEach(prepareTabs);
        Array.from(root.querySelectorAll('[data-bs-dismiss="alert"], [data-bs-dismiss="toast"]')).forEach(function(button) {
            button.dataset.uifAction = 'close';
            var host = button.closest('.alert, .toast');
            if (host && !host.dataset.uif) {
                host.dataset.uif = host.classList.contains('toast') ? 'toast' : 'alert';
                initComponent(host);
            }
        });
    };
})();
</script>
HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function escapeJs(string $value): string
    {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value);
    }
}
