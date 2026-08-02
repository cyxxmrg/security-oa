<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * V0.4.3 施工团队
 *
 * 表: construction_teams
 * 主键: id
 *
 * 关系:
 *  - 一个 team 属于一个 project
 *  - 一个 team 有一个 leader (User)
 *  - 一个 team 有多个 member (ConstructionTeamMember)
 *  - 一个 team 有多条施工日志 (ConstructionLog)
 */
class ConstructionTeam extends Model
{
    use SoftDeletes;

    protected $table = 'construction_teams';

    protected $fillable = [
        'project_id', 'team_name', 'team_type', 'leader_user_id',
        'leader_name', 'leader_phone', 'member_count', 'specialty',
        'status', 'created_by', 'remark',
    ];

    protected $casts = [
        'member_count' => 'integer',
    ];

    /** 团队类型 */
    public const TYPE_INTERNAL  = 'internal';   // 内部团队
    public const TYPE_EXTERNAL  = 'external';   // 外部施工队
    public const TYPE_OUTSOURCE = 'outsource';  // 外包

    /** 状态 */
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_INACTIVE  = 'inactive';
    public const STATUS_DISBANDED = 'disbanded';

    // ========== 关联 ==========

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConstructionTeamMember::class, 'team_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ConstructionLog::class, 'team_id');
    }

    public function commencementOrders(): HasMany
    {
        return $this->hasMany(ProjectCommencementOrder::class, 'team_id');
    }

    // ========== Scope / Helper ==========

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->team_type) {
            self::TYPE_INTERNAL  => '内部',
            self::TYPE_EXTERNAL  => '外部',
            self::TYPE_OUTSOURCE => '外包',
            default              => $this->team_type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE    => '在用',
            self::STATUS_INACTIVE  => '停用',
            self::STATUS_DISBANDED => '已解散',
            default                => $this->status,
        };
    }
}
