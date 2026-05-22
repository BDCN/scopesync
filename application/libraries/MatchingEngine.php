<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MatchingEngine — deterministic spec-vs-cut-sheet comparator.
 *
 * Usage:
 *   $this->load->library('MatchingEngine');
 *   $results = $this->matchingengine->run($specExtraction, $cutsheetExtractions);
 *
 * Each element of $results is a match-result array ready for Match_result_model::create().
 */
class MatchingEngine {

    // Attributes where ±2% numeric tolerance applies
    const TOLERANCE_PERCENT = ['voltage_rating' => 2];

    public function __construct()
    {
        // No CI instance needed — pure computation
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Run matching for one spec extraction against one or more cut sheet extractions.
     *
     * @param  array $specExtraction       Single extractions row (status=completed)
     * @param  array $cutsheetExtractions  Array of extractions rows (status=completed)
     * @return array  Array of match-result arrays; one per (spec product × variant)
     */
    public function run(array $specExtraction, array $cutsheetExtractions): array
    {
        $specData = json_decode($specExtraction['structured_data'], TRUE);
        if (empty($specData['products'])) {
            return [];
        }

        $results = [];

        foreach ($cutsheetExtractions as $csExtr) {
            $csData = json_decode($csExtr['structured_data'], TRUE);
            if (empty($csData['variants'])) {
                continue;
            }

            // Build common_attributes lookup (variant-level fallback)
            $commonAttrs = $this->_buildCommonAttrMap($csData['common_attributes'] ?? []);

            foreach ($csData['variants'] as $variant) {
                foreach ($specData['products'] as $specProduct) {
                    $results[] = $this->_matchVariantToProduct(
                        $specExtraction,
                        $csExtr,
                        $specProduct,
                        $variant,
                        $commonAttrs
                    );
                }
            }
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function _matchVariantToProduct(
        array $specExtr,
        array $csExtr,
        array $specProduct,
        array $variant,
        array $commonAttrs
    ): array {
        $variantAttrs = $this->_buildVariantAttrMap($variant);

        $attrResults         = [];
        $unmatchedSpecAttrs  = [];

        $passCount         = 0;
        $failCount         = 0;
        $missingCount      = 0;
        $unverifiableCount = 0;

        // Only compare is_required=true attributes
        foreach ($specProduct['required_attributes'] ?? [] as $specAttr) {
            if (empty($specAttr['is_required'])) {
                continue;
            }

            $attrName = strtolower(trim($specAttr['attribute'] ?? ''));
            if ($attrName === '') {
                continue;
            }

            // Find in variant attrs first, fall back to common_attributes
            $productAttr = $variantAttrs[$attrName] ?? $commonAttrs[$attrName] ?? null;

            if ($productAttr === null) {
                $attrResults[] = [
                    'attribute'      => $specAttr['attribute'],
                    'spec_value'     => $specAttr['value'],
                    'spec_unit'      => $specAttr['unit'] ?? null,
                    'product_value'  => null,
                    'product_unit'   => null,
                    'result'         => 'missing',
                    'spec_source'    => $specAttr['source'] ?? null,
                    'product_source' => null,
                ];
                $missingCount++;
                $unmatchedSpecAttrs[] = $specAttr['attribute'];
                continue;
            }

            $result = $this->_compareValues($attrName, $specAttr['value'], $productAttr['value']);

            $attrResults[] = [
                'attribute'      => $specAttr['attribute'],
                'spec_value'     => $specAttr['value'],
                'spec_unit'      => $specAttr['unit'] ?? null,
                'product_value'  => $productAttr['value'],
                'product_unit'   => $productAttr['unit'] ?? null,
                'result'         => $result,
                'spec_source'    => $specAttr['source'] ?? null,
                'product_source' => $productAttr['source'] ?? null,
            ];

            switch ($result) {
                case 'pass':         $passCount++;         break;
                case 'fail':         $failCount++;         break;
                case 'unverifiable': $unverifiableCount++; break;
            }
        }

        // Listing checks
        $listingResults = $this->_checkListings(
            $specProduct['listings_required'] ?? [],
            $variant
        );

        foreach ($listingResults as $lr) {
            if ($lr['result'] === 'pass') {
                $passCount++;
            } else {
                $failCount++;
            }
        }

        $overallResult = $this->_deriveOverallResult(
            $passCount, $failCount, $missingCount, $unverifiableCount
        );

        return [
            'spec_extraction_id'        => (int) $specExtr['id'],
            'cutsheet_extraction_id'    => (int) $csExtr['id'],
            'tenant_id'                 => (int) $specExtr['tenant_id'],
            'submittal_job_id'          => (int) $specExtr['submittal_job_id'],
            'catalog_number'            => $variant['catalog_number'] ?? 'UNKNOWN',
            'product_category'          => $specProduct['product_category'] ?? null,
            'overall_result'            => $overallResult,
            'attribute_results'         => $attrResults,
            'listing_results'           => $listingResults,
            'unmatched_spec_attributes' => $unmatchedSpecAttrs,
        ];
    }

    /**
     * Compare a spec attribute value against a product attribute value.
     * Returns: 'pass' | 'fail' | 'unverifiable'
     */
    private function _compareValues(string $attrName, $specValue, $productValue): string
    {
        if ($specValue === null || $specValue === '' || $productValue === null || $productValue === '') {
            return 'unverifiable';
        }

        $sv = trim((string) $specValue);
        $pv = trim((string) $productValue);

        if (is_numeric($sv) && is_numeric($pv)) {
            $svf = (float) $sv;
            $pvf = (float) $pv;

            $tolerancePct = self::TOLERANCE_PERCENT[$attrName] ?? 0;
            if ($tolerancePct > 0 && $svf != 0) {
                $tolerance = abs($svf) * ($tolerancePct / 100);
                return (abs($svf - $pvf) <= $tolerance) ? 'pass' : 'fail';
            }

            return ($svf == $pvf) ? 'pass' : 'fail';
        }

        return (strtolower($sv) === strtolower($pv)) ? 'pass' : 'fail';
    }

    private function _checkListings(array $specListings, array $variant): array
    {
        $variantListings = [];
        foreach ($variant['listings_and_certifications'] ?? [] as $l) {
            if ( ! empty($l['listing'])) {
                $variantListings[] = $l['listing'];
            }
        }

        $results = [];
        foreach ($specListings as $req) {
            $required = $req['listing'] ?? '';
            if ($required === '') {
                continue;
            }

            $matched = $this->_findListingMatch($required, $variantListings);
            $results[] = [
                'required_listing' => $required,
                'result'           => $matched !== null ? 'pass' : 'missing',
                'spec_source'      => $req['source'] ?? null,
                'matched_listing'  => $matched,
            ];
        }

        return $results;
    }

    private function _findListingMatch(string $required, array $available): ?string
    {
        $reqLower = strtolower(trim($required));
        foreach ($available as $avail) {
            if (strpos(strtolower(trim($avail)), $reqLower) !== FALSE) {
                return $avail;
            }
        }
        return null;
    }

    private function _deriveOverallResult(
        int $pass, int $fail, int $missing, int $unverifiable
    ): string {
        $totalActionable = $pass + $fail + $missing;

        if ($totalActionable === 0) {
            return 'unverifiable';
        }

        if ($fail === 0 && $missing === 0) {
            return 'pass';
        }

        if ($pass === 0) {
            return 'fail';
        }

        return 'partial';
    }

    private function _buildVariantAttrMap(array $variant): array
    {
        $map = [];
        foreach ($variant['attributes'] ?? [] as $a) {
            $name = strtolower(trim($a['name'] ?? ''));
            if ($name !== '') {
                $map[$name] = $a;
            }
        }

        // Also expose top-level nema_configuration if not already in attributes
        if ( ! empty($variant['nema_configuration']) && ! isset($map['nema_configuration'])) {
            $map['nema_configuration'] = [
                'name'   => 'nema_configuration',
                'value'  => $variant['nema_configuration'],
                'unit'   => null,
                'source' => ['page' => 1, 'location' => 'product header'],
            ];
        }

        return $map;
    }

    private function _buildCommonAttrMap(array $commonAttributes): array
    {
        $map = [];
        foreach ($commonAttributes as $a) {
            $name = strtolower(trim($a['name'] ?? ''));
            if ($name !== '') {
                $map[$name] = $a;
            }
        }
        return $map;
    }
}
