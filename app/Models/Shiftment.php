<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shiftment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'shiftments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'startHour' => 'string',
        'endHour' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function workShifts()
    {
        return $this->hasMany(WorkShift::class);
    }

    public function overtimes()
    {
        return $this->hasMany(Overtime::class);
    }
}
