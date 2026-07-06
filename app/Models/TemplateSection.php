<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateSection extends Model
{
    use HasUuids;

    protected $fillable = [
        'template_id', 'code', 'title',
        'section_type', 'order_index', 'is_required',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(TemplateCheckpoint::class, 'section_id')->orderBy('order_index');
    }
}