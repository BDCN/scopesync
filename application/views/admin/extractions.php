<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-activity me-2"></i>Extraction Log</h1>
    <small class="text-muted">Last 100 extractions · all tenants</small>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="display-6 fw-bold"><?= count($extractions) ?></div>
                <div class="text-muted small">Extractions shown</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <?php $completed = array_filter($extractions, fn($e) => $e['status'] === 'completed'); ?>
                <div class="display-6 fw-bold text-success"><?= count($completed) ?></div>
                <div class="text-muted small">Completed</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="display-6 fw-bold"><?= number_format($total_input_tokens) ?></div>
                <div class="text-muted small">Input tokens</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="display-6 fw-bold"><?= number_format($total_output_tokens) ?></div>
                <div class="text-muted small">Output tokens</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Tenant</th>
                    <th>Submittal</th>
                    <th>File</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Model</th>
                    <th>In tokens</th>
                    <th>Out tokens</th>
                    <th>Confidence</th>
                    <th>Started</th>
                    <th>Completed</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($extractions)): ?>
            <tr><td colspan="12" class="text-center text-muted py-4">No extractions yet.</td></tr>
            <?php else: ?>
            <?php foreach ($extractions as $e):
                $statusBadge = [
                    'pending'   => '<span class="badge bg-secondary">Pending</span>',
                    'running'   => '<span class="badge bg-info text-dark">Running</span>',
                    'completed' => '<span class="badge bg-success">Completed</span>',
                    'failed'    => '<span class="badge bg-danger">Failed</span>',
                ][$e['status']] ?? '<span class="badge bg-light text-dark">?</span>';

                $confBadge = [
                    'high'   => '<span class="badge bg-success">High</span>',
                    'medium' => '<span class="badge bg-warning text-dark">Med</span>',
                    'low'    => '<span class="badge bg-danger">Low</span>',
                ][$e['confidence'] ?? ''] ?? '—';
            ?>
            <tr>
                <td class="text-muted small"><?= (int) $e['id'] ?></td>
                <td class="small"><?= htmlspecialchars($e['tenant_name'] ?? '') ?></td>
                <td class="small text-break" style="max-width:140px">
                    <a href="<?= site_url('submittals/' . $e['submittal_job_id']) ?>">
                        <?= htmlspecialchars($e['submittal_name'] ?? $e['submittal_job_id']) ?>
                    </a>
                </td>
                <td class="small text-break" style="max-width:160px"><?= htmlspecialchars($e['original_filename'] ?? '') ?></td>
                <td>
                    <?php if ($e['doc_type'] === 'spec_section'): ?>
                        <span class="badge bg-primary-subtle text-primary">Spec</span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis">Cut Sheet</span>
                    <?php endif; ?>
                </td>
                <td><?= $statusBadge ?></td>
                <td class="small text-muted"><?= htmlspecialchars($e['model_used'] ?? '—') ?></td>
                <td class="small text-end"><?= $e['input_tokens']  ? number_format((int) $e['input_tokens'])  : '—' ?></td>
                <td class="small text-end"><?= $e['output_tokens'] ? number_format((int) $e['output_tokens']) : '—' ?></td>
                <td><?= $confBadge ?></td>
                <td class="small text-muted text-nowrap"><?= $e['started_at']   ? date('M j, H:i', strtotime($e['started_at']))   : '—' ?></td>
                <td class="small text-muted text-nowrap"><?= $e['completed_at'] ? date('M j, H:i', strtotime($e['completed_at'])) : '—' ?></td>
            </tr>
            <?php if ($e['status'] === 'failed' && ! empty($e['error_message'])): ?>
            <tr class="table-danger">
                <td colspan="12" class="small text-danger py-1 ps-3">
                    <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e['error_message']) ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
