<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ─── CheckpointResponse ───────────────────────────────────────────────────────
class CheckpointResponse extends Model
{
    use HasUuids;

    protected $fillable = [
        'inspection_item_id', 'checkpoint_id', 'sample_no',
        'value_text', 'value_number', 'value_unit',
        'value_converted', 'is_out_of_range', 'is_pass', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_out_of_range' => 'boolean',
            'is_pass'         => 'boolean',
            'value_number'    => 'decimal:4',
            'value_converted' => 'decimal:4',
        ];
    }

    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class);
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(TemplateCheckpoint::class, 'checkpoint_id');
    }
}