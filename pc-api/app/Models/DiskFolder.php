<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiskFolder extends Model
{
    use HasFactory;

    /** 系统 scope 常量 */
    public const SCOPE_PROJECT_ROOT = 'project_root';
    public const SCOPE_WORK_ROOT    = 'work_root';
    public const SCOPE_SHARE_ROOT   = 'share_root';
    public const SCOPE_NONE         = 'none';

    /** 前端 system_type 常量（与前端 SYSTEM_LABELS 一致） */
    public const SYS_TYPE_PROJECT_ROOT = 'project_root';
    public const SYS_TYPE_PROJECT_DOC  = 'project_doc';
    public const SYS_TYPE_WORK         = 'work';
    public const SYS_TYPE_SHARE        = 'share';

    protected $fillable = [
        'parent_id', 'name', 'path', 'created_by', 'is_system',
        'project_id', 'scope', 'is_protected', 'employee_id', 'system_type',
    ];

    protected $casts = [
        'is_system'    => 'boolean',
        'is_protected' => 'boolean',
    ];

    public function parent(): BelongsTo { return $this->belongsTo(DiskFolder::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(DiskFolder::class, 'parent_id'); }
    public function files(): HasMany { return $this->hasMany(DiskFile::class, 'folder_id'); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class, 'project_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(User::class, 'employee_id'); }
}
