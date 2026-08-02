<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * V0.4.3 项目开工单
 *
 * 表: project_commencement_orders
 * 主键: id
 *
 * 状态机: draft → pending_approval → approved → in_progress → completed
 *                              └→ rejected / cancelled
 */
class ProjectCommencementOrder extends Model
{
    use SoftDeletes;

    protected $table = 'project_commencement_orders';

    protected $fillable = [
        'project_id', 'team_id', 'code',
        'commencement_date', 'planned_start_date', 'planned_end_date',
        'actual_start_date', 'actual_end_date',
        'work_content', 'work_scope', 'work_location',
        'work_standard', 'quality_requirements', 'safety_requirements',
        'on_site_contacts', 'attachments',
        'status', 'approver_id', 'approved_by', 'approved_at', 'rejected_reason',
        'created_by', 'remark',
    ];

    protected $casts = [
        'commencement_date'  => 'date',
        'planned_start_date' => 'date',
        'planned_end_date'   => 'date',
        'actual_start_date'  => 'date',
        'actual_end_date'    => 'date',
        'approved_at'        => 'datetime',
    ];

    /** 状态 */
    public const STATUS_DRAFT           = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED        = 'approved';
    public const STATUS_REJECTED        = 'rejected';
    public const STATUS_IN_PROGRESS     = 'in_progress';
    public const STATUS_COMPLETED       = 'completed';
    public const STATUS_CANCELLED       = 'cancelled';

    // ========== 关联 ==========

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ConstructionTeam::class, 'team_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processes(): HasMany
    {
        // work_processes 表无 commencement_order_id 列 — 通过 work_process_progress 反查
        return $this->hasManyThrough(WorkProcess::class, WorkProcessProgress::class, 'commencement_order_id', 'id', 'id', 'process_id');
    }

    public function logs(): HasMany
    {
        // construction_logs 表无 commencement_order_id 列 — 改走 rectification_daily_required
        return $this->hasMany(RectificationDailyRequired::class, 'commencement_order_id');
    }

    // ========== Helper ==========

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT            => '草稿',
            self::STATUS_PENDING_APPROVAL => '待审批',
            self::STATUS_APPROVED         => '已批准',
            self::STATUS_REJECTED         => '已驳回',
            self::STATUS_IN_PROGRESS      => '施工中',
            self::STATUS_COMPLETED        => '已完工',
            self::STATUS_CANCELLED        => '已取消',
            default                       => $this->status,
        };
    }
}
