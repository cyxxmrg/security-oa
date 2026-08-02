<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.4.3 施工团队成员
 *
 * 表: construction_team_members
 * 主键: id
 *
 * 关系: team_id → construction_teams.id
 *       user_id → users.id (内部员工) 或 null (临时工)
 */
class ConstructionTeamMember extends Model
{
    protected $table = 'construction_team_members';

    protected $fillable = [
        'team_id', 'user_id', 'name', 'phone', 'role',
        'id_card', 'id_number', 'is_leader',
        'joined_at', 'left_at', 'join_date', 'leave_date',
        'status', 'remark',
    ];

    protected $casts = [
        'is_leader' => 'boolean',
        'joined_at' => 'date',
        'left_at'   => 'date',
        'join_date' => 'date',
        'leave_date' => 'date',
    ];

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_LEFT     = 'left';

    /** 角色 */
    public const ROLE_LEADER     = 'leader';     // 队长
    public const ROLE_FOREMAN    = 'foreman';    // 工头
    public const ROLE_WORKER     = 'worker';     // 普工
    public const ROLE_ELECTRICIAN = 'electrician'; // 电工
    public const ROLE_OPERATOR   = 'operator';   // 操作员
    public const ROLE_TEMP       = 'temp';       // 临时工

    // ========== 关联 ==========

    public function team(): BelongsTo
    {
        return $this->belongsTo(ConstructionTeam::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_LEADER      => '队长',
            self::ROLE_FOREMAN     => '工头',
            self::ROLE_WORKER      => '普工',
            self::ROLE_ELECTRICIAN => '电工',
            self::ROLE_OPERATOR    => '操作员',
            self::ROLE_TEMP        => '临时工',
            default                => $this->role,
        };
    }
}
