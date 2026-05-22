<div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-5 col-xl-4">
        <div class="card mt-4">
            <div class="card-body p-4">
                <h4 class="card-title mb-1 fw-bold">Set a new password</h4>
                <p class="text-muted small mb-4">Choose a strong password of at least 8 characters.</p>

                <?php if ( ! empty($error)): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php echo form_open('reset-password/' . htmlspecialchars($token)); ?>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">New Password</label>
                        <input type="password" id="password" name="password"
                               class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                               minlength="8" required autofocus>
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirm" class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" id="password_confirm" name="password_confirm"
                               class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                               required>
                        <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password_confirm']) ?></div><?php endif; ?>
                    </div>

                    <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>

                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
