<?php

namespace App\Services;

use App\Models\ConstructionLog;
use App\Models\ConstructionTeam;
use App\Models\ConstructionTeamMember;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.3 施工团队服务
 *
 * 关键能力:
 *  - createTeam: 创建团队 + 自动建空成员表
 *  - addMembers: 批量加成员 (DB::transaction)
 *  - removeMember / disbandTeam: 移除/解散
 *  - getTeamStats: 团队聚合统计
 */
class ConstructionTeamService
{
    /**
     * 创建团队
     *
     * @param array $data team_name, team_type, leader_user_id, leader_name, leader_phone, specialty ...
     */
    public function createTeam(?int $projectId, array $data, int $userId): ConstructionTeam
    {
        return DB::transaction(function () use ($projectId, $data, $userId) {
            $team = ConstructionTeam::create([
                'project_id'      => $projectId,
                'team_name'       => $data['team_name'],
                'team_type'       => $data['team_type'] ?? ConstructionTeam::TYPE_INTERNAL,
                'leader_user_id'  => $data['leader_user_id'] ?? null,
                'leader_name'     => $data['leader_name']     ?? null,
                'leader_phone'    => $data['leader_phone']    ?? null,
                'member_count'    => 0,
                'specialty'       => $data['specialty']       ?? null,
                'status'          => $data['status']          ?? ConstructionTeam::STATUS_ACTIVE,
                'created_by'      => $userId,
            ]);

            // 如果传了初始成员，批量加入
            if (!empty($data['members']) && is_array($data['members'])) {
                $this->addMembersInternal($team, $data['members']);
            }

            return $team->fresh(['members', 'leader']);
        });
    }

    /**
     * 更新团队信息（含停用/启用）
     */
    public function updateTeam(int $id, array $data): ConstructionTeam
    {
        $team = ConstructionTeam::findOrFail($id);
        $team->update($data);
        return $team->fresh();
    }

    /**
     * 批量加成员
     *
     * @param array $members [{user_id, name, phone, role, is_leader, ...}]
     */
    public function addMembers(int $teamId, array $members): ConstructionTeam
    {
        return DB::transaction(function () use ($teamId, $members) {
            $team = ConstructionTeam::findOrFail($teamId);
            if ($team->status === ConstructionTeam::STATUS_DISBANDED) {
                throw new \RuntimeException('团队已解散，无法添加成员');
            }

            $this->addMembersInternal($team, $members);

            return $team->fresh(['members']);
        });
    }

    /**
     * 内部成员追加逻辑
     */
    private function addMembersInternal(ConstructionTeam $team, array $members): void
    {
        foreach ($members as $row) {
            ConstructionTeamMember::create([
                'team_id'    => $team->id,
                'user_id'    => $row['user_id']    ?? null,
                'name'       => $row['name']       ?? '',
                'phone'      => $row['phone']      ?? null,
                'role'       => $row['role']       ?? ConstructionTeamMember::ROLE_WORKER,
                'id_number'  => $row['id_card']    ?? $row['id_number'] ?? null,
                'is_leader'  => (bool) ($row['is_leader'] ?? false),
                'join_date'  => $row['joined_at']  ?? $row['join_date'] ?? now()->toDateString(),
                'leave_date' => $row['left_at']    ?? $row['leave_date'] ?? null,
                'status'     => ConstructionTeamMember::STATUS_ACTIVE,
                'remark'     => $row['remark']     ?? null,
            ]);
        }

        // 刷新 member_count
        $count = $team->members()->where('status', ConstructionTeamMember::STATUS_ACTIVE)->count();
        $team->update(['member_count' => $count]);
    }

    /**
     * 移除单个成员 (软方式：填 left_at)
     */
    public function removeMember(int $teamId, int $memberId): ConstructionTeam
    {
        return DB::transaction(function () use ($teamId, $memberId) {
            $team = ConstructionTeam::findOrFail($teamId);
            if ($team->status === ConstructionTeam::STATUS_DISBANDED) {
                throw new \RuntimeException('团队已解散');
            }

            $member = $team->members()->where('id', $memberId)->first();
            if (!$member) {
                throw new \RuntimeException('成员不存在');
            }

            $member->update(['leave_date' => now()->toDateString()]);

            $count = $team->members()->whereNull('leave_date')->count();
            $team->update(['member_count' => $count]);

            return $team->fresh(['members']);
        });
    }

    /**
     * 解散团队
     */
    public function disbandTeam(int $teamId, ?string $reason = null): ConstructionTeam
    {
        return DB::transaction(function () use ($teamId, $reason) {
            $team = ConstructionTeam::findOrFail($teamId);
            if ($team->status === ConstructionTeam::STATUS_DISBANDED) {
                throw new \RuntimeException('团队已解散，无需重复操作');
            }

            $team->update([
                'status'        => ConstructionTeam::STATUS_DISBANDED,
                'remark'        => $team->remark
                    ? ($team->remark . "\n[解散] " . ($reason ?? now()->toDateTimeString()))
                    : ('[解散] ' . ($reason ?? now()->toDateTimeString())),
            ]);

            // 团队下所有未离开成员也置离开日期（leave_date）
            $team->members()->whereNull('leave_date')->update(['leave_date' => now()->toDateString()]);

            return $team->fresh();
        });
    }

    /**
     * 团队统计
     *
     * @return array{
     *   team: ConstructionTeam,
     *   member_total: int,
     *   active_member: int,
     *   log_count: int,
     *   last_log_date: ?string,
     *   ongoing_orders: int,
     * }
     */
    public function getTeamStats(int $teamId): array
    {
        $team = ConstructionTeam::with(['members', 'leader:id,name'])->findOrFail($teamId);

        $memberTotal   = $team->members()->count();
        $activeMember  = $team->members()->whereNull('leave_date')->count();
        $logCount      = $team->logs()->count();
        $lastLogDate   = $team->logs()->max('work_date');
        $ongoingOrders = $team->commencementOrders()
            ->where('status', ProjectCommencementOrder::STATUS_IN_PROGRESS)
            ->count();

        return [
            'team'           => $team,
            'member_total'   => $memberTotal,
            'active_member'  => $activeMember,
            'log_count'      => $logCount,
            'last_log_date'  => $lastLogDate,
            'ongoing_orders' => $ongoingOrders,
        ];
    }

    /**
     * 团队列表 (按项目过滤)
     */
    public function listTeams(int $projectId, array $filters = []): array
    {
        $q = ConstructionTeam::with(['leader:id,name', 'creator:id,name'])
            ->withCount('members')
            ->where('project_id', $projectId);

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['team_type'])) {
            $q->where('team_type', $filters['team_type']);
        }
        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $q->where(function ($w) use ($kw) {
                $w->where('team_name', 'like', "%{$kw}%")
                  ->orWhere('leader_name', 'like', "%{$kw}%");
            });
        }

        $total = (clone $q)->count();
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $size  = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $items = $q->orderByDesc('id')->skip(($page - 1) * $size)->take($size)->get();

        return ['items' => $items, 'total' => $total];
    }
}
