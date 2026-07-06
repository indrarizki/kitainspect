<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Employee;
use App\Models\Shiftment;

class Attendance extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'attendances';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'attendanceDate' => 'date',
        'checkIn' => 'string',
        'checkOut' => 'string',
        'earlyIn' => 'integer',
        'earlyOut' => 'integer',
        'lateIn' => 'integer',
        'lateOut' => 'integer',
        'absent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'attendanceDate',
        'description',
        'checkIn',
        'checkOut',
        'earlyIn',
        'earlyOut',
        'lateIn',
        'lateOut',
        'absent',
        'employee_id',
        'shiftment_id',
        'reason_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftment()
    {
        return $this->belongsTo(Shiftment::class);
    }

}
