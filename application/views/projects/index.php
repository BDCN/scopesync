<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 fw-bold">Projects</h1>
    <a href="<?= site_url('projects/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Project
    </a>
</div>

<?php if (empty($projects)): ?>

<div class="card text-center py-5">
    <div class="card-body">
        <i class="bi bi-folder2-open display-4 text-muted mb-3 d-block"></i>
        <h5 class="fw-bold">No active projects</h5>
        <p class="text-muted">Create a project to start building submittal packages.</p>
        <a href="<?= site_url('projects/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Create a project
        </a>
    </div>
</div>

<?php else: ?>

<div class="table-responsive">
    <table class="table table-hover align-middle bg-white rounded">
        <thead class="table-light">
            <tr>
                <th>Project</th>
                <th>Number</th>
                <th>General Contractor</th>
                <th>Location</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $p): ?>
            <tr>
                <td class="fw-semibold">
                    <a href="<?= site_url('projects/' . $p['id']) ?>" class="text-decoration-none">
                        <?= htmlspecialchars($p['name']) ?>
                    </a>
                </td>
                <td class="text-muted small"><?= $p['project_number'] ? '#' . htmlspecialchars($p['project_number']) : '—' ?></td>
                <td><?= $p['gc_name'] ? htmlspecialchars($p['gc_name']) : '—' ?></td>
                <td><?= $p['location'] ? htmlspecialchars($p['location']) : '—' ?></td>
                <td class="text-muted small"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                <td class="text-end">
                    <a href="<?= site_url('projects/' . $p['id']) ?>" class="btn btn-sm btn-outline-primary me-1">Open</a>
                    <a href="<?= site_url('projects/' . $p['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>
