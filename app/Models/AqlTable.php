<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AqlTable extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lot_size_from', 'lot_size_to',
        'inspection_level', 'sample_size',
        'accept_no', 'reject_no',
    ];

    /**
     * Lookup AQL values for a given lot size and inspection level.
     *
     * @param  int     $lotSize         Total produced quantity
     * @param  string  $level           'I' | 'II' | 'III'
     * @return static|null
     */
    public static function lookup(int $lotSize, string $level = 'II'): ?self
    {
        return self::where('inspection_level', $level)
            ->where('lot_size_from', '<=', $lotSize)
            ->where('lot_size_to', '>=', $lotSize)
            ->first();
    }

    /**
     * Calculate inspected qty and return AQL values.
     * Returns ['sample_size', 'accept_no', 'reject_no', 'pct']
     */
    public static function calculate(int $lotSize, string $level = 'II'): array
    {
        $row = self::lookup($lotSize, $level);

        if (! $row) {
            return [
                'sample_size' => $lotSize, // fallback: check all
                'accept_no'   => 0,
                'reject_no'   => 1,
                'pct'         => 100.0,
            ];
        }

        return [
            'sample_size' => $row->sample_size,
            'accept_no'   => $row->accept_no,
            'reject_no'   => $row->reject_no,
            'pct'         => round(($row->sample_size / $lotSize) * 100, 2),
        ];
    }
}