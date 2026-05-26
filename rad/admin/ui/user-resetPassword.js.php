<script>
(function() {
    function copyText(text) {
        if (!text) {
            return Promise.reject();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        const input = document.createElement('textarea');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        return Promise.resolve();
    }

    function flashCopied(button) {
        if (!button) { return; }
        const original = button.getAttribute('data-original-label') || button.innerHTML;
        button.setAttribute('data-original-label', original);
        button.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
        button.disabled = true;
        setTimeout(function() {
            button.innerHTML = original;
            button.disabled = false;
        }, 1200);
    }

    document.querySelectorAll('[data-toggle-password]').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = button.getAttribute('data-toggle-password');
            const input = targetId ? document.getElementById(targetId) : null;
            if (!input) { return; }
            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    });

    const generateBtn = document.getElementById('generate-password');
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            const input = document.getElementById('reset-password-input');
            if (!input) { return; }
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*?';
            let value = '';
            for (let i = 0; i < 12; i++) {
                value += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            input.value = value;
            input.setAttribute('type', 'text');
            const toggle = document.querySelector('[data-toggle-password="reset-password-input"] i');
            if (toggle) {
                toggle.className = 'bi bi-eye-slash';
            }
        });
    }

    document.querySelectorAll('[data-copy-target]').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = button.getAttribute('data-copy-target');
            const input = targetId ? document.getElementById(targetId) : null;
            if (!input) { return; }
            copyText(input.value || '').then(function() {
                flashCopied(button);
            });
        });
    });

    const bundleBtn = document.getElementById('copy-access-bundle');
    if (bundleBtn) {
        bundleBtn.addEventListener('click', function() {
            const accessUrl = bundleBtn.getAttribute('data-access-url') || '';
            const username = bundleBtn.getAttribute('data-username') || '';
            const password = bundleBtn.getAttribute('data-password') || '';
            const bundle = 'Access URL: ' + accessUrl + '\nUsername: ' + username + '\nPassword: ' + password;
            copyText(bundle).then(function() {
                flashCopied(bundleBtn);
            });
        });
    }
})();
</script>
