<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Concerns\HasDataScope;

/**
 * V0.4.4 整改工单主表
 *
 * 状态机: pending → in_progress → completed → verified (客户验收通过) / rejected
 * 关联: project, project_commencement_order, construction_log (父日志)
 *       internal_acceptance_by / customer_acceptance_by
 */
class Rectification extends Model
{
    use HasDataScope;
    protected $table = 'rectifications';

    protected $fillable = [
        'project_id', 'commencement_order_id', 'construction_log_id',
        'code', 'source_type', 'source_id',
        'title', 'description', 'severity',
        'responsible_id', 'deadline',
        'status',
        'internal_acceptance_at', 'internal_acceptance_by', 'internal_acceptance_remark',
        'customer_acceptance_at', 'customer_acceptance_by', 'customer_acceptance_remark',
        'images', 'complete_images', 'created_by', 'completed_by', 'completed_at', 'remark',
    ];

    protected $casts = [
        'deadline'                 => 'date',
        'internal_acceptance_at'   => 'datetime',
        'customer_acceptance_at'   => 'datetime',
        'completed_at'             => 'datetime',
        'images'                   => 'array',
        'complete_images'          => 'array',
    ];

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_VERIFIED    = 'verified';
    public const STATUS_REJECTED    = 'rejected';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function commencementOrder(): BelongsTo
    {
        return $this->belongsTo(ProjectCommencementOrder::class, 'commencement_order_id');
    }

    public function parentLog(): BelongsTo
    {
        return $this->belongsTo(ConstructionLog::class, 'construction_log_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function internalAcceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_acceptance_by');
    }

    public function customerAcceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_acceptance_by');
    }
}
