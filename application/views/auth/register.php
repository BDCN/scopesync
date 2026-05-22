<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-6 col-xl-5">
        <div class="card mt-4">
            <div class="card-body p-4">
                <h4 class="card-title mb-1 fw-bold">Create your ScopeSync account</h4>
                <p class="text-muted small mb-4">14-day free trial, no credit card required</p>

                <?php if ( ! empty($error)): ?>
                <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php echo form_open('register'); ?>

                    <div class="mb-3">
                        <label for="company_name" class="form-label fw-semibold">Company Name</label>
                        <input type="text" id="company_name" name="company_name"
                               class="form-control <?= isset($errors['company_name']) ? 'is-invalid' : '' ?>"
                               value="<?= set_value('company_name') ?>" placeholder="Acme Electric Co." required autofocus>
                        <?php if (isset($errors['company_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['company_name']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="your_name" class="form-label fw-semibold">Your Name</label>
                        <input type="text" id="your_name" name="your_name"
                               class="form-control <?= isset($errors['your_name']) ? 'is-invalid' : '' ?>"
                               value="<?= set_value('your_name') ?>" placeholder="Jane Smith" required>
                        <?php if (isset($errors['your_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['your_name']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Work Email</label>
                        <input type="email" id="email" name="email"
                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               value="<?= set_value('email') ?>" placeholder="jane@acme-electric.com" required>
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <input type="password" id="password" name="password"
                                   class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                   minlength="8" required>
                            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                        </div>
                        <div class="col">
                            <label for="password_confirm" class="form-label fw-semibold">Confirm Password</label>
                            <input type="password" id="password_confirm" name="password_confirm"
                                   class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                                   required>
                            <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password_confirm']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                    </div>

                <?php echo form_close(); ?>

                <hr>
                <p class="text-center small mb-0">Already have an account? <a href="<?= site_url('login') ?>">Sign in</a></p>
            </div>
        </div>
    </div>
</div>
