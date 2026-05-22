<?php
// -- Helper: result badge cell
function ss_cell(string $result): string {
    $map = [
        'pass'          => ['bg-success text-white',         'bi-check-lg',         'Pass'],
        'fail'          => ['bg-danger text-white',          'bi-x-lg',             'Fail'],
        'partial'       => ['bg-warning text-dark',          'bi-exclamation-lg',   'Partial'],
        'missing'       => ['bg-secondary text-white',       'bi-dash-lg',          'Missing'],
        'unverifiable'  => ['bg-light text-muted border',    'bi-question-lg',      '?'],
    ];
    [$cls, $icon, $lbl] = $map[$result] ?? ['bg-light text-muted border', 'bi-question-lg', $result];
    return "<span class=\"badge {$cls}\"><i class=\"bi {$icon} me-1\"></i>{$lbl}</span>";
}

function ss_overall_badge(string $result): string {
    $map = [
        'pass'         => 'success',
        'partial'      => 'warning',
        'fail'         => 'danger',
        'unverifiable' => 'secondary',
    ];
    $cls = $map[$result] ?? 'secondary';
    return "<span class=\"badge bg-{$cls} fs-6\">" . ucfirst($result) . "</span>";
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= site_url('projects') ?>">Projects</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('projects/' . $project['id']) ?>"><?= htmlspecialchars($project['name']) ?></a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('submittals/' . $submittal['id']) ?>"><?= htmlspecialchars($submittal['name']) ?></a></li>
        <li class="breadcrumb-item active">Compliance Matrix</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Compliance Matrix</h1>
        <div class="text-muted small"><?= htmlspecialchars($submittal['name']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('submittals/' . $submittal['id'] . '/review') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-clipboard-check me-1"></i>Review Queue
        </a>
        <a href="<?= site_url('submittals/' . $submittal['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<?php if ( ! $has_results): ?>
<div class="alert alert-info">
    <i class="bi bi-hourglass-split me-2"></i>
    Matching has not completed yet. Check back in a moment — the worker runs every minute.
</div>
<?php else: ?>

<?php
$resultLegend = [
    'pass'         => ['success', 'All required attributes match'],
    'partial'      => ['warning', 'Some attributes match, some do not'],
    'fail'         => ['danger',  'No required attributes match'],
    'missing'      => ['secondary','Attribute not found in cut sheet'],
    'unverifiable' => ['light',   'Value is null on spec or cut sheet'],
];
?>
<div class="d-flex gap-3 flex-wrap mb-4 small">
    <?php foreach ($resultLegend as $r => [$cls, $desc]): ?>
    <span><?= ss_cell($r) ?> <?= $desc ?></span>
    <?php endforeach; ?>
</div>

<?php foreach ($matrix as $category => $catData):
    $catalogs = array_keys($catData['catalogs']);
?>
<div class="card mb-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-box me-2"></i><?= htmlspecialchars($category) ?>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th class="text-muted fw-normal" style="min-width:180px">Attribute</th>
                    <?php foreach ($catalogs as $catNum): ?>
                    <th class="text-center" style="min-width:130px">
                        <div class="font-monospace fw-semibold"><?= htmlspecialchars($catNum) ?></div>
                        <div class="mt-1"><?= ss_overall_badge($catData['overall'][$catNum]) ?></div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($catData['attrs'] as $attrKey => $attrLabel): ?>
            <tr>
                <td class="text-muted">
                    <?php if (strpos($attrKey, '_listing_') === 0): ?>
                        <i class="bi bi-patch-check me-1 text-info"></i><?= htmlspecialchars($attrLabel) ?>
                    <?php else: ?>
                        <code class="small"><?= htmlspecialchars($attrLabel) ?></code>
                    <?php endif; ?>
                </td>
                <?php foreach ($catalogs as $catNum):
                    $cell = $catData['cells'][$attrKey][$catNum] ?? null;
                    if ($cell === null):
                ?>
                <td class="text-center text-muted">—</td>
                <?php else: ?>
                <td class="text-center" title="Spec: <?= htmlspecialchars((string)($cell['spec_value'] ?? '')) ?> | Product: <?= htmlspecialchars((string)($cell['product_value'] ?? '')) ?>">
                    <?= ss_cell($cell['result']) ?>
                    <div class="mt-1" style="font-size:.7rem;line-height:1.2">
                        <?php if (isset($cell['spec_value']) && isset($cell['product_value']) && $cell['spec_value'] !== null): ?>
                        <span class="text-muted">
                            <abbr title="Spec value">S:</abbr> <?= htmlspecialchars((string) $cell['spec_value']) ?>&nbsp;
                            <abbr title="Product value">P:</abbr> <?= htmlspecialchars((string) ($cell['product_value'] ?? '—')) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endif; endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<div class="mt-3 d-flex gap-2">
    <a href="<?= site_url('submittals/' . $submittal['id'] . '/review') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-clipboard-check me-1"></i>Go to Review Queue
    </a>
    <a href="<?= site_url('submittals/' . $submittal['id']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Submittal
    </a>
</div>

<?php endif; ?>
