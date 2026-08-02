<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id', 'certificate_name', 'certificate_no',
        'issue_date', 'expire_date', 'issuer', 'status', 'attachment', 'remind_days',
    ];

    protected $casts = [
        'issue_date' => 'date', 'expire_date' => 'date',
    ];

    public function profile(): BelongsTo { return $this->belongsTo(EmployeeProfile::class); }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expire_date && $this->expire_date->diffInDays(now()) <= $days;
    }
}
