<?php

namespace App\Services;

use App\Models\AqlTable;
use App\Models\InspectionItem;
use App\Models\InspectionOrder;
use App\Models\TemplateCheckpoint;

class AqlService
{
    /**
     * Calculate and apply AQL values to an InspectionItem.
     * Called when item is created or produced_qty changes.
     */
    public function applyToItem(InspectionItem $item): void
    {
        $order  = $item->inspectionOrder;
        $method = $order->effectiveSamplingMethod();
        $level  = $order->effectiveInspectionLevel();

        switch ($method) {
            case 'check_all':
                $item->inspected_qty = $item->produced_qty;
                $item->inspected_pct = 100.0;
                $item->aql_accept_no = null; // no AQL limit — check all
                $item->aql_reject_no = null;
                break;

            case 'custom':
                $pct = $order->custom_sample_pct ?? $order->template->custom_sample_pct ?? 10;
                $qty = (int) ceil($item->produced_qty * ($pct / 100));
                $item->inspected_qty = max(1, $qty);
                $item->inspected_pct = round(($item->inspected_qty / $item->produced_qty) * 100, 2);
                $item->aql_accept_no = null;
                $item->aql_reject_no = null;
                break;

            default: // aql_2_5
                $aql = AqlTable::calculate($item->produced_qty, $level);
                $item->inspected_qty = $aql['sample_size'];
                $item->inspected_pct = $aql['pct'];
                $item->aql_accept_no = $aql['accept_no'];
                $item->aql_reject_no = $aql['reject_no'];
                break;
        }

        $item->save();
    }

    /**
     * Convert a value between units.
     * Supported: mm ↔ inches ↔ cm
     */
    public static function convertUnit(float $value, string $from, string $to): float
    {
        return TemplateCheckpoint::convertUnit($value, $from, $to);
    }

    /**
     * Validate a checkpoint response value against its target/range.
     * Returns ['is_pass', 'is_out_of_range', 'value_converted']
     */
    public static function validateResponse(
        TemplateCheckpoint $checkpoint,
        float $value,
        string $inputUnit,
        string $standardUnit
    ): array {
        // Convert to template standard unit for comparison
        $converted = self::convertUnit($value, $inputUnit, $standardUnit);

        $isOutOfRange = ! $checkpoint->isInRange($converted);
        $isPass       = ! $isOutOfRange;

        return [
            'value_converted' => $converted,
            'is_out_of_range' => $isOutOfRange,
            'is_pass'         => $isPass,
        ];
    }
}