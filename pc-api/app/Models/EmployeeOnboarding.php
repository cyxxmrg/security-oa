<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOnboarding extends Model
{
    use HasFactory;
    protected $table = 'employee_onboardings';
    protected $fillable = [
        'user_id', 'hire_date', 'department_id', 'position_id', 'mentor_id',
        'probation_months', 'probation_end_date', 'contract_start', 'contract_end',
        'id_card_no', 'id_card_file_id',
        'driver_license_no', 'driver_license_expire', 'driver_license_file_id',
        'education_level', 'education_school', 'education_major', 'education_file_id',
        'contract_file_id', 'status', 'remark', 'onboarded_by',
    ];
    protected $casts = [
        'hire_date' => 'date', 'probation_end_date' => 'date',
        'contract_start' => 'date', 'contract_end' => 'date',
        'driver_license_expire' => 'date',
        'id_card_no' => 'encrypted',
    ];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function mentor(): BelongsTo { return $this->belongsTo(User::class, 'mentor_id'); }
    public function onboarder(): BelongsTo { return $this->belongsTo(User::class, 'onboarded_by'); }
    public function idCardFile(): BelongsTo { return $this->belongsTo(DiskFile::class, 'id_card_file_id'); }
    public function driverLicenseFile(): BelongsTo { return $this->belongsTo(DiskFile::class, 'driver_license_file_id'); }
    public function educationFile(): BelongsTo { return $this->belongsTo(DiskFile::class, 'education_file_id'); }
    public function contractFile(): BelongsTo { return $this->belongsTo(DiskFile::class, 'contract_file_id'); }
}
