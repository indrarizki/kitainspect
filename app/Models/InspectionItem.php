<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ─── InspectionItem ───────────────────────────────────────────────────────────
class InspectionItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'inspection_order_id', 'item_no',
        'product_name', 'product_code', 'drawing_number', 'dimensions',
        'produced_qty', 'inspected_qty', 'inspected_pct',
        'aql_accept_no', 'aql_reject_no',
        'total_minor_defects', 'total_major_defects', 'total_critical_defects',
        'result', 'inspector_remarks',
    ];

    protected function casts(): array
    {
        return [
            'produced_qty'          => 'integer',
            'inspected_qty'         => 'integer',
            'total_minor_defects'   => 'integer',
            'total_major_defects'   => 'integer',
            'total_critical_defects'=> 'integer',
        ];
    }

    public function inspectionOrder(): BelongsTo
    {
        return $this->belongsTo(InspectionOrder::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CheckpointResponse::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(DefectRecord::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(InspectionEvidence::class);
    }

    /**
     * Recalculate defect totals and auto-determine pass/fail.
     */
    public function recalculateResult(): void
    {
        $minor    = $this->defects()->where('severity', 'minor')->sum('qty');
        $major    = $this->defects()->where('severity', 'major')->sum('qty');
        $critical = $this->defects()->where('severity', 'critical')->sum('qty');

        $this->total_minor_defects    = $minor;
        $this->total_major_defects    = $major;
        $this->total_critical_defects = $critical;

        // Critical defect = auto fail
        if ($critical > 0) {
            $this->result = 'fail';
        } elseif ($this->aql_reject_no !== null && $major >= $this->aql_reject_no) {
            $this->result = 'fail';
        } elseif ($this->aql_accept_no !== null && $major <= $this->aql_accept_no) {
            $this->result = 'pass';
        } else {
            $this->result = 'conditional';
        }

        $this->save();
    }
}