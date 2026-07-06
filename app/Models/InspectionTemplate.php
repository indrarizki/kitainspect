<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'inspection_type_id', 'company_id', 'created_by',
        'name', 'description', 'version',
        'sampling_method', 'custom_sample_pct', 'inspection_level',
        'default_unit', 'status', 'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function inspectionType(): BelongsTo
    {
        return $this->belongsTo(InspectionType::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TemplateSection::class, 'template_id')->orderBy('order_index');
    }

    public function inspectionOrders(): HasMany
    {
        return $this->hasMany(InspectionOrder::class, 'template_id');
    }
}