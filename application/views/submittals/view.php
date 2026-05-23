<?php
// -- Helper: format bytes
function ss_bytes(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b / 1024, 0) . ' KB';
    return $b . ' B';
}

// -- Helper: confidence badge
function ss_confidence_badge(?string $c): string {
    $map = ['high' => 'success', 'medium' => 'warning', 'low' => 'danger'];
    $cls = $map[$c] ?? 'secondary';
    $lbl = $c ? ucfirst($c) : 'N/A';
    return "<span class=\"badge bg-{$cls}\">{$lbl}</span>";
}

// -- Determine if any extraction is still pending/running (for auto-refresh)
$hasPending = FALSE;
foreach ($extractions_by_doc as $extr) {
    if ($extr && in_array($extr['status'], ['pending', 'running'], TRUE)) {
        $hasPending = TRUE;
        break;
    }
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= site_url('projects') ?>">Projects</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('projects/' . $project['id']) ?>"><?= htmlspecialchars($project['name']) ?></a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($submittal['name']) ?></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold"><?= htmlspecialchars($submittal['name']) ?></h1>
        <div class="text-muted small d-flex gap-3 flex-wrap">
            <span><i class="bi bi-layers me-1"></i>
                <span class="badge bg-primary"><?= htmlspecialchars($division['code']) ?></span>
                <?= htmlspecialchars($division['name']) ?>
            </span>
            <?php if ($submittal['submittal_number']): ?>
            <span><i class="bi bi-hash me-1"></i><?= htmlspecialchars($submittal['submittal_number']) ?></span>
            <?php endif; ?>
            <?php if ($submittal['spec_section']): ?>
            <span><i class="bi bi-file-text me-1"></i>Spec <?= htmlspecialchars($submittal['spec_section']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 ms-3">
        <?php
        $statusColors = [
            'draft'      => 'secondary',
            'uploading'  => 'info',
            'extracting' => 'warning',
            'matching'   => 'warning',
            'review'     => 'primary',
            'assembling' => 'primary',
            'delivered'  => 'success',
            'failed'     => 'danger',
        ];
        $sc = $statusColors[$submittal['status']] ?? 'secondary';
        ?>
        <span class="badge bg-<?= $sc ?> fs-6"><?= ucfirst($submittal['status']) ?></span>
        <?php if ($submittal['status'] === 'complete' && ! empty($submittal['output_path'])): ?>
        <a href="<?= site_url('submittals/' . $submittal['id'] . '/download') ?>" class="btn btn-success btn-sm">
            <i class="bi bi-download me-1"></i>Download PDF
        </a>
        <?php elseif ($submittal['status'] === 'assembling'): ?>
        <form method="post" action="<?= site_url('submittals/' . $submittal['id'] . '/assemble') ?>" class="d-inline" id="assemble-form"
              onsubmit="(function(btn){btn.disabled=true;btn.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\' role=\'status\'></span>Generating…';})(document.getElementById('assemble-btn'))">
            <input type="hidden" name="<?= $csrf_token_name ?>" value="<?= $csrf_hash ?>">
            <button type="submit" class="btn btn-primary btn-sm" id="assemble-btn">
                <i class="bi bi-file-earmark-pdf me-1"></i>Generate Submittal Package
            </button>
        </form>
        <?php endif; ?>
        <?php if (($submittal['matching_status'] ?? '') === 'complete'): ?>
        <a href="<?= site_url('submittals/' . $submittal['id'] . '/compliance') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-table me-1"></i>Compliance Matrix
        </a>
        <a href="<?= site_url('submittals/' . $submittal['id'] . '/review') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-clipboard-check me-1"></i>Review Queue
        </a>
        <?php elseif (($submittal['matching_status'] ?? '') === 'running'): ?>
        <span class="badge bg-warning text-dark">
            <span class="spinner-border spinner-border-sm me-1" style="width:.6rem;height:.6rem"></span>
            Matching…
        </span>
        <?php elseif (($submittal['matching_status'] ?? '') === 'failed'): ?>
        <span class="badge bg-danger">Matching failed</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($hasPending): ?>
<div class="alert alert-info d-flex align-items-center gap-2 py-2" id="extracting-notice">
    <div class="spinner-border spinner-border-sm" role="status"></div>
    <span>Extraction in progress — this page refreshes automatically every 10 seconds.</span>
</div>
<?php endif; ?>

<!-- =========================================================
     UPLOAD ZONE
     ========================================================= -->
<div class="card mb-4" id="upload-card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cloud-upload me-2"></i>Upload Documents</span>
        <small class="text-muted fw-normal">PDF only · max 50 MB each</small>
    </div>
    <div class="card-body">
        <div id="drop-zone"
             class="border border-2 border-dashed rounded-3 p-5 text-center text-muted mb-3"
             style="border-color: #ced4da !important; cursor: pointer;"
             onclick="document.getElementById('file-input').click()">
            <i class="bi bi-file-earmark-pdf display-5 mb-2 d-block"></i>
            <strong>Drag &amp; drop PDFs here</strong>
            <div class="small mt-1">or click to browse</div>
        </div>
        <input type="file" id="file-input" accept=".pdf,application/pdf" multiple class="d-none">

        <div id="upload-queue" class="d-none">
            <table class="table table-sm align-middle mb-2">
                <thead class="table-light">
                    <tr>
                        <th>File</th>
                        <th style="width:200px">Document type</th>
                        <th style="width:130px">Size</th>
                        <th style="width:220px">Status</th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody id="queue-body"></tbody>
            </table>
            <button class="btn btn-primary btn-sm" id="upload-all-btn">
                <i class="bi bi-upload me-1"></i>Upload All
            </button>
        </div>

        <div id="upload-global-error" class="alert alert-danger d-none mt-2"></div>
    </div>
</div>

<!-- =========================================================
     DOCUMENTS LIST
     ========================================================= -->
<div class="card mb-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-files me-2"></i>Documents
        <span class="badge bg-secondary ms-1"><?= count($documents) ?></span>
    </div>
    <?php if (empty($documents)): ?>
    <div class="card-body text-center text-muted py-4">
        <i class="bi bi-inbox display-6 d-block mb-2"></i>
        No documents uploaded yet.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                    <th>Extraction</th>
                    <th>Confidence</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($documents as $doc):
                $extr = $extractions_by_doc[(int) $doc['id']] ?? NULL;
                $extrStatus = $extr['status'] ?? 'none';
                $extrBadge  = [
                    'pending'   => '<span class="badge bg-secondary">Queued</span>',
                    'running'   => '<span class="badge bg-info text-dark"><span class="spinner-border spinner-border-sm me-1" style="width:.6rem;height:.6rem"></span>Running</span>',
                    'completed' => '<span class="badge bg-success">Done</span>',
                    'failed'    => '<span class="badge bg-danger">Failed</span>',
                    'none'      => '<span class="badge bg-light text-dark">—</span>',
                ];
            ?>
            <tr>
                <td>
                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                    <span class="text-break"><?= htmlspecialchars($doc['original_filename']) ?></span>
                </td>
                <td>
                    <?php if ($doc['doc_type'] === 'spec_section'): ?>
                        <span class="badge bg-primary-subtle text-primary">Spec Section</span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis">Cut Sheet</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted small"><?= ss_bytes((int) $doc['size_bytes']) ?></td>
                <td class="text-muted small"><?= htmlspecialchars(date('M j, g:i a', strtotime($doc['created_at']))) ?></td>
                <td><?= $extrBadge[$extrStatus] ?? $extrBadge['none'] ?></td>
                <td>
                    <?php if ($extr && $extr['status'] === 'completed'): ?>
                        <?= ss_confidence_badge($extr['confidence']) ?>
                    <?php elseif ($extr && $extr['status'] === 'failed'): ?>
                        <span class="text-danger small" title="<?= htmlspecialchars($extr['error_message'] ?? '') ?>">
                            <i class="bi bi-exclamation-circle"></i> Error
                        </span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <?php if ($extr && $extr['status'] === 'completed'): ?>
                        <a href="#results-doc-<?= $doc['id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>Results
                        </a>
                    <?php elseif ($extr && $extr['status'] === 'failed'): ?>
                        <button class="btn btn-outline-secondary btn-sm rerun-btn"
                                data-extraction-id="<?= $extr['id'] ?>"
                                data-csrf-name="<?= $csrf_token_name ?>"
                                data-csrf-hash="<?= $csrf_hash ?>">
                            <i class="bi bi-arrow-repeat me-1"></i>Re-run
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- =========================================================
     EXTRACTION RESULTS
     ========================================================= -->
<?php foreach ($documents as $doc):
    $extr = $extractions_by_doc[(int) $doc['id']] ?? NULL;
    if ( ! $extr || $extr['status'] !== 'completed' || empty($extr['structured_data'])) {
        continue;
    }
    $data = json_decode($extr['structured_data'], TRUE);
    if ( ! is_array($data)) {
        continue;
    }
    $isSpec = ($doc['doc_type'] === 'spec_section');
?>
<div class="card mb-4" id="results-doc-<?= $doc['id'] ?>">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="fw-semibold">
            <i class="bi bi-check-circle-fill text-success me-2"></i>
            <?= htmlspecialchars($doc['original_filename']) ?>
            <span class="badge bg-primary-subtle text-primary ms-1 fw-normal small">
                <?= $isSpec ? 'Spec Section' : 'Cut Sheet' ?>
            </span>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?= ss_confidence_badge($extr['confidence']) ?>
            <small class="text-muted">
                <?= number_format((int) $extr['input_tokens']) ?> in / <?= number_format((int) $extr['output_tokens']) ?> out tokens
            </small>
        </div>
    </div>
    <div class="card-body">

    <?php if ($isSpec): ?>

        <?php /* ---- SPEC SECTION RESULTS ---- */ ?>

        <?php $meta = $data['meta'] ?? []; ?>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <table class="table table-sm table-bordered mb-0">
                    <tbody>
                    <?php foreach ([
                        'Section'    => $meta['section_number'] ?? NULL,
                        'Title'      => $meta['section_title']  ?? NULL,
                        'Pages'      => $meta['document_page_count'] ?? NULL,
                        'Confidence' => isset($meta['extraction_confidence']) ? ucfirst($meta['extraction_confidence']) : NULL,
                    ] as $k => $v): if ($v === NULL) continue; ?>
                        <tr><th class="table-light" style="width:35%"><?= $k ?></th><td><?= htmlspecialchars((string) $v) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ( ! empty($data['flags'])): ?>
            <div class="col-md-6">
                <?php foreach ($data['flags'] as $flag): ?>
                <div class="alert alert-<?= $flag['severity'] === 'error' ? 'danger' : 'warning' ?> py-2 mb-1 small">
                    <i class="bi bi-<?= $flag['severity'] === 'error' ? 'x-circle' : 'exclamation-triangle' ?> me-1"></i>
                    <?= htmlspecialchars($flag['issue']) ?>
                    <?php if ( ! empty($flag['source']['page'])): ?>
                        <span class="text-muted ms-1">(p.<?= (int) $flag['source']['page'] ?>)</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ( ! empty($data['products'])): ?>
        <h6 class="fw-semibold mt-3 mb-2"><i class="bi bi-box me-1"></i>Products</h6>
        <div class="accordion mb-3" id="products-acc-<?= $doc['id'] ?>">
        <?php foreach ($data['products'] as $pi => $prod): ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= $pi > 0 ? 'collapsed' : '' ?> py-2" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#prod-<?= $doc['id'] ?>-<?= $pi ?>">
                        <?= htmlspecialchars($prod['product_category'] ?? "Product " . ($pi + 1)) ?>
                    </button>
                </h2>
                <div id="prod-<?= $doc['id'] ?>-<?= $pi ?>"
                     class="accordion-collapse collapse <?= $pi === 0 ? 'show' : '' ?>">
                    <div class="accordion-body pt-2">
                        <?php if ( ! empty($prod['approved_manufacturers'])): ?>
                        <p class="mb-1 small text-muted fw-semibold">APPROVED MANUFACTURERS</p>
                        <ul class="list-unstyled small mb-3">
                        <?php foreach ($prod['approved_manufacturers'] as $mfr): ?>
                            <li>
                                <i class="bi bi-building me-1 text-primary"></i>
                                <?= htmlspecialchars($mfr['name'] ?? '') ?>
                                <?php if ( ! empty($mfr['source']['page'])): ?>
                                    <span class="text-muted">(p.<?= (int) $mfr['source']['page'] ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>

                        <?php if ( ! empty($prod['required_attributes'])): ?>
                        <p class="mb-1 small text-muted fw-semibold">REQUIRED ATTRIBUTES</p>
                        <table class="table table-sm table-bordered small mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Attribute</th>
                                    <th>Value</th>
                                    <th>Required</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($prod['required_attributes'] as $attr): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($attr['attribute'] ?? '') ?></td>
                                <td>
                                    <?php if (($attr['value'] ?? NULL) !== NULL): ?>
                                        <?= htmlspecialchars($attr['value']) ?>
                                        <?php if ( ! empty($attr['unit'])): ?><span class="text-muted"> <?= htmlspecialchars($attr['unit']) ?></span><?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">null</span>
                                    <?php endif; ?>
                                    <?php if ( ! empty($attr['notes'])): ?>
                                        <div class="text-muted xsmall"><?= htmlspecialchars($attr['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= ($attr['is_required'] ?? FALSE) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">—</span>' ?></td>
                                <td class="text-muted small">
                                    <?php if ( ! empty($attr['source']['page'])): ?>
                                        p.<?= (int) $attr['source']['page'] ?>
                                        <?php if ( ! empty($attr['source']['section'])): ?>
                                            · <?= htmlspecialchars($attr['source']['section']) ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <?php if ( ! empty($prod['listings_required'])): ?>
                        <p class="mb-1 small text-muted fw-semibold">LISTINGS REQUIRED</p>
                        <ul class="list-unstyled small">
                        <?php foreach ($prod['listings_required'] as $lst): ?>
                            <li><i class="bi bi-patch-check me-1 text-info"></i><?= htmlspecialchars($lst['listing'] ?? '') ?>
                                <?php if ( ! empty($lst['source']['page'])): ?><span class="text-muted">(p.<?= (int) $lst['source']['page'] ?>)</span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty($data['submittal_requirements'])): ?>
        <h6 class="fw-semibold mb-2"><i class="bi bi-clipboard-check me-1"></i>Submittal Requirements</h6>
        <ul class="list-group list-group-flush small mb-3">
        <?php foreach ($data['submittal_requirements'] as $req): ?>
            <li class="list-group-item px-0 py-1">
                <?= htmlspecialchars($req['requirement'] ?? '') ?>
                <?php if ( ! empty($req['source']['page'])): ?>
                    <span class="text-muted ms-1">(p.<?= (int) $req['source']['page'] ?>)</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
        <?php endif; ?>

    <?php else: ?>

        <?php /* ---- CUT SHEET RESULTS ---- */ ?>

        <?php $meta = $data['meta'] ?? []; ?>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <table class="table table-sm table-bordered mb-0">
                    <tbody>
                    <?php foreach ([
                        'Manufacturer'  => $meta['manufacturer']           ?? NULL,
                        'Product Family'=> $meta['product_family']         ?? NULL,
                        'Rev. Date'     => $meta['document_revision_date'] ?? NULL,
                        'Pages'         => $meta['document_page_count']    ?? NULL,
                        'Confidence'    => isset($meta['extraction_confidence']) ? ucfirst($meta['extraction_confidence']) : NULL,
                    ] as $k => $v): if ($v === NULL) continue; ?>
                        <tr><th class="table-light" style="width:40%"><?= $k ?></th><td><?= htmlspecialchars((string) $v) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ( ! empty($data['flags'])): ?>
            <div class="col-md-6">
                <?php foreach ($data['flags'] as $flag): ?>
                <div class="alert alert-<?= $flag['severity'] === 'error' ? 'danger' : 'warning' ?> py-2 mb-1 small">
                    <i class="bi bi-<?= $flag['severity'] === 'error' ? 'x-circle' : 'exclamation-triangle' ?> me-1"></i>
                    <?= htmlspecialchars($flag['issue']) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ( ! empty($data['variants'])): ?>
        <h6 class="fw-semibold mt-3 mb-2"><i class="bi bi-collection me-1"></i>Product Variants</h6>
        <div class="accordion mb-3" id="variants-acc-<?= $doc['id'] ?>">
        <?php foreach ($data['variants'] as $vi => $variant): ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= $vi > 0 ? 'collapsed' : '' ?> py-2 font-monospace" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#variant-<?= $doc['id'] ?>-<?= $vi ?>">
                        <?= htmlspecialchars($variant['catalog_number'] ?? "Variant " . ($vi + 1)) ?>
                        <?php if ( ! empty($variant['description'])): ?>
                            <span class="text-muted fw-normal ms-2 small font-sans-serif"><?= htmlspecialchars($variant['description']) ?></span>
                        <?php endif; ?>
                    </button>
                </h2>
                <div id="variant-<?= $doc['id'] ?>-<?= $vi ?>"
                     class="accordion-collapse collapse <?= $vi === 0 ? 'show' : '' ?>">
                    <div class="accordion-body pt-2">
                        <?php if ( ! empty($variant['attributes'])): ?>
                        <table class="table table-sm table-bordered small mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Attribute</th>
                                    <th>Value</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($variant['attributes'] as $attr): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($attr['name'] ?? '') ?></td>
                                <td>
                                    <?= htmlspecialchars($attr['value'] ?? '') ?>
                                    <?php if ( ! empty($attr['unit'])): ?><span class="text-muted"> <?= htmlspecialchars($attr['unit']) ?></span><?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?php if ( ! empty($attr['source']['page'])): ?>
                                        p.<?= (int) $attr['source']['page'] ?>
                                        <?php if ( ! empty($attr['source']['location'])): ?>
                                            · <?= htmlspecialchars($attr['source']['location']) ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <?php if ( ! empty($variant['listings_and_certifications'])): ?>
                        <p class="mb-1 small text-muted fw-semibold">LISTINGS & CERTIFICATIONS</p>
                        <ul class="list-unstyled small mb-0">
                        <?php foreach ($variant['listings_and_certifications'] as $cert): ?>
                            <li>
                                <i class="bi bi-patch-check-fill text-success me-1"></i>
                                <?= htmlspecialchars($cert['listing'] ?? '') ?>
                                <?php if ( ! empty($cert['file_number'])): ?>
                                    <span class="text-muted">(<?= htmlspecialchars($cert['file_number']) ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty($data['common_attributes'])): ?>
        <h6 class="fw-semibold mb-2"><i class="bi bi-list-check me-1"></i>Common Attributes</h6>
        <table class="table table-sm table-bordered small mb-3">
            <thead class="table-light">
                <tr><th>Attribute</th><th>Value</th><th>Source</th></tr>
            </thead>
            <tbody>
            <?php foreach ($data['common_attributes'] as $attr): ?>
            <tr>
                <td class="fw-medium"><?= htmlspecialchars($attr['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($attr['value'] ?? '') ?><?php if ( ! empty($attr['unit'])): ?> <span class="text-muted"><?= htmlspecialchars($attr['unit']) ?></span><?php endif; ?></td>
                <td class="text-muted small"><?php if ( ! empty($attr['source']['page'])): ?>p.<?= (int) $attr['source']['page'] ?><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    <?php endif; /* end spec vs cut sheet */ ?>

    </div><!-- card-body -->
</div><!-- results card -->
<?php endforeach; /* end documents loop for results */ ?>

<div class="mt-3">
    <a href="<?= site_url('projects/' . $project['id']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Project
    </a>
</div>

<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->
<script>
(function () {
    'use strict';

    const UPLOAD_URL    = '<?= site_url('submittals/' . $submittal['id'] . '/upload') ?>';
    const RERUN_URL_TPL = '<?= site_url('extractions/__ID__/rerun') ?>';
    const MAX_BYTES     = <?= Submittals::MAX_BYTES ?>;

    let csrfName = '<?= $csrf_token_name ?>';
    let csrfHash = '<?= $csrf_hash ?>';

    // ---- Auto-refresh when extractions are pending ----
    <?php if ($hasPending): ?>
    setTimeout(function () { location.reload(); }, 10000);
    <?php endif; ?>

    // ---- Drag-and-drop / browse ----
    const dropZone  = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const queueDiv  = document.getElementById('upload-queue');
    const queueBody = document.getElementById('queue-body');
    const uploadAllBtn = document.getElementById('upload-all-btn');
    const globalErr = document.getElementById('upload-global-error');

    let pendingFiles = []; // { file, rowEl, typeSelect, progressEl, statusEl, uploaded }

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZone.classList.add('bg-light');
    });
    dropZone.addEventListener('dragleave', function () {
        dropZone.classList.remove('bg-light');
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('bg-light');
        addFiles(Array.from(e.dataTransfer.files));
    });
    fileInput.addEventListener('change', function () {
        addFiles(Array.from(fileInput.files));
        fileInput.value = '';
    });

    function addFiles(files) {
        files.forEach(function (file) {
            if ( ! file.name.toLowerCase().endsWith('.pdf') && file.type !== 'application/pdf') {
                showGlobalError('Skipped "' + escHtml(file.name) + '" — only PDF files are accepted.');
                return;
            }
            if (file.size > MAX_BYTES) {
                showGlobalError('Skipped "' + escHtml(file.name) + '" — exceeds 50 MB limit.');
                return;
            }
            addQueueRow(file);
        });
        if (pendingFiles.length > 0) {
            queueDiv.classList.remove('d-none');
        }
    }

    function addQueueRow(file) {
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td><i class="bi bi-file-earmark-pdf text-danger me-1"></i>' + escHtml(file.name) + '</td>' +
            '<td>' +
                '<select class="form-select form-select-sm doc-type-select">' +
                    '<option value="">— select type —</option>' +
                    '<option value="spec_section">Spec Section</option>' +
                    '<option value="cut_sheet">Cut Sheet</option>' +
                '</select>' +
            '</td>' +
            '<td class="text-muted small">' + fmtBytes(file.size) + '</td>' +
            '<td>' +
                '<div class="progress d-none mb-1" style="height:6px"><div class="progress-bar" style="width:0%"></div></div>' +
                '<span class="status-cell text-muted small">Waiting</span>' +
            '</td>' +
            '<td class="text-end">' +
                '<button class="btn btn-sm btn-outline-danger remove-btn"><i class="bi bi-trash"></i></button>' +
            '</td>';
        queueBody.appendChild(tr);

        const entry = {
            file:        file,
            rowEl:       tr,
            typeSelect:  tr.querySelector('.doc-type-select'),
            progressDiv: tr.querySelector('.progress'),
            progressBar: tr.querySelector('.progress-bar'),
            statusEl:    tr.querySelector('.status-cell'),
            uploaded:    false,
        };
        pendingFiles.push(entry);

        tr.querySelector('.remove-btn').addEventListener('click', function () {
            tr.remove();
            pendingFiles = pendingFiles.filter(function (e) { return e !== entry; });
            if (pendingFiles.length === 0) queueDiv.classList.add('d-none');
        });
    }

    uploadAllBtn.addEventListener('click', function () {
        globalErr.classList.add('d-none');
        const toUpload = pendingFiles.filter(function (e) { return ! e.uploaded; });
        if (toUpload.length === 0) return;

        // Validate all types selected
        let missing = false;
        toUpload.forEach(function (e) {
            if ( ! e.typeSelect.value) {
                e.statusEl.innerHTML = '<span class="text-danger">Select a type</span>';
                missing = true;
            }
        });
        if (missing) return;

        // Upload sequentially
        uploadAllBtn.disabled = true;
        uploadSequential(toUpload, 0);
    });

    function uploadSequential(list, idx) {
        if (idx >= list.length) {
            uploadAllBtn.disabled = false;
            // Reload to show updated document list
            setTimeout(function () { location.reload(); }, 800);
            return;
        }
        uploadOne(list[idx], function () {
            uploadSequential(list, idx + 1);
        });
    }

    function uploadOne(entry, done) {
        entry.typeSelect.disabled = true;
        entry.progressDiv.classList.remove('d-none');
        entry.statusEl.textContent = 'Uploading…';

        const fd = new FormData();
        fd.append('file',     entry.file);
        fd.append('doc_type', entry.typeSelect.value);
        fd.append(csrfName,   csrfHash);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', UPLOAD_URL);

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                const pct = Math.round(e.loaded / e.total * 100);
                entry.progressBar.style.width = pct + '%';
            }
        });

        xhr.addEventListener('load', function () {
            let resp;
            try { resp = JSON.parse(xhr.responseText); } catch (e) { resp = {success: false, error: 'Server error.'}; }

            // Update CSRF token for next request
            if (resp.csrf_hash) csrfHash = resp.csrf_hash;

            if (resp.success) {
                entry.uploaded = true;
                entry.progressBar.classList.add('bg-success');
                entry.progressBar.style.width = '100%';
                entry.statusEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Uploaded</span>';
                entry.rowEl.classList.add('table-success');
            } else {
                entry.progressBar.classList.add('bg-danger');
                entry.statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + escHtml(resp.error || 'Upload failed.') + '</span>';
            }
            done();
        });

        xhr.addEventListener('error', function () {
            entry.progressBar.classList.add('bg-danger');
            entry.statusEl.innerHTML = '<span class="text-danger">Network error</span>';
            done();
        });

        xhr.send(fd);
    }

    // ---- Re-run extraction buttons ----
    document.querySelectorAll('.rerun-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const extId = btn.dataset.extractionId;
            const url   = RERUN_URL_TPL.replace('__ID__', extId);
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const fd = new FormData();
            fd.append(csrfName, csrfHash);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.addEventListener('load', function () {
                let resp;
                try { resp = JSON.parse(xhr.responseText); } catch (e) { resp = {success: false}; }
                if (resp.csrf_hash) csrfHash = resp.csrf_hash;
                if (resp.success) {
                    location.reload();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Re-run';
                }
            });
            xhr.send(fd);
        });
    });

    // ---- Utilities ----
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function fmtBytes(b) {
        if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
        if (b >= 1024)    return Math.round(b / 1024) + ' KB';
        return b + ' B';
    }
    function showGlobalError(msg) {
        globalErr.classList.remove('d-none');
        globalErr.textContent = msg;
    }
}());
</script>
