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
 *  - 属于一个项目，project_id 为空时为通用工序
 *  - 实际进度: work_process_progress
 *  - 工序日志: construction_logs.processes (json)
 */
class WorkProcess extends Model
{
    protected $table = 'work_processes';

    protected $fillable = [
        'project_id', 'name', 'sequence', 'description',
        'estimated_hours', 'status',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'estimated_hours' => 'decimal:2',
    ];

    // ========== 关联 ==========

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(WorkProcessProgress::class, 'process_id');
    }
}
