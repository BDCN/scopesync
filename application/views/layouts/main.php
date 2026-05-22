<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ScopeSync' : 'ScopeSync' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --ss-primary: <?= isset($brand_color) ? htmlspecialchars($brand_color) : '#1A73E8' ?>;
        }
        body { background: #f8f9fa; }
        .navbar-brand { font-weight: 700; letter-spacing: -0.5px; }
        .navbar-ss { background: var(--ss-primary) !important; }
        .navbar-ss .navbar-brand,
        .navbar-ss .nav-link,
        .navbar-ss .navbar-text { color: #fff !important; }
        .navbar-ss .nav-link:hover { opacity: 0.85; }
        .sidebar-link { color: #495057; text-decoration: none; display: block; padding: 6px 12px; border-radius: 6px; }
        .sidebar-link:hover, .sidebar-link.active { background: #e8f0fe; color: var(--ss-primary); }
        .card { border: 1px solid #e0e0e0; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .status-badge { font-size: .75rem; }
        .breadcrumb-item + .breadcrumb-item::before { content: "/"; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-ss">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= site_url() ?>">
            <i class="bi bi-layers-fill me-1"></i>ScopeSync
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon" style="filter:invert(1)"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <?php if ($this->tenantcontext->isLoggedIn()): ?>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= site_url('projects') ?>">
                        <i class="bi bi-folder2-open me-1"></i>Projects
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($this->tenantcontext->userName()) ?>
                        <small class="opacity-75">(<?= htmlspecialchars($this->tenantcontext->name()) ?>)</small>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small"><?= htmlspecialchars($this->tenantcontext->userEmail()) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-right me-1"></i>Log out</a></li>
                    </ul>
                </li>
            </ul>
            <?php else: ?>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="<?= site_url('login') ?>">Log in</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('register') ?>">Sign up</a></li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container-fluid py-4 px-4">

    <?php if ($flash_success = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($flash_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($flash_error = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flash_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (ENVIRONMENT !== 'production' && $dev_link = $this->session->flashdata('dev_reset_link')): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <strong>[DEV]</strong> Password reset link (SMTP not configured):
        <a href="<?= htmlspecialchars($dev_link) ?>" class="alert-link"><?= htmlspecialchars($dev_link) ?></a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?= $content ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
