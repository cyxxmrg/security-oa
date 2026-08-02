<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * V0.4.3 工序模板 / 项目工序
 *
 * 表: work_processes
 * 主键: id
 *
 * 关系:
 *  - 属于一个 开工单 (commencement_order_id)
 *  - 实际进度: work_process_progress (1 对 1)
 *  - 工序日志: construction_logs.processes (json)
 */
class WorkProcess extends Model
{
    protected $table = 'work_processes';

    protected $fillable = [
        'commencement_order_id', 'project_id', 'name', 'code',
        'planned_start_date', 'planned_end_date',
        'planned_quantity', 'unit',
        'sort_order', 'parent_id', 'is_milestone', 'remark',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date'   => 'date',
        'planned_quantity'   => 'decimal:2',
        'sort_order'         => 'integer',
        'is_milestone'       => 'boolean',
    ];

    // ========== 关联 ==========

    public function commencementOrder(): BelongsTo
    {
        return $this->belongsTo(ProjectCommencementOrder::class, 'commencement_order_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WorkProcess::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WorkProcess::class, 'parent_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(WorkProcessProgress::class, 'process_id');
    }
}
