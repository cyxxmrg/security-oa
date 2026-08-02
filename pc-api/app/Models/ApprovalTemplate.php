<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 审批流程模板
 *
 * DB 表: approval_templates
 * 字段: id, name, type, module, description, steps(json), enabled(bool),
 *      sort_order, created_by, updated_by, created_at, updated_at
 *
 * 注意: 历史前端用 status/nodes 字段，本 Model 实际表用 enabled/steps
 */
class ApprovalTemplate extends Model
{
    protected $table = 'approval_templates';

    protected $fillable = [
        'name', 'type', 'module', 'description', 'steps', 'enabled', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'steps'    => 'array',
        'enabled'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
