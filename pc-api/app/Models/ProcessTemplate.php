<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessTemplate extends Model
{
    use HasFactory;

    const INDUSTRY_SECURITY   = 'security';
    const INDUSTRY_BUILDING   = 'building';
    const INDUSTRY_TRANSPORT  = 'transport';
    const INDUSTRY_ENERGY     = 'energy';
    const INDUSTRY_INDUSTRIAL = 'industrial';

    protected $fillable = [
        'industry', 'category', 'code', 'name', 'description',
        'standard_duration_days', 'standard_man_hours',
        'required_qualifications', 'safety_requirements',
        'quality_checkpoints', 'acceptance_criteria',
        'sort_order', 'is_active', 'created_by',
    ];

    protected $casts = [
        'standard_duration_days' => 'integer',
        'standard_man_hours'     => 'decimal:2',
        'required_qualifications'=> 'array',
        'quality_checkpoints'    => 'array',
        'acceptance_criteria'    => 'array',
        'sort_order'             => 'integer',
        'is_active'              => 'boolean',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function instances(): HasMany { return $this->hasMany(ProcessInstance::class, 'template_id'); }

    public static function industries(): array
    {
        return [
            self::INDUSTRY_SECURITY   => '安防监控',
            self::INDUSTRY_BUILDING   => '楼宇自控',
            self::INDUSTRY_TRANSPORT  => '智能交通',
            self::INDUSTRY_ENERGY     => '能源电力',
            self::INDUSTRY_INDUSTRIAL => '工业自动化',
        ];
    }
}
