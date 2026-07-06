<?php

namespace App\Models;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;

class AttendanceSummary extends Model
{
    use HasUuids;

    protected $table = 'attendance_summaries';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'totalWorkday' => 'decimal:4',
        'totalIn' => 'decimal:4',
        'totalLoyalty' => 'decimal:4',
        'totalAbsent' => 'decimal:4',
        'totalOvertime' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'totalWorkday',
        'totalIn',
        'totalLoyalty',
        'totalAbsent',
        'totalOvertime',
        'import_batch_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
