<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Payroll;
use App\Models\Department;
use App\Models\AttendanceSummary;

class Employee extends Model
{
    use HasUuids;

    protected $table = 'employees';
    protected $keyType = 'string';
    public $incrementing = false;

    

    protected $guarded = [];

    protected $casts = [
        'joinDate' => 'date',
        'dateOfBirth' => 'date',
        'resignDate' => 'date',
        'leaveBalance' => 'integer',
        'haveOvertimeBenefit' => 'boolean',
        'roles' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function attedanceSummary()
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function workShifts()
    {
        return $this->hasMany(WorkShift::class);
    }

    public function overtimes()
    {
        return $this->hasMany(Overtime::class);
    }
}
