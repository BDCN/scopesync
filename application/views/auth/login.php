<div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-5 col-xl-4">
        <div class="card mt-4">
            <div class="card-body p-4">
                <h4 class="card-title mb-1 fw-bold">Sign in to ScopeSync</h4>
                <p class="text-muted small mb-4">Submittal automation for subcontractors</p>

                <?php if ( ! empty($error)): ?>
                <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php echo form_open('login', ['autocomplete' => 'on']); ?>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email</label>
                        <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               value="<?= set_value('email') ?>" required autofocus>
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                    </div>

                    <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Sign in</button>
                    </div>

                <?php echo form_close(); ?>

                <hr>
                <div class="d-flex justify-content-between small">
                    <a href="<?= site_url('forgot-password') ?>">Forgot password?</a>
                    <a href="<?= site_url('register') ?>">Create an account</a>
                </div>
            </div>
        </div>
    </div>
</div>
