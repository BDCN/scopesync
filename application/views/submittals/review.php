<?php
function ss_result_badge(string $result): string {
    $map = [
        'pass'         => ['success', 'Pass'],
        'partial'      => ['warning', 'Partial'],
        'fail'         => ['danger',  'Fail'],
        'unverifiable' => ['secondary','Unverifiable'],
    ];
    [$cls, $lbl] = $map[$result] ?? ['secondary', ucfirst($result)];
    return "<span class=\"badge bg-{$cls}\">{$lbl}</span>";
}

function ss_attr_result_badge(string $result): string {
    $map = [
        'pass'         => ['success',   'bi-check-circle-fill', 'Pass'],
        'fail'         => ['danger',    'bi-x-circle-fill',     'Fail'],
        'missing'      => ['secondary', 'bi-dash-circle',       'Missing'],
        'unverifiable' => ['warning',   'bi-question-circle',   '?'],
    ];
    [$cls, $icon, $lbl] = $map[$result] ?? ['secondary', 'bi-question-circle', $result];
    return "<span class=\"badge bg-{$cls}\"><i class=\"bi {$icon} me-1\"></i>{$lbl}</span>";
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= site_url('projects') ?>">Projects</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('projects/' . $project['id']) ?>"><?= htmlspecialchars($project['name']) ?></a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('submittals/' . $submittal['id']) ?>"><?= htmlspecialchars($submittal['name']) ?></a></li>
        <li class="breadcrumb-item active">Review Queue</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Review Queue</h1>
        <div class="text-muted small"><?= htmlspecialchars($submittal['name']) ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-success btn-sm" id="approve-all-btn">
            <i class="bi bi-check-all me-1"></i>Approve All
        </button>
        <a href="<?= site_url('submittals/' . $submittal['id'] . '/compliance') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-table me-1"></i>Compliance Matrix
        </a>
        <a href="<?= site_url('submittals/' . $submittal['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<?php if (empty($match_results)): ?>
<div class="alert alert-info">
    <i class="bi bi-hourglass-split me-2"></i>
    No match results yet. The matching engine runs automatically once all extractions are complete.
</div>
<?php else: ?>

<?php if ($all_decided): ?>
    <?php if ($has_rejections): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>One or more products were rejected.</strong>
        Replacement products must be submitted before this package can be assembled.
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>All products reviewed and approved.</strong>
        This submittal is ready for PDF assembly.
    </div>
    <?php endif; ?>
<?php else:
    $total   = count($match_results);
    $decided = count($decision_map);
    $remaining = $total - $decided;
?>
<div class="alert alert-primary d-flex align-items-center gap-2 py-2">
    <i class="bi bi-clipboard-check"></i>
    <span><?= $decided ?> of <?= $total ?> product(s) reviewed — <strong><?= $remaining ?> remaining</strong>.</span>
</div>
<?php endif; ?>

<!-- Summary strip -->
<?php
$summaryCount = ['pass' => 0, 'partial' => 0, 'fail' => 0, 'unverifiable' => 0];
foreach ($match_results as $mr) {
    $summaryCount[$mr['overall_result']] = ($summaryCount[$mr['overall_result']] ?? 0) + 1;
}
$summaryLabels = ['pass' => ['success','Pass'], 'partial' => ['warning','Partial'], 'fail' => ['danger','Fail'], 'unverifiable' => ['secondary','Unverifiable']];
?>
<div class="d-flex gap-3 flex-wrap mb-3 small">
    <?php foreach ($summaryLabels as $key => [$cls, $lbl]): if ($summaryCount[$key] === 0) continue; ?>
    <span class="badge bg-<?= $cls ?> fs-6"><?= $summaryCount[$key] ?> <?= $lbl ?></span>
    <?php endforeach; ?>
</div>

<!-- Filter strip — all four result types always visible -->
<div class="d-flex gap-2 align-items-center flex-wrap mb-4" id="filter-strip">
    <span class="small text-muted">Filter:</span>
    <button class="btn btn-sm btn-secondary active" data-filter="all">All (<?= count($match_results) ?>)</button>
    <?php foreach ($summaryLabels as $key => [$cls, $lbl]): ?>
    <button class="btn btn-sm btn-outline-<?= $cls ?>" data-filter="<?= $key ?>"><?= $lbl ?> (<?= $summaryCount[$key] ?>)</button>
    <?php endforeach; ?>
</div>

<?php foreach ($match_results as $mr):
    $mrId         = (int) $mr['id'];
    $attrData     = $decoded[$mrId] ?? [];
    $attrResults  = $attrData['attribute_results'] ?? [];
    $listingResults = $attrData['listing_results'] ?? [];
    $decision     = $decision_map[$mrId] ?? null;

    $failingAttrs = array_filter($attrResults, function ($a) {
        return in_array($a['result'], ['fail', 'missing'], TRUE);
    });
    $hasIssues = ! empty($failingAttrs) || ! empty(array_filter($listingResults, function ($l) { return $l['result'] !== 'pass'; }));
?>
<div class="card mb-3 <?= $decision ? 'border-' . (['approved' => 'success', 'overridden' => 'warning', 'rejected' => 'danger'][$decision['decision']] ?? '') : '' ?>"
     id="mr-card-<?= $mrId ?>"
     data-mr-id="<?= $mrId ?>"
     data-mr-result="<?= htmlspecialchars($mr['overall_result']) ?>">

    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <div class="d-flex align-items-center gap-2">
            <code class="fw-semibold fs-6"><?= htmlspecialchars($mr['catalog_number']) ?></code>
            <?php if ($mr['product_category']): ?>
            <span class="badge bg-primary-subtle text-primary fw-normal"><?= htmlspecialchars($mr['product_category']) ?></span>
            <?php endif; ?>
            <?= ss_result_badge($mr['overall_result']) ?>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($decision): ?>
            <?php $decBadge = ['approved' => ['success','check-circle-fill','Approved'], 'overridden' => ['warning','exclamation-circle-fill','Overridden'], 'rejected' => ['danger','x-circle-fill','Rejected']][$decision['decision']]; ?>
            <span class="badge bg-<?= $decBadge[0] ?>">
                <i class="bi bi-<?= $decBadge[1] ?> me-1"></i><?= $decBadge[2] ?>
            </span>
            <?php endif; ?>
            <button class="btn btn-outline-secondary btn-sm" type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#mr-body-<?= $mrId ?>">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>

    <div id="mr-body-<?= $mrId ?>" class="collapse <?= ($hasIssues || ! $decision) ? 'show' : '' ?>">
        <div class="card-body pt-2">

            <?php if ( ! empty($attrResults)): ?>
            <h6 class="small fw-semibold text-muted text-uppercase mb-2">Attribute Comparison</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Attribute</th>
                            <th>Spec</th>
                            <th>Product</th>
                            <th>Result</th>
                            <th>Spec source</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attrResults as $ar): ?>
                    <tr class="<?= in_array($ar['result'], ['fail', 'missing'], TRUE) ? 'table-danger' : ($ar['result'] === 'pass' ? 'table-success' : '') ?>">
                        <td class="fw-medium font-monospace"><?= htmlspecialchars($ar['attribute']) ?></td>
                        <td>
                            <?php if ($ar['spec_value'] !== null): ?>
                                <?= htmlspecialchars($ar['spec_value']) ?>
                                <?php if ( ! empty($ar['spec_unit'])): ?>
                                    <span class="text-muted"><?= htmlspecialchars($ar['spec_unit']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">null</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ar['product_value'] !== null): ?>
                                <?= htmlspecialchars($ar['product_value']) ?>
                                <?php if ( ! empty($ar['product_unit'])): ?>
                                    <span class="text-muted"><?= htmlspecialchars($ar['product_unit']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= ss_attr_result_badge($ar['result']) ?></td>
                        <td class="text-muted">
                            <?php if ( ! empty($ar['spec_source']['page'])): ?>
                                p.<?= (int) $ar['spec_source']['page'] ?>
                                <?php if ( ! empty($ar['spec_source']['section'])): ?>
                                    · <?= htmlspecialchars($ar['spec_source']['section']) ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ( ! empty($listingResults)): ?>
            <h6 class="small fw-semibold text-muted text-uppercase mb-2">Listing Checks</h6>
            <ul class="list-unstyled small mb-3">
            <?php foreach ($listingResults as $lr): ?>
            <li class="mb-1">
                <?php if ($lr['result'] === 'pass'): ?>
                    <i class="bi bi-patch-check-fill text-success me-1"></i>
                    <?= htmlspecialchars($lr['required_listing']) ?>
                    <span class="text-muted">(matched: <?= htmlspecialchars($lr['matched_listing'] ?? '') ?>)</span>
                <?php else: ?>
                    <i class="bi bi-patch-exclamation-fill text-danger me-1"></i>
                    <span class="text-danger"><?= htmlspecialchars($lr['required_listing']) ?> — not found</span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if ($decision && $decision['decision'] === 'overridden' && $decision['override_notes']): ?>
            <div class="alert alert-warning py-2 small mb-3">
                <i class="bi bi-pencil me-1"></i>
                <strong>Override justification:</strong> <?= htmlspecialchars($decision['override_notes']) ?>
            </div>
            <?php endif; ?>

            <!-- Decision form -->
            <div class="border rounded p-3 bg-light" id="decision-form-<?= $mrId ?>">
                <h6 class="fw-semibold mb-2 small">
                    <?= $decision ? 'Update Decision' : 'Make a Decision' ?>
                </h6>
                <div class="d-flex gap-2 flex-wrap align-items-start">
                    <button type="button"
                            class="btn btn-sm btn-success decide-btn <?= ($decision && $decision['decision'] === 'approved') ? 'active' : '' ?>"
                            data-mr-id="<?= $mrId ?>"
                            data-submittal-id="<?= $submittal['id'] ?>"
                            data-decision="approved">
                        <i class="bi bi-check-circle me-1"></i>Approve
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-warning decide-btn <?= ($decision && $decision['decision'] === 'overridden') ? 'active' : '' ?>"
                            data-mr-id="<?= $mrId ?>"
                            data-submittal-id="<?= $submittal['id'] ?>"
                            data-decision="overridden">
                        <i class="bi bi-pencil-square me-1"></i>Override
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-danger decide-btn <?= ($decision && $decision['decision'] === 'rejected') ? 'active' : '' ?>"
                            data-mr-id="<?= $mrId ?>"
                            data-submittal-id="<?= $submittal['id'] ?>"
                            data-decision="rejected">
                        <i class="bi bi-x-circle me-1"></i>Reject
                    </button>
                </div>

                <!-- Override notes field (shown only for override) -->
                <div class="mt-2 d-none override-notes-row" id="notes-row-<?= $mrId ?>">
                    <label class="form-label small fw-semibold mb-1" for="notes-<?= $mrId ?>">
                        Override justification <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control form-control-sm override-notes-field"
                              id="notes-<?= $mrId ?>"
                              rows="2"
                              placeholder="Explain why this product is acceptable despite the mismatch…"><?= htmlspecialchars($decision['override_notes'] ?? '') ?></textarea>
                </div>

                <div class="decision-feedback mt-2 small text-muted d-none" id="feedback-<?= $mrId ?>"></div>
            </div>

        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- Summary action bar -->
<?php if ( ! empty($match_results) && $all_decided && ! $has_rejections): ?>
<div class="alert alert-success d-flex justify-content-between align-items-center mt-2">
    <span><i class="bi bi-check-circle-fill me-2"></i>All products approved — ready for PDF assembly.</span>
    <a href="<?= site_url('submittals/' . $submittal['id']) ?>" class="btn btn-success btn-sm">
        <i class="bi bi-file-earmark-pdf me-1"></i>Proceed to Assembly
    </a>
</div>
<?php endif; ?>

<div class="mt-3 d-flex gap-2">
    <a href="<?= site_url('submittals/' . $submittal['id'] . '/compliance') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-table me-1"></i>Compliance Matrix
    </a>
    <a href="<?= site_url('submittals/' . $submittal['id']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Submittal
    </a>
</div>

<script>
(function () {
    'use strict';

    const DECIDE_URL_TPL = '<?= site_url('submittals/__SID__/decide') ?>';
    let csrfName = '<?= $csrf_token_name ?>';
    let csrfHash = '<?= $csrf_hash ?>';

    // Show override notes textarea when Override is chosen
    document.querySelectorAll('.decide-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const mrId    = btn.dataset.mrId;
            const decision = btn.dataset.decision;
            const notesRow = document.getElementById('notes-row-' + mrId);
            const feedback = document.getElementById('feedback-' + mrId);

            // Toggle notes row
            if (decision === 'overridden') {
                notesRow.classList.remove('d-none');
            } else {
                notesRow.classList.add('d-none');
            }

            // If not override, submit immediately; if override, require confirmation
            if (decision !== 'overridden') {
                submitDecision(mrId, btn.dataset.submittalId, decision, null, btn, feedback);
            } else {
                // Add confirm button dynamically if not already there
                let confirmBtn = notesRow.querySelector('.override-confirm-btn');
                if ( ! confirmBtn) {
                    confirmBtn = document.createElement('button');
                    confirmBtn.type    = 'button';
                    confirmBtn.className = 'btn btn-sm btn-warning mt-2 override-confirm-btn';
                    confirmBtn.innerHTML = '<i class="bi bi-check me-1"></i>Submit Override';
                    notesRow.appendChild(confirmBtn);

                    confirmBtn.addEventListener('click', function () {
                        const notes = document.getElementById('notes-' + mrId).value.trim();
                        submitDecision(mrId, btn.dataset.submittalId, 'overridden', notes, confirmBtn, feedback);
                    });
                }
            }
        });
    });

    // ---- Filter strip ----
    const filterBtns    = document.querySelectorAll('[data-filter]');
    const allCards      = document.querySelectorAll('[data-mr-result]');
    const colorMap      = {all: 'secondary', pass: 'success', partial: 'warning', fail: 'danger', unverifiable: 'secondary'};

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const filter = btn.dataset.filter;

            filterBtns.forEach(function (b) {
                const c = colorMap[b.dataset.filter] || 'secondary';
                b.className = 'btn btn-sm btn-outline-' + c;
            });
            const ac = colorMap[filter] || 'secondary';
            btn.className = 'btn btn-sm btn-' + ac + ' active';

            allCards.forEach(function (card) {
                card.classList.toggle('d-none', filter !== 'all' && card.dataset.mrResult !== filter);
            });
        });
    });

    // ---- Approve All ----
    const approveAllBtn = document.getElementById('approve-all-btn');
    if (approveAllBtn) {
        approveAllBtn.addEventListener('click', function () {
            const targets = Array.from(document.querySelectorAll('[data-mr-id]:not(.d-none)'))
                                 .map(function (c) { return c.dataset.mrId; });
            if (targets.length === 0) return;

            if ( ! confirm('Approve all ' + targets.length + ' visible product(s)?\n\nExisting overrides and rejections will be set to Approved.')) {
                return;
            }

            approveAllBtn.disabled = true;
            const sid = '<?= (int) $submittal['id'] ?>';
            _approveSequential(targets, 0, sid);
        });
    }

    function _approveSequential(list, idx, sid) {
        if (idx >= list.length) {
            location.reload();
            return;
        }

        approveAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Approving ' + (idx + 1) + ' / ' + list.length + '…';

        const fd = new FormData();
        fd.append('match_result_id', list[idx]);
        fd.append('decision',        'approved');
        fd.append('override_notes',  '');
        fd.append(csrfName,          csrfHash);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', DECIDE_URL_TPL.replace('__SID__', sid));
        xhr.addEventListener('load', function () {
            let resp;
            try { resp = JSON.parse(xhr.responseText); } catch (e) { resp = {}; }
            if (resp.csrf_hash) csrfHash = resp.csrf_hash;
            _approveSequential(list, idx + 1, sid);
        });
        xhr.addEventListener('error', function () {
            _approveSequential(list, idx + 1, sid);
        });
        xhr.send(fd);
    }

    function submitDecision(mrId, submittalId, decision, notes, triggerEl, feedbackEl) {
        triggerEl.disabled = true;

        const fd = new FormData();
        fd.append('match_result_id', mrId);
        fd.append('decision',        decision);
        fd.append('override_notes',  notes || '');
        fd.append(csrfName,          csrfHash);

        const url = DECIDE_URL_TPL.replace('__SID__', submittalId);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);

        xhr.addEventListener('load', function () {
            let resp;
            try { resp = JSON.parse(xhr.responseText); } catch(e) { resp = {success: false, error: 'Server error.'}; }

            if (resp.csrf_hash) csrfHash = resp.csrf_hash;

            if (resp.success) {
                const card = document.getElementById('mr-card-' + mrId);

                // Update card border
                card.classList.remove('border-success', 'border-warning', 'border-danger');
                const borderMap = {approved: 'success', overridden: 'warning', rejected: 'danger'};
                if (borderMap[decision]) card.classList.add('border-' + borderMap[decision]);

                feedbackEl.classList.remove('d-none', 'text-danger');
                feedbackEl.classList.add('text-success');
                feedbackEl.innerHTML = '<i class="bi bi-check-circle me-1"></i>Decision saved.';

                // Reload to sync all-decided banner
                setTimeout(function () { location.reload(); }, 600);
            } else {
                feedbackEl.classList.remove('d-none', 'text-success');
                feedbackEl.classList.add('text-danger');
                feedbackEl.textContent = resp.error || 'Failed to save decision.';
                triggerEl.disabled = false;
            }
        });

        xhr.addEventListener('error', function () {
            feedbackEl.classList.remove('d-none');
            feedbackEl.classList.add('text-danger');
            feedbackEl.textContent = 'Network error — please try again.';
            triggerEl.disabled = false;
        });

        xhr.send(fd);
    }
}());
</script>
