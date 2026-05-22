<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Dashboard</h1>
        <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($this->tenantcontext->userName()) ?></p>
    </div>
    <a href="<?= site_url('projects/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Project
    </a>
</div>

<?php if (empty($projects)): ?>

<div class="card text-center py-5">
    <div class="card-body">
        <i class="bi bi-folder2-open display-4 text-muted mb-3 d-block"></i>
        <h5 class="fw-bold">No projects yet</h5>
        <p class="text-muted">Create your first project to start building submittal packages.</p>
        <a href="<?= site_url('projects/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Create your first project
        </a>
    </div>
</div>

<?php else: ?>

<div class="row g-3">
    <?php foreach ($projects as $project): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="card-title mb-0 fw-semibold">
                        <a href="<?= site_url('projects/' . $project['id']) ?>" class="text-decoration-none text-dark">
                            <?= htmlspecialchars($project['name']) ?>
                        </a>
                    </h5>
                    <span class="badge bg-success status-badge">Active</span>
                </div>
                <?php if ($project['project_number']): ?>
                <p class="text-muted small mb-1">#<?= htmlspecialchars($project['project_number']) ?></p>
                <?php endif; ?>
                <?php if ($project['gc_name']): ?>
                <p class="text-muted small mb-1"><i class="bi bi-building me-1"></i><?= htmlspecialchars($project['gc_name']) ?></p>
                <?php endif; ?>
                <?php if ($project['location']): ?>
                <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($project['location']) ?></p>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                <small class="text-muted">Created <?= date('M j, Y', strtotime($project['created_at'])) ?></small>
                <a href="<?= site_url('projects/' . $project['id']) ?>" class="btn btn-sm btn-outline-primary">Open</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
