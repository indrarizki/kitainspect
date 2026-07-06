<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefectRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'inspection_item_id', 'checkpoint_id', 'sample_no',
        'defect_type', 'severity', 'qty', 'action', 'remarks',
    ];

    protected function casts(): array
    {
        return ['qty' => 'integer'];
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