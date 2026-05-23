<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'third_party/tcpdf/tcpdf.php';

/**
 * SubmittalAssembler — generates a branded PDF submittal package.
 *
 * Usage (from controller):
 *   $this->load->library('SubmittalAssembler');
 *   $outputPath = $this->submittalassembler->build($submittalId, $tenantId);
 */
class SubmittalAssembler {

    protected $CI;

    const DEFAULT_COLOR = '#0d9488'; // ScopeSync teal fallback
    const FONT          = 'helvetica';

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model([
            'Submittal_model',
            'Project_model',
            'Division_model',
            'Tenant_model',
            'Match_result_model',
            'Review_decision_model',
            'Document_model',
            'Extraction_model',
        ]);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Build the submittal package PDF.
     *
     * @return string Absolute path to the generated file.
     * @throws RuntimeException on fatal errors.
     */
    public function build(int $submittalId, int $tenantId): string
    {
        $data = $this->_loadData($submittalId, $tenantId);
        $pdf  = $this->_createPdf($data);

        $this->_buildCoverPage($pdf, $data);
        $this->_buildTOC($pdf, $data);

        foreach ($data['approved'] as $mr) {
            $decision = $data['decision_map'][(int) $mr['id']] ?? [];
            $this->_buildProductSection($pdf, $data, $mr, $decision);
        }

        if ( ! empty($data['rejected'])) {
            $this->_buildRejectedAppendix($pdf, $data);
        }

        $outputPath = $this->_outputPath(
            $tenantId,
            (int) $data['submittal']['project_id'],
            $submittalId
        );

        $outputDir = dirname($outputPath);
        if ( ! is_dir($outputDir)) {
            mkdir($outputDir, 0755, TRUE);
        }

        $pdf->Output($outputPath, 'F');

        return $outputPath;
    }

    // -------------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------------

    private function _loadData(int $submittalId, int $tenantId): array
    {
        $submittal = $this->CI->Submittal_model->getByIdAndTenant($submittalId, $tenantId);
        if ( ! $submittal) {
            throw new RuntimeException("Submittal {$submittalId} not found for tenant {$tenantId}.");
        }

        $project  = $this->CI->Project_model->getByIdAndTenant((int) $submittal['project_id'], $tenantId);
        $division = $this->CI->Division_model->getByIdAndTenant((int) $submittal['division_id'], $tenantId);
        $settings = $this->CI->Tenant_model->getSettings($tenantId) ?? [];

        $matchResults = $this->CI->Match_result_model->getBySubmittal($submittalId, $tenantId);
        $decisionMap  = $this->CI->Review_decision_model->mapByMatchResult($submittalId, $tenantId);

        // Build extraction_id → document filename map for source citations
        $allExtractions = $this->CI->Extraction_model->getBySubmittal($submittalId, $tenantId);
        $allDocuments   = $this->CI->Document_model->getBySubmittal($submittalId, $tenantId);
        $docById = [];
        foreach ($allDocuments as $doc) {
            $docById[(int) $doc['id']] = $doc;
        }
        $extractionDocMap = [];
        foreach ($allExtractions as $extr) {
            $docId = (int) $extr['document_id'];
            $extractionDocMap[(int) $extr['id']] = $docById[$docId]['original_filename'] ?? '';
        }

        // Split match results into approved (or overridden) vs rejected
        $approved = [];
        $rejected = [];
        foreach ($matchResults as $mr) {
            $mrId     = (int) $mr['id'];
            $decision = $decisionMap[$mrId]['decision'] ?? 'approved';
            if ($decision === 'rejected') {
                $rejected[] = $mr;
            } else {
                $approved[] = $mr;
            }
        }

        $primaryColor = ! empty($settings['primary_color']) ? $settings['primary_color'] : self::DEFAULT_COLOR;
        $companyName  = ! empty($settings['company_name'])  ? $settings['company_name']  : ($this->CI->tenantcontext->name() ?: 'Unknown Company');
        $logoPath     = ! empty($settings['logo_path'])     ? APPPATH . "../storage/tenants/{$tenantId}/branding/" . basename($settings['logo_path']) : null;

        return [
            'submittal'         => $submittal,
            'project'           => $project,
            'division'          => $division,
            'settings'          => $settings,
            'primary_color'     => $primaryColor,
            'company_name'      => $companyName,
            'logo_path'         => ($logoPath && is_file($logoPath)) ? $logoPath : null,
            'match_results'     => $matchResults,
            'decision_map'      => $decisionMap,
            'approved'          => $approved,
            'rejected'          => $rejected,
            'extraction_doc_map'=> $extractionDocMap,
        ];
    }

    // -------------------------------------------------------------------------
    // PDF setup
    // -------------------------------------------------------------------------

    private function _createPdf(array $data): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'LETTER', TRUE, 'UTF-8', FALSE);

        $pdf->SetCreator('ScopeSync');
        $pdf->SetAuthor($data['company_name']);
        $pdf->SetTitle($data['submittal']['name'] . ' — Submittal Package');
        $pdf->SetSubject('Submittal Package');

        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 20);
        $pdf->setPrintHeader(FALSE);
        $pdf->setPrintFooter(FALSE);

        return $pdf;
    }

    // -------------------------------------------------------------------------
    // Cover page
    // -------------------------------------------------------------------------

    private function _buildCoverPage(TCPDF $pdf, array $data): void
    {
        $pdf->AddPage();

        [$r, $g, $b] = $this->_hexToRgb($data['primary_color']);

        // Color band at top
        $pdf->SetFillColor($r, $g, $b);
        $pdf->Rect(0, 0, 216, 50, 'F');

        // Logo (white area top-left of band)
        $logoY = 10;
        if ($data['logo_path']) {
            try {
                $pdf->Image($data['logo_path'], 15, $logoY, 40, 30, '', '', '', FALSE, 300, '', FALSE, FALSE, 0, TRUE);
            } catch (Exception $e) {
                // Logo failed — continue without it
            }
        }

        // Company name (white text on color band)
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont(self::FONT, 'B', 22);
        $pdf->SetXY(60, 14);
        $pdf->Cell(0, 12, $data['company_name'], 0, 1, 'L');

        $pdf->SetFont(self::FONT, '', 11);
        $pdf->SetXY(60, 27);
        $pdf->Cell(0, 8, 'SUBMITTAL PACKAGE', 0, 1, 'L');

        // Body content below band
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetY(65);

        // Submittal name
        $pdf->SetFont(self::FONT, 'B', 18);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->MultiCell(0, 10, $data['submittal']['name'], 0, 'L');
        $pdf->Ln(4);

        // Details table
        $details = [
            'Project'      => $data['project']['name']          ?? '—',
            'Division'     => ($data['division']['code'] ?? '') . ' ' . ($data['division']['name'] ?? ''),
            'Spec Section' => $data['submittal']['spec_section'] ?? '—',
            'Date'         => date('F j, Y'),
        ];

        $pdf->SetFont(self::FONT, '', 10);
        foreach ($details as $label => $value) {
            $pdf->SetFillColor($r, $g, $b);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(45, 8, $label, 0, 0, 'L', TRUE);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Cell(0, 8, trim($value), 0, 1, 'L', TRUE);
        }

        $pdf->Ln(8);

        // Summary counts
        $totalApproved = count($data['approved']);
        $totalRejected = count($data['rejected']);
        $overrideCount = 0;
        foreach ($data['approved'] as $mr) {
            if (($data['decision_map'][(int) $mr['id']]['decision'] ?? '') === 'overridden') {
                $overrideCount++;
            }
        }

        $pdf->SetFont(self::FONT, 'B', 11);
        $pdf->SetFillColor($r, $g, $b);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 8, 'Package Summary', 0, 1, 'L', TRUE);
        $pdf->SetFont(self::FONT, '', 10);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetTextColor(30, 30, 30);

        $summaryRows = [
            ['Products included',         $totalApproved],
            ['  — Approved',              $totalApproved - $overrideCount],
            ['  — Approved with Override', $overrideCount],
            ['Products rejected',          $totalRejected],
        ];
        foreach ($summaryRows as [$label, $val]) {
            $pdf->Cell(80, 7, $label, 0, 0, 'L', TRUE);
            $pdf->Cell(0, 7, (string) $val, 0, 1, 'L', TRUE);
        }

        // ScopeSync footer watermark
        $pdf->SetY(-20);
        $pdf->SetFont(self::FONT, 'I', 8);
        $pdf->SetTextColor(180, 180, 180);
        $pdf->Cell(0, 5, 'Generated by ScopeSync · scopesync.app · ' . date('Y-m-d H:i') . ' UTC', 0, 0, 'C');
    }

    // -------------------------------------------------------------------------
    // Table of contents
    // -------------------------------------------------------------------------

    private function _buildTOC(TCPDF $pdf, array $data): void
    {
        $pdf->AddPage();

        [$r, $g, $b] = $this->_hexToRgb($data['primary_color']);

        $pdf->SetFillColor($r, $g, $b);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont(self::FONT, 'B', 14);
        $pdf->Cell(0, 10, 'Table of Contents', 0, 1, 'L', TRUE);
        $pdf->Ln(4);

        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFont(self::FONT, 'B', 9);
        $pdf->SetFillColor($r, $g, $b);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(80, 7, 'Catalog Number', 0, 0, 'L', TRUE);
        $pdf->Cell(50, 7, 'Category',       0, 0, 'L', TRUE);
        $pdf->Cell(0,  7, 'Decision',       0, 1, 'L', TRUE);

        $pdf->SetFont(self::FONT, '', 9);
        $odd = TRUE;
        foreach ($data['approved'] as $mr) {
            $mrId     = (int) $mr['id'];
            $decision = $data['decision_map'][$mrId]['decision'] ?? 'approved';
            $label    = $this->_decisionLabel($decision);

            $pdf->SetFillColor($odd ? 250 : 240, $odd ? 250 : 240, $odd ? 250 : 240);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Cell(80, 6, $mr['catalog_number'],              0, 0, 'L', TRUE);
            $pdf->Cell(50, 6, $mr['product_category'] ?? '—',     0, 0, 'L', TRUE);
            $pdf->Cell(0,  6, $label,                             0, 1, 'L', TRUE);
            $odd = ! $odd;
        }

        if ( ! empty($data['rejected'])) {
            $pdf->Ln(3);
            $pdf->SetFont(self::FONT, 'B', 9);
            $pdf->SetFillColor(220, 53, 69);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 7, 'Appendix: Rejected Products', 0, 1, 'L', TRUE);

            $pdf->SetFont(self::FONT, '', 9);
            $odd = TRUE;
            foreach ($data['rejected'] as $mr) {
                $pdf->SetFillColor($odd ? 255 : 248, $odd ? 240 : 235, $odd ? 240 : 235);
                $pdf->SetTextColor(30, 30, 30);
                $pdf->Cell(80, 6, $mr['catalog_number'],          0, 0, 'L', TRUE);
                $pdf->Cell(50, 6, $mr['product_category'] ?? '—', 0, 0, 'L', TRUE);
                $pdf->Cell(0,  6, 'Rejected',                     0, 1, 'L', TRUE);
                $odd = ! $odd;
            }
        }

        $pdf->SetY(-20);
        $pdf->SetFont(self::FONT, 'I', 8);
        $pdf->SetTextColor(180, 180, 180);
        $pdf->Cell(0, 5, 'Generated by ScopeSync · scopesync.app', 0, 0, 'C');
    }

    // -------------------------------------------------------------------------
    // Per-product section (approved + overridden only)
    // -------------------------------------------------------------------------

    private function _buildProductSection(TCPDF $pdf, array $data, array $mr, array $decision): void
    {
        $pdf->AddPage();

        [$r, $g, $b] = $this->_hexToRgb($data['primary_color']);

        $decisionLabel = $this->_decisionLabel($decision['decision'] ?? 'approved');
        $isOverride    = ($decision['decision'] ?? '') === 'overridden';

        // Section header bar
        $pdf->SetFillColor($r, $g, $b);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont(self::FONT, 'B', 13);
        $pdf->Cell(0, 10, $mr['catalog_number'] . '  —  ' . ($mr['product_category'] ?? 'Product'), 0, 1, 'L', TRUE);

        $pdf->SetFont(self::FONT, '', 9);
        $overallLabel = ucfirst($mr['overall_result']);
        $pdf->Cell(0, 6, 'Match Result: ' . $overallLabel . '  |  Decision: ' . $decisionLabel, 0, 1, 'L', TRUE);
        $pdf->Ln(4);

        // Source citation
        $sourceFile = $data['extraction_doc_map'][(int) $mr['cutsheet_extraction_id']] ?? 'Unknown';
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetFont(self::FONT, 'I', 8);
        $pdf->Cell(0, 5, 'Source: ' . $sourceFile, 0, 1, 'L');
        $pdf->Ln(2);

        // Decode attribute/listing results
        $decoded    = json_decode($mr['attribute_results'], TRUE) ?? [];
        $attrRows   = $decoded['attribute_results']   ?? [];
        $listRows   = $decoded['listing_results']     ?? [];

        // Attribute comparison table
        if ( ! empty($attrRows)) {
            $pdf->SetFont(self::FONT, 'B', 9);
            $pdf->SetFillColor($r, $g, $b);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(55, 7, 'Attribute',        0, 0, 'L', TRUE);
            $pdf->Cell(45, 7, 'Spec Requirement', 0, 0, 'L', TRUE);
            $pdf->Cell(45, 7, 'Product Value',    0, 0, 'L', TRUE);
            $pdf->Cell(0,  7, 'Result',           0, 1, 'L', TRUE);

            $pdf->SetFont(self::FONT, '', 9);
            $odd = TRUE;
            foreach ($attrRows as $ar) {
                [$fr, $fg, $fb] = $this->_resultRowColor($ar['result'] ?? '', $odd);
                $pdf->SetFillColor($fr, $fg, $fb);
                $pdf->SetTextColor(30, 30, 30);

                $specVal    = $this->_attrValue($ar['spec_value'] ?? null, $ar['spec_unit'] ?? null);
                $productVal = $this->_attrValue($ar['product_value'] ?? null, $ar['product_unit'] ?? null);
                $resultLbl  = ucfirst($ar['result'] ?? '—');

                $rowH = 6;
                $pdf->Cell(55, $rowH, $ar['attribute'] ?? '—', 0, 0, 'L', TRUE);
                $pdf->Cell(45, $rowH, $specVal,                0, 0, 'L', TRUE);
                $pdf->Cell(45, $rowH, $productVal,             0, 0, 'L', TRUE);
                $pdf->Cell(0,  $rowH, $resultLbl,              0, 1, 'L', TRUE);
                $odd = ! $odd;
            }
            $pdf->Ln(4);
        }

        // Listing results table
        if ( ! empty($listRows)) {
            $pdf->SetFont(self::FONT, 'B', 9);
            $pdf->SetFillColor($r, $g, $b);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(90, 7, 'Required Listing', 0, 0, 'L', TRUE);
            $pdf->Cell(70, 7, 'Matched Listing',  0, 0, 'L', TRUE);
            $pdf->Cell(0,  7, 'Result',           0, 1, 'L', TRUE);

            $pdf->SetFont(self::FONT, '', 9);
            $odd = TRUE;
            foreach ($listRows as $lr) {
                [$fr, $fg, $fb] = $this->_resultRowColor($lr['result'] ?? '', $odd);
                $pdf->SetFillColor($fr, $fg, $fb);
                $pdf->SetTextColor(30, 30, 30);

                $rowH = 6;
                $pdf->Cell(90, $rowH, $lr['required_listing'] ?? '—',      0, 0, 'L', TRUE);
                $pdf->Cell(70, $rowH, $lr['matched_listing']  ?? 'Missing', 0, 0, 'L', TRUE);
                $pdf->Cell(0,  $rowH, ucfirst($lr['result'] ?? '—'),        0, 1, 'L', TRUE);
                $odd = ! $odd;
            }
            $pdf->Ln(4);
        }

        // Override notes box
        if ($isOverride && ! empty($decision['override_notes'])) {
            $pdf->SetFillColor(255, 243, 205);
            $pdf->SetDrawColor(255, 193, 7);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetFont(self::FONT, 'B', 9);
            $pdf->Cell(0, 7, 'Override Justification', 1, 1, 'L', TRUE);
            $pdf->SetFont(self::FONT, '', 9);
            $pdf->SetFillColor(255, 248, 220);
            $pdf->MultiCell(0, 6, $decision['override_notes'], 1, 'L', TRUE);
            $pdf->Ln(2);
        }

        // Footer
        $pdf->SetY(-20);
        $pdf->SetFont(self::FONT, 'I', 8);
        $pdf->SetTextColor(180, 180, 180);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Cell(0, 5, 'Generated by ScopeSync · scopesync.app  |  Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');
    }

    // -------------------------------------------------------------------------
    // Rejected products appendix
    // -------------------------------------------------------------------------

    private function _buildRejectedAppendix(TCPDF $pdf, array $data): void
    {
        $pdf->AddPage();

        $pdf->SetFillColor(220, 53, 69);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont(self::FONT, 'B', 13);
        $pdf->Cell(0, 10, 'Appendix: Rejected Products', 0, 1, 'L', TRUE);
        $pdf->Ln(4);

        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFont(self::FONT, '', 9);
        $pdf->MultiCell(0, 6, 'The following products were reviewed and rejected. They are excluded from the submittal package but documented here for the record.', 0, 'L');
        $pdf->Ln(4);

        foreach ($data['rejected'] as $mr) {
            $mrId     = (int) $mr['id'];
            $decision = $data['decision_map'][$mrId] ?? [];
            $notes    = $decision['override_notes'] ?? '';

            $pdf->SetFillColor(255, 235, 235);
            $pdf->SetDrawColor(220, 53, 69);
            $pdf->SetFont(self::FONT, 'B', 10);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Cell(0, 7, $mr['catalog_number'] . '  —  ' . ($mr['product_category'] ?? 'Product'), 1, 1, 'L', TRUE);

            if ($notes) {
                $pdf->SetFont(self::FONT, 'B', 9);
                $pdf->Cell(30, 6, 'Rejection notes:', 0, 0, 'L');
                $pdf->SetFont(self::FONT, '', 9);
                $pdf->MultiCell(0, 6, $notes, 0, 'L');
            } else {
                $pdf->SetFont(self::FONT, 'I', 9);
                $pdf->SetTextColor(120, 120, 120);
                $pdf->Cell(0, 6, 'No rejection notes recorded.', 0, 1, 'L');
            }

            $pdf->SetTextColor(30, 30, 30);
            $pdf->Ln(3);
        }

        $pdf->SetY(-20);
        $pdf->SetFont(self::FONT, 'I', 8);
        $pdf->SetTextColor(180, 180, 180);
        $pdf->Cell(0, 5, 'Generated by ScopeSync · scopesync.app  |  Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _outputPath(int $tenantId, int $projectId, int $submittalId): string
    {
        return APPPATH . "../storage/tenants/{$tenantId}/projects/{$projectId}/submittals/{$submittalId}/output/package.pdf";
    }

    private function _hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            $hex = '0d9488'; // fallback teal
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function _decisionLabel(string $decision): string
    {
        return [
            'approved'   => 'Approved',
            'overridden' => 'Approved with Override',
            'rejected'   => 'Rejected',
        ][$decision] ?? ucfirst($decision);
    }

    private function _attrValue($value, $unit): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $str = (string) $value;
        if ($unit) {
            $str .= ' ' . $unit;
        }
        return $str;
    }

    /**
     * Returns [R, G, B] fill color for an attribute/listing result row.
     * Pass / fail / missing get subtle tints; alternating rows vary brightness.
     */
    private function _resultRowColor(string $result, bool $odd): array
    {
        if ($result === 'pass') {
            return $odd ? [235, 255, 235] : [220, 248, 220];
        }
        if ($result === 'fail') {
            return $odd ? [255, 235, 235] : [248, 220, 220];
        }
        if ($result === 'missing') {
            return $odd ? [255, 248, 220] : [248, 240, 205];
        }
        // unverifiable / unknown
        return $odd ? [245, 245, 245] : [235, 235, 235];
    }
}
