<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= site_url('projects') ?>">Projects</a></li>
        <li class="breadcrumb-item active">New Project</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-transparent fw-semibold">New Project</div>
            <div class="card-body p-4">

                <?php if ( ! empty($error)): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php echo form_open('projects/create'); ?>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Project Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               value="<?= set_value('name') ?>" placeholder="e.g. Riverside Hospital Expansion" required autofocus>
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="project_number" class="form-label fw-semibold">Project Number</label>
                        <input type="text" id="project_number" name="project_number"
                               class="form-control <?= isset($errors['project_number']) ? 'is-invalid' : '' ?>"
                               value="<?= set_value('project_number') ?>" placeholder="e.g. 2024-082">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="gc_name" class="form-label fw-semibold">General Contractor</label>
                            <input type="text" id="gc_name" name="gc_name"
                                   class="form-control <?= isset($errors['gc_name']) ? 'is-invalid' : '' ?>"
                                   value="<?= set_value('gc_name') ?>" placeholder="e.g. Turner Construction">
                        </div>
                        <div class="col-md-6">
                            <label for="architect_name" class="form-label fw-semibold">Architect</label>
                            <input type="text" id="architect_name" name="architect_name"
                                   class="form-control <?= isset($errors['architect_name']) ? 'is-invalid' : '' ?>"
                                   value="<?= set_value('architect_name') ?>" placeholder="e.g. Gensler">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label fw-semibold">Location</label>
                        <input type="text" id="location" name="location"
                               class="form-control <?= isset($errors['location']) ? 'is-invalid' : '' ?>"
                               value="<?= set_value('location') ?>" placeholder="e.g. Chicago, IL">
                    </div>

                    <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Create Project</button>
                        <a href="<?= site_url('projects') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                <?php echo form_close(); ?>

            </div>
        </div>
    </div>
</div>
