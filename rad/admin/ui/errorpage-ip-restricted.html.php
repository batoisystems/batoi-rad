<?php
$clientIp = $this->runData['route']['client_ip'] ?? '';
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '/rad-admin';
?>
<!-- Fallback styles to ensure the page renders even if local assets are blocked -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<style>
    body { background: #f8fafc; }
    .ip-card { max-width: 720px; }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 ip-card">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3 text-danger" style="font-size:1.5rem;"><i class="bi bi-shield-lock-fill"></i></div>
                        <div>
                            <h1 class="h4 mb-0">Access Restricted</h1>
                            <div class="text-muted">You are accessing from outside the trusted network.</div>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <div class="fw-semibold mb-1">Why am I seeing this?</div>
                        <div class="small text-muted">This RAD Admin instance only allows traffic from approved networks or VPN. Your request has been blocked for security.</div>
                    </div>
                    <dl class="row small mb-4">
                        <dt class="col-sm-4 text-muted">Your IP</dt>
                        <dd class="col-sm-8"><?php echo $clientIp ? htmlspecialchars($clientIp) : 'Unavailable'; ?></dd>
                        <dt class="col-sm-4 text-muted">Status</dt>
                        <dd class="col-sm-8">403 Forbidden</dd>
                    </dl>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo htmlspecialchars($radAdminUrl); ?>/home/view" class="btn btn-outline-secondary">
                            <i class="bi bi-house-door me-1"></i>Home
                        </a>
                        <button class="btn btn-primary" onclick="location.reload();">
                            <i class="bi bi-arrow-repeat me-1"></i>Try again
                        </button>
                        <a href="mailto:office.corp@batoi.com" class="btn btn-link text-decoration-none">
                            <i class="bi bi-envelope me-1"></i>Contact administrator
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
