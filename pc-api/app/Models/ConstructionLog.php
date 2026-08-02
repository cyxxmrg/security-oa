<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Concerns\HasDataScope;

/**
 * V0.4.3 施工日志 (PSR-4 单文件,优先于 ProjectModels.php 聚合类)
 *
 * 表: construction_logs
 * 主键: id
 *
 * V0.4.3 扩展字段（保持向后兼容，原字段不动）:
 *  - commencement_order_id: 所属开工单
 *  - team_id: 所属施工团队
 *  - process_progress: json，记录当日每工序实绩 [{process_id, completed_qty, percentage}]
 *  - is_rectification: 是否整改日志
 *  - rectification_order_id: 关联的整改工单 (V0.4.4)
 *  - status: 提交/草稿/驳回
 */
class ConstructionLog extends Model
{
    use HasDataScope;
    protected $table = 'construction_logs';

    protected $fillable = [
        // 原 V0.4.1 字段（保持兼容）
        'project_id', 'user_id', 'work_date', 'weather',
        'content', 'problems', 'solutions', 'photos',
        'work_hours', 'location', 'status',
        // V0.4.3 扩展字段
        'commencement_order_id', 'team_id', 'process_progress',
        'is_rectification', 'rectification_order_id',
        'reviewer_id', 'reviewed_at', 'review_remark',
    ];

    protected $casts = [
        'photos'                 => 'array',
        'process_progress'       => 'array',
        'work_date'              => 'date',
        'work_hours'             => 'decimal:1',
        'is_rectification'       => 'boolean',
        'reviewed_at'            => 'datetime',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';

    // ========== 关联 ==========

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 施工员别名 — Controller 习惯用 operator:id,name，字段实际是 user_id */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ConstructionTeam::class, 'team_id');
    }

    public function commencementOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProjectCommencementOrder::class, 'commencement_order_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function dailyRequired(): HasMany
    {
        return $this->hasMany(RectificationDailyRequired::class, 'submitted_log_id');
    }
}
