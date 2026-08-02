<?php

namespace App\Services;

use App\Models\ConstructionLog;
use App\Models\ProjectCommencementOrder;
use App\Models\RectificationDailyRequired;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.3 整改服务 (V0.4.4 占位骨架)
 *
 * 当前提供接口签名 & 最少实现,后续 V0.4.4 完善:
 *  - 整改工单表 (rectification_orders) 在 V0.4.4 引入
 *  - 当前仅打通: 提交整改日志 + 标记日报需求 is_rectification
 *
 * 注意: 当前不创建独立整改工单表,通过 construction_logs.is_rectification + rectification_order_id
 *       (nullable) 标识,V0.4.4 接入 rectification_orders 主表
 */
class RectificationService
{
    /**
     * 创建整改工单 (V0.4.4)
     *
     * 1. 写 rectifications 主表 (新表)
     * 2. (可选) 联动 rectification_daily_required
     *
     * @return array{rect: Rectification, placeholder: false, message: string}
     */
    public function createRectification(int $projectId, array $data, int $userId): array
    {
        $code = $this->generateCode();

        $rect = \App\Models\Rectification::create([
            'project_id'           => $projectId,
            'commencement_order_id'=> $data['commencement_order_id'] ?? null,
            'construction_log_id'  => $data['construction_log_id']   ?? null,
            'code'                 => $code,
            'source_type'          => $data['source_type']   ?? 'other',
            'source_id'            => $data['source_id']     ?? null,
            'title'                => $data['title']         ?? '整改任务',
            'description'          => $data['description']   ?? ($data['content'] ?? ''),
            'severity'             => $data['severity']      ?? 'medium',
            'responsible_id'       => $data['responsible_id']?? null,
            'deadline'             => $data['deadline']      ?? null,
            'status'               => \App\Models\Rectification::STATUS_PENDING,
            'images'               => $data['images']        ?? null,
            'created_by'           => $userId,
        ]);

        return [
            'rect'       => $rect,
            'placeholder' => false,
            'message'    => '整改单已创建，待内部验收',
        ];
    }

    /**
     * 生成整改单号 RECT-YYYY-NNNN
     */
    public function generateCode(): string
    {
        $year   = date('Y');
        $prefix = "RECT-{$year}-";
        $latest = \App\Models\Rectification::where('code', 'like', $prefix . '%')
            ->orderByDesc('id')->value('code');
        $next = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) {
            $next = ((int) $m[1]) + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 提交整改日志 (V0.4.3 入口)
     *
     * 行为:
     *  - 调 ConstructionLogService::submitLog
     *  - 联动 rectification_daily_required (通过 rectificationOrderId 关联)
     */
    public function submitRectificationLog(
        array $data,
        int $userId,
        bool $isRectification = true,
        ?int $rectificationOrderId = null
    ): ConstructionLog {
        $log = app(ConstructionLogService::class)->submitLog(array_merge($data, [
            'is_rectification'      => $isRectification,
            'rectification_order_id' => $rectificationOrderId,
        ]), $userId);

        // 把对应日报需求标记为已提交 (整改完成)
        RectificationDailyRequired::where('project_id', $log->project_id)
            ->where('work_date', $log->work_date)
            ->update([
                'status'              => RectificationDailyRequired::STATUS_SUBMITTED,
                'submitted_log_id'    => $log->id,
                'updated_at'          => now(),
            ]);

        return $log;
    }

    /**
     * V0.4.4 接口占位: 创建正式整改工单
     */
    public function createRectificationOrder(int $projectId, array $data, int $userId): array
    {
        // V0.4.4 实现 rectification_orders 表
        return [
            'placeholder' => true,
            'message'     => 'V0.4.4 待实现 rectification_orders 主表',
            'project_id'  => $projectId,
            'data'        => $data,
        ];
    }

    /**
     * 标记日报需求为整改 (V0.4.4 入口)
     *
     * 由 ScanOverdueConstructionLogs Command 在连续 3 天 overdue 时调用
     */
    public function markOverdueAsRectification(int $requiredId, int $userId): RectificationDailyRequired
    {
        return DB::transaction(function () use ($requiredId, $userId) {
            $req = RectificationDailyRequired::findOrFail($requiredId);
            $req->update([
                'is_required' => true,  // 保留 (V0.4.3: 用 is_required 标记)
                'updated_at'  => now(),
            ]);
            return $req->fresh();
        });
    }

    /**
     * 提交整改结果 (V0.4.3 入口，由 RectificationController::complete 调用)
     */
    public function completeRectification(int $id, array $data, int $userId): \App\Models\Rectification
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $rect = \App\Models\Rectification::findOrFail($id);
            $rect->update([
                'status'          => \App\Models\Rectification::STATUS_COMPLETED,
                'completed_by'    => $userId,
                'completed_at'    => now(),
                'remark'          => $data['rectify_result'] ?? null,
                'complete_images' => $data['complete_images'] ?? null,
            ]);
            return $rect->fresh();
        });
    }
}
