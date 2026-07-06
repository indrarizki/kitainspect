<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateCheckpoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'section_id', 'label', 'input_type',
        'unit', 'allow_unit_conversion',
        'target_value', 'min_value', 'max_value',
        'severity', 'is_required', 'has_photo',
        'options', 'description', 'order_index',
    ];

    protected function casts(): array
    {
        return [
            'options'               => 'array',
            'allow_unit_conversion' => 'boolean',
            'is_required'           => 'boolean',
            'has_photo'             => 'boolean',
            'target_value'          => 'decimal:4',
            'min_value'             => 'decimal:4',
            'max_value'             => 'decimal:4',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TemplateSection::class, 'section_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Check if a value is within the allowed range.
     */
    public function isInRange(float $value): bool
    {
        if ($this->min_value !== null && $value < $this->min_value) return false;
        if ($this->max_value !== null && $value > $this->max_value) return false;
        return true;
    }

    /**
     * Convert value between units.
     * Supports: mm ↔ inches ↔ cm
     */
    public static function convertUnit(float $value, string $from, string $to): float
    {
        if ($from === $to) return $value;

        // Convert to mm first
        $inMm = match ($from) {
            'inches' => $value * 25.4,
            'cm'     => $value * 10,
            default  => $value, // already mm
        };

        // Convert from mm to target
        return match ($to) {
            'inches' => $inMm / 25.4,
            'cm'     => $inMm / 10,
            default  => $inMm,
        };
    }
}