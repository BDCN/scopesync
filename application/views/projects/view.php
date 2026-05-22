<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= site_url('projects') ?>">Projects</a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($project['name']) ?></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold"><?= htmlspecialchars($project['name']) ?></h1>
        <div class="text-muted small d-flex gap-3 flex-wrap">
            <?php if ($project['project_number']): ?><span><i class="bi bi-hash me-1"></i><?= htmlspecialchars($project['project_number']) ?></span><?php endif; ?>
            <?php if ($project['gc_name']): ?><span><i class="bi bi-building me-1"></i><?= htmlspecialchars($project['gc_name']) ?></span><?php endif; ?>
            <?php if ($project['architect_name']): ?><span><i class="bi bi-pencil-square me-1"></i><?= htmlspecialchars($project['architect_name']) ?></span><?php endif; ?>
            <?php if ($project['location']): ?><span><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($project['location']) ?></span><?php endif; ?>
        </div>
    </div>
    <a href="<?= site_url('projects/' . $project['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm ms-3">
        <i class="bi bi-pencil me-1"></i>Edit
    </a>
</div>

<!-- Add Division -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-semibold">Divisions &amp; Submittals</h5>
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddDivision">
        <i class="bi bi-plus me-1"></i>Add Division
    </button>
</div>

<?php if (empty($divisions)): ?>

<div class="card text-center py-5 mb-4">
    <div class="card-body">
        <i class="bi bi-layout-three-columns display-5 text-muted mb-3 d-block"></i>
        <h6 class="fw-bold">No divisions yet</h6>
        <p class="text-muted small mb-3">Add a CSI MasterFormat division (e.g., Division 26 — Electrical) to organize your submittals.</p>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddDivision">
            <i class="bi bi-plus me-1"></i>Add First Division
        </button>
    </div>
</div>

<?php else: ?>

<?php foreach ($divisions as $div): ?>
<div class="card mb-3">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <span class="badge bg-primary me-2"><?= htmlspecialchars($div['code']) ?></span>
            <?= htmlspecialchars($div['name']) ?>
        </span>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#modalAddSubmittal"
                    data-division-id="<?= $div['id'] ?>"
                    data-division-name="<?= htmlspecialchars($div['name']) ?>">
                <i class="bi bi-plus me-1"></i>Add Submittal
            </button>
            <?php echo form_open('divisions/' . $div['id'] . '/delete', ['class' => 'd-inline']); ?>
                <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Delete this division? All submittals inside will also be deleted.')">
                    <i class="bi bi-trash"></i>
                </button>
            <?php echo form_close(); ?>
        </div>
    </div>

    <?php if (empty($div['submittals'])): ?>
    <div class="card-body py-3 text-center text-muted small">
        No submittal jobs yet — click <strong>Add Submittal</strong> to create one.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Submittal Name</th>
                    <th>Number</th>
                    <th>Spec Section</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($div['submittals'] as $sub): ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <a href="<?= site_url('submittals/' . $sub['id']) ?>" class="text-decoration-none">
                            <?= htmlspecialchars($sub['name']) ?>
                        </a>
                    </td>
                    <td class="text-muted small"><?= $sub['submittal_number'] ? htmlspecialchars($sub['submittal_number']) : '—' ?></td>
                    <td class="text-muted small"><?= $sub['spec_section'] ? htmlspecialchars($sub['spec_section']) : '—' ?></td>
                    <td>
                        <?php
                        $statusColors = [
                            'draft'       => 'secondary',
                            'uploading'   => 'info',
                            'extracting'  => 'warning',
                            'matching'    => 'warning',
                            'review'      => 'primary',
                            'assembling'  => 'primary',
                            'delivered'   => 'success',
                            'failed'      => 'danger',
                        ];
                        $statusColor = $statusColors[$sub['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $statusColor ?> status-badge"><?= ucfirst($sub['status']) ?></span>
                    </td>
                    <td class="text-muted small"><?= date('M j, Y', strtotime($sub['created_at'])) ?></td>
                    <td><a href="<?= site_url('submittals/' . $sub['id']) ?>" class="btn btn-sm btn-outline-primary">Open</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>


<!-- Modal: Add Division -->
<div class="modal fade" id="modalAddDivision" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?php echo form_open('projects/' . $project['id'] . '/divisions/create'); ?>
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">Add Division</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="div_code" class="form-label fw-semibold">CSI Division Code <span class="text-danger">*</span></label>
                    <input type="text" id="div_code" name="code" class="form-control" placeholder="e.g. 26" maxlength="16" required>
                    <div class="form-text">e.g. 26 for Electrical, 23 for Mechanical</div>
                </div>
                <div class="mb-3">
                    <label for="div_name" class="form-label fw-semibold">Division Name <span class="text-danger">*</span></label>
                    <input type="text" id="div_name" name="name" class="form-control" placeholder="e.g. Electrical" maxlength="255" required>
                </div>
                <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Division</button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>


<!-- Modal: Add Submittal -->
<div class="modal fade" id="modalAddSubmittal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?php echo form_open('', ['id' => 'formAddSubmittal']); ?>
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">Add Submittal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="sub_name" class="form-label fw-semibold">Submittal Name <span class="text-danger">*</span></label>
                    <input type="text" id="sub_name" name="name" class="form-control" placeholder="e.g. Lighting Fixtures" maxlength="255" required>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="sub_number" class="form-label fw-semibold">Submittal Number</label>
                        <input type="text" id="sub_number" name="submittal_number" class="form-control" placeholder="e.g. SUB-001" maxlength="64">
                    </div>
                    <div class="col-md-6">
                        <label for="sub_spec" class="form-label fw-semibold">Spec Section</label>
                        <input type="text" id="sub_spec" name="spec_section" class="form-control" placeholder="e.g. 26 51 13" maxlength="64">
                    </div>
                </div>
                <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Submittal</button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<script>
// Update the Add Submittal form action when modal opens with correct division
document.getElementById('modalAddSubmittal').addEventListener('show.bs.modal', function (e) {
    var btn  = e.relatedTarget;
    var divId = btn.getAttribute('data-division-id');
    document.getElementById('formAddSubmittal').action = '<?= site_url('divisions/') ?>' + divId + '/submittals/create';
});
</script>
