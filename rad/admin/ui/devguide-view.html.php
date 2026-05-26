<?php
$links = $this->runData['data']['devguide_links'] ?? [];
?>

<div class="row g-3">
    <?php foreach ($links as $card): ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-light text-dark me-2"><i class="bi <?php echo $card['icon']; ?>"></i></span>
                        <h2 class="h6 mb-0"><?php echo htmlspecialchars($card['title']); ?></h2>
                    </div>
                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($card['desc']); ?></p>
                    <div class="mt-auto">
                        <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($card['href']); ?>">Open</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
