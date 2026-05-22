<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= site_url('projects') ?>">Projects</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('projects/' . $project['id']) ?>"><?= htmlspecialchars($project['name']) ?></a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($submittal['name']) ?></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold"><?= htmlspecialchars($submittal['name']) ?></h1>
        <div class="text-muted small d-flex gap-3 flex-wrap">
            <span><i class="bi bi-layers me-1"></i>
                <span class="badge bg-primary"><?= htmlspecialchars($division['code']) ?></span>
                <?= htmlspecialchars($division['name']) ?>
            </span>
            <?php if ($submittal['submittal_number']): ?>
            <span><i class="bi bi-hash me-1"></i><?= htmlspecialchars($submittal['submittal_number']) ?></span>
            <?php endif; ?>
            <?php if ($submittal['spec_section']): ?>
            <span><i class="bi bi-file-text me-1"></i>Spec <?= htmlspecialchars($submittal['spec_section']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $statusColors = [
        'draft'      => 'secondary',
        'uploading'  => 'info',
        'extracting' => 'warning',
        'matching'   => 'warning',
        'review'     => 'primary',
        'assembling' => 'primary',
        'delivered'  => 'success',
        'failed'     => 'danger',
    ];
    $sc = $statusColors[$submittal['status']] ?? 'secondary';
    ?>
    <span class="badge bg-<?= $sc ?> fs-6 ms-3"><?= ucfirst($submittal['status']) ?></span>
</div>

<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-cloud-upload display-4 text-muted mb-3 d-block"></i>
        <h5 class="fw-bold">Upload Coming in Phase 3</h5>
        <p class="text-muted mb-0">
            File upload, Claude API extraction, and structured results will be built in Week 3.<br>
            For now, this submittal job is saved and ready.
        </p>
    </div>
</div>

<div class="mt-3">
    <a href="<?= site_url('projects/' . $project['id']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Project
    </a>
</div>
