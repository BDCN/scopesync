<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= site_url('projects') ?>">Projects</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('projects/' . $project['id']) ?>"><?= htmlspecialchars($project['name']) ?></a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-transparent fw-semibold">Edit Project</div>
            <div class="card-body p-4">

                <?php if ( ! empty($error)): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php echo form_open('projects/' . $project['id'] . '/edit'); ?>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Project Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               value="<?= set_value('name', htmlspecialchars($project['name'])) ?>" required autofocus>
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="project_number" class="form-label fw-semibold">Project Number</label>
                        <input type="text" id="project_number" name="project_number"
                               class="form-control"
                               value="<?= set_value('project_number', htmlspecialchars($project['project_number'] ?? '')) ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="gc_name" class="form-label fw-semibold">General Contractor</label>
                            <input type="text" id="gc_name" name="gc_name"
                                   class="form-control"
                                   value="<?= set_value('gc_name', htmlspecialchars($project['gc_name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="architect_name" class="form-label fw-semibold">Architect</label>
                            <input type="text" id="architect_name" name="architect_name"
                                   class="form-control"
                                   value="<?= set_value('architect_name', htmlspecialchars($project['architect_name'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label fw-semibold">Location</label>
                        <input type="text" id="location" name="location"
                               class="form-control"
                               value="<?= set_value('location', htmlspecialchars($project['location'] ?? '')) ?>">
                    </div>

                    <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?= site_url('projects/' . $project['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                <?php echo form_close(); ?>

            </div>
        </div>

        <div class="card mt-4 border-danger">
            <div class="card-header bg-transparent text-danger fw-semibold">Danger Zone</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Archiving removes this project from your active list. It can't be un-archived from the UI yet.</p>
                <?php echo form_open('projects/' . $project['id'] . '/archive'); ?>
                    <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('Archive this project? It will no longer appear in your active list.')">
                        <i class="bi bi-archive me-1"></i>Archive Project
                    </button>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
