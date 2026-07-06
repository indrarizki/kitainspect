<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ─── InspectionEvidence ───────────────────────────────────────────────────────
class InspectionEvidence extends Model
{
    use HasUuids;

    protected $table = 'inspection_evidences';

    protected $fillable = [
        'inspection_item_id', 'checkpoint_id',
        'caption', 'file_url', 'file_type',
        'taken_at', 'order_index',
    ];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime'];
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