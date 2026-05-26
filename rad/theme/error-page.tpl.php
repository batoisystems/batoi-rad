<?php
$baseUrl = $this->runData['route']['home_url'] ?? $this->runData['config']['sys']['base_url'] ?? '/';
$rawCode = $this->runData['route']['error_code'] ?? 500;
$errorCode = htmlspecialchars((string)$rawCode, ENT_QUOTES, 'UTF-8');
$errorMessage = htmlspecialchars($this->runData['route']['error_message'] ?? 'Something went wrong.', ENT_QUOTES, 'UTF-8');
$requestPath = htmlspecialchars($this->runData['route']['error_path'] ?? '', ENT_QUOTES, 'UTF-8');
$brand = htmlspecialchars($this->runData['config']['sys']['project_title'] ?? 'RAD', ENT_QUOTES, 'UTF-8');
$tone = $this->runData['route']['error_tone'] ?? 'danger';
$meta = $this->runData['route']['error_meta'] ?? [];
$family = ($rawCode >= 500) ? 'Server Error' : (($rawCode >= 400) ? 'Client Error' : 'Notice');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Unexpected error">
    <title>Error <?php echo $errorCode; ?></title>
    <style>
        :root {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            color-scheme: light dark;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: radial-gradient(circle at 25% 25%, #f3f7ff, #f8fbff 45%, #fbfcff);
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 48px;
            font-size: 0.95rem;
            color: #0f1f3d;
        }
        .brand {
            font-weight: 600;
            letter-spacing: 0.08em;
        }
        .error-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .error-card {
            width: min(560px, 92vw);
            border-radius: 32px;
            background: #fff;
            padding: 56px 48px;
            text-align: center;
            box-shadow: 0 45px 140px rgba(11, 52, 120, 0.12);
            position: relative;
            isolation: isolate;
        }
        .error-card::before {
            content: '';
            position: absolute;
            inset: -60px;
            background: radial-gradient(circle, rgba(15, 98, 254, 0.16) 0%, rgba(15, 98, 254, 0) 65%);
            z-index: -1;
        }
        .error-card h1 {
            font-size: 3.2rem;
            margin-bottom: 0.25rem;
            color: #0f1f3d;
        }
        .error-card p {
            margin: 0;
            color: #50607a;
        }
        .error-card .badge {
            display: inline-block;
            padding: 0.25rem 0.85rem;
            border-radius: 999px;
            font-size: 0.75rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            background: #eef1ff;
            color: #243464;
            margin-bottom: 1rem;
        }
        .error-card[data-tone="warning"] .badge {
            background: #fff4e5;
            color: #92400e;
        }
        .error-card[data-tone="info"] .badge {
            background: #e0f2fe;
            color: #0c4a6e;
        }
        .error-card code {
            display: block;
            margin: 1.5rem auto 0;
            padding: 0.85rem 1.2rem;
            background: #eef4ff;
            border-radius: 14px;
            font-size: 0.95rem;
            color: #1c263f;
            letter-spacing: 0.04em;
        }
        .error-meta {
            margin: 1.5rem auto 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
        }
        .error-meta li {
            text-align: left;
            padding: 0.75rem 0.9rem;
            border-radius: 14px;
            background: #f1f4fb;
            font-size: 0.85rem;
            color: #1f2d4a;
        }
        .error-meta span {
            display: block;
            font-size: 0.7rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8a96b2;
            margin-bottom: 0.2rem;
        }
        .btn-row {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1.8rem;
        }
        .error-card a.btn-home,
        .error-card button.btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.85rem 1.5rem;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
        }
        .error-card a.btn-home {
            background: #0f62fe;
            color: #fff;
            box-shadow: 0 20px 35px rgba(15, 98, 254, 0.18);
        }
        .error-card button.btn-secondary {
            background: #e7edf8;
            color: #0f1f3d;
        }
        footer {
            text-align: center;
            padding: 24px;
            color: #6d7b92;
            font-size: 0.85rem;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #050914; }
            .error-card { background: #0f1729; color: #e3e7f3; }
            .error-card code { background: #1b2642; color: #f6f8ff; }
            .error-meta li { background: #111f3a; color: #dbe4ff; }
            .error-card button.btn-secondary { background: #1f2d47; color: #f5f8ff; }
        }
    </style>
</head>

<body>
    <header>
        <div class="brand"><?php echo $brand; ?></div>
        <div><?php echo date('Y'); ?></div>
    </header>
    <div class="error-wrapper">
        <div class="error-card" data-tone="<?php echo htmlspecialchars($tone, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="badge"><?php echo htmlspecialchars($family, ENT_QUOTES, 'UTF-8'); ?></span>
            <h1><?php echo $errorCode; ?></h1>
            <p><?php echo $errorMessage; ?></p>
            <?php if ($requestPath): ?>
                <code><?php echo $requestPath; ?></code>
            <?php endif; ?>
            <?php if (!empty($meta)): ?>
                <ul class="error-meta">
                    <?php if (!empty($meta['method'])): ?>
                        <li><span>Method</span><?php echo htmlspecialchars($meta['method'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($meta['host'])): ?>
                        <li><span>Host</span><?php echo htmlspecialchars($meta['host'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($meta['timestamp'])): ?>
                        <li><span>Timestamp</span><?php echo date('Y-m-d H:i:s', (int)$meta['timestamp']); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($meta['reference'])): ?>
                        <li><span>Reference</span><?php echo htmlspecialchars($meta['reference'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($meta['location'])): ?>
                        <li><span>Source</span><?php echo htmlspecialchars($meta['location'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($meta['referer'])): ?>
                        <li><span>Referer</span><?php echo htmlspecialchars($meta['referer'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($meta['user_id'])): ?>
                        <li><span>User ID</span><?php echo htmlspecialchars($meta['user_id'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
            <div class="btn-row">
                <button type="button" class="btn-secondary" onclick="window.history.back();">
                    &larr; Back
                </button>
                <a class="btn-home" href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>">&larr; Back to Home</a>
            </div>
        </div>
    </div>
    <footer>&copy; <?php echo date('Y'); ?> <?php echo parse_url($baseUrl, PHP_URL_HOST); ?></footer>
</body>

</html>
