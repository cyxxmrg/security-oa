<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectStageLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * V1.2.12m 项目阶段自动推进服务
 *
 * 阶段流转规则 (6 段):
 *   ① 进场准备 (mobilization)
 *   ② 现场施工 (construction)   ← 首条施工日志
 *   ③ 验收交付 (acceptance)     ← 工序验收全部通过
 *   ④ 项目结算 (settlement)     ← 结算金额 ≥ 合同金额
 *   ⑤ 售后质保 (warranty)       ← 创建质保期
 *   ⑥ 已关闭   (closed)         ← 质保期全部到期/关闭
 *
 * 特性:
 *   - 幂等: 已达目标或超过时不重复推进
 *   - 可回退: target 比当前旧时记录 skip
 *   - 自动写 project_stage_logs 流水
 *   - 失败不抛异常 (log 警告)
 */
class ProjectStageService
{
    public const STAGE_ORDER = [
        'mobilization' => 1,
        'construction' => 2,
        'acceptance'   => 3,
        'settlement'   => 4,
        'warranty'     => 5,
        'closed'       => 6,
    ];

    /**
     * 把项目推进到目标阶段 (仅前向; 不会回退)
     *
     * @return bool 是否实际发生了推进
     */
    public function advance(Project $project, string $targetStage, ?string $note = null, ?int $userId = null): bool
    {
        try {
            $current = $project->stage instanceof \BackedEnum ? $project->stage->value : (string) $project->stage;
            $currentOrder = self::STAGE_ORDER[$current] ?? 0;
            $targetOrder  = self::STAGE_ORDER[$targetStage] ?? 0;

            // 目标非法/已经超过/相等 → 跳过
            if ($targetOrder === 0) {
                Log::warning("ProjectStageService::advance invalid target", ['project' => $project->id, 'target' => $targetStage]);
                return false;
            }
            if ($targetOrder <= $currentOrder) {
                return false;  // 不回退、不重复
            }

            DB::transaction(function () use ($project, $current, $targetStage, $note, $userId) {
                $project->update(['stage' => $targetStage]);
                ProjectStageLog::create([
                    'project_id'  => $project->id,
                    'stage_key'   => $targetStage,
                    'action'      => 'enter',
                    'note'        => $note ?: "自动从 [$current] 推进到 [$targetStage]",
                    'operator_id' => $userId,
                    'created_at'  => now(),
                ]);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error("ProjectStageService::advance failed: " . $e->getMessage(), [
                'project' => $project->id, 'target' => $targetStage, 'note' => $note,
            ]);
            return false;
        }
    }

    /**
     * 触发规则: 创建第一条施工日志
     *   → mobilization → construction
     */
    public function onFirstConstructionLog(int $projectId, ?int $userId = null): bool
    {
        $count = DB::table('construction_logs')->where('project_id', $projectId)->count();
        if ($count !== 1) return false;  // 必须是第一条
        $project = Project::find($projectId);
        return $project ? $this->advance($project, 'construction', '自动推进: 创建了第一条施工日志', $userId) : false;
    }

    /**
     * 触发规则: 工序验收全部完成
     *   → construction → acceptance
     */
    public function onAllProcessInspectionsPassed(int $projectId, ?int $userId = null): bool
    {
        $total = DB::table('process_inspections as pi')
            ->join('process_instances as pin', 'pi.process_instance_id', '=', 'pin.id')
            ->where('pin.project_id', $projectId)
            ->count();
        if ($total === 0) return false;
        $passed = DB::table('process_inspections as pi')
            ->join('process_instances as pin', 'pi.process_instance_id', '=', 'pin.id')
            ->where('pin.project_id', $projectId)
            ->whereIn('pi.result', ['pass', 'partial'])  // pass + partial 都算过
            ->count();
        if ($passed < $total) return false;
        $project = Project::find($projectId);
        return $project ? $this->advance($project, 'acceptance', "自动推进: 工序验收全部通过 ($passed/$total)", $userId) : false;
    }

    /**
     * 触发规则: 项目结算累计金额 ≥ 合同金额
     *   → acceptance → settlement
     */
    public function onSettlementReachedContract(int $projectId, ?int $userId = null): bool
    {
        $project = Project::find($projectId);
        if (!$project) return false;
        $settled = (float) DB::table('project_settlements')->where('project_id', $projectId)->sum('total_income');
        $contract = (float) $project->getAttribute('contract_amount');  // accessor 已算合同总额
        if ($contract > 0 && $settled >= $contract) {
            return $this->advance($project, 'settlement', "自动推进: 结算金额 ¥{$settled} ≥ 合同金额 ¥{$contract}", $userId);
        }
        return false;
    }

    /**
     * 触发规则: 创建质保期
     *   → settlement → warranty
     */
    public function onWarrantyCreated(int $projectId, ?int $userId = null): bool
    {
        $project = Project::find($projectId);
        return $project ? $this->advance($project, 'warranty', '自动推进: 创建了质保期', $userId) : false;
    }

    /**
     * 触发规则: 质保期全部关闭 (terminated/expired)
     *   → warranty → closed
     */
    public function onAllWarrantyClosed(int $projectId, ?int $userId = null): bool
    {
        $total = DB::table('warranties')->where('project_id', $projectId)->count();
        if ($total === 0) return false;
        $closed = DB::table('warranties')
            ->where('project_id', $projectId)
            ->whereIn('status', ['terminated', 'expired', 'closed'])
            ->count();
        if ($closed < $total) return false;
        $project = Project::find($projectId);
        return $project ? $this->advance($project, 'closed', "自动推进: 质保期全部关闭 ($closed/$total)", $userId) : false;
    }
}
