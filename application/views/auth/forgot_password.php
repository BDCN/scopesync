<div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-5 col-xl-4">
        <div class="card mt-4">
            <div class="card-body p-4">
                <h4 class="card-title mb-1 fw-bold">Reset your password</h4>
                <p class="text-muted small mb-4">Enter your email and we'll send a reset link.</p>

                <?php if ( ! empty($error)): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php echo form_open('forgot-password'); ?>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email</label>
                        <input type="email" id="email" name="email"
                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               value="<?= set_value('email') ?>" required autofocus>
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                    </div>

                    <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">Send Reset Link</button>
                    </div>

                <?php echo form_close(); ?>

                <hr>
                <p class="text-center small mb-0"><a href="<?= site_url('login') ?>"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a></p>
            </div>
        </div>
    </div>
</div>
