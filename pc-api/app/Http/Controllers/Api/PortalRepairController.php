<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V0.5.7 块3 — 客户端查询入口 (无需登录, 双因子验证)
 *
 * 流程:
 *   客户在公开页面输入工单号 + 联系电话后 4 位 → 后端校验
 *   校验通过 → 返回脱敏后的进度/物流/状态信息
 *   校验失败 → 模糊提示"未找到记录", 不暴露具体信息
 *
 * 路由: /api/portal/repair  (无需 auth + 无 permission 中间件)
 */
class PortalRepairController extends Controller
{
    /**
     * GET /api/portal/repair?code=RN2026-001&phone_suffix=1234
     * 公开查询 (无登录, 限制频次)
     */
    public function query(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'         => 'required|string|max:32',
            'phone_suffix' => 'required|string|size:4|regex:/^[0-9]+$/',
        ]);

        $code = trim($data['code']);
        $suffix = $data['phone_suffix'];

        // V1.4.3 (REVIEW P1): 失败锁定 — 同 IP 连续失败 5 次锁 15 分钟 (防工单号+尾号爆破)
        $ipKey = $request->ip() ?: 'unknown';
        $lockKey = "portal:repair:lock:{$ipKey}";
        if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
            return response()->json([
                'code' => 429,
                'message' => '查询尝试过多, 请 15 分钟后再试',
            ], 429);
        }

        $ro = RepairOrder::with([
            'customer:id,name',
            'project:id,name',
            'shipments',
            'methods',
            'progressLogs.actor:id,username,name',
        ])->where('code', $code)->first();

        if (!$ro) {
            // V1.4.3: 单号不存在也计失败 (防单号枚举)
            if ($block = $this->bumpFailure($ipKey)) {
                return $block;
            }
            return response()->json([
                'code' => 404,
                'message' => '未找到该工单, 请检查单号是否正确',
            ], 404);
        }

        // 双因子验证: 客户电话后 4 位
        $stored = $ro->contact_phone ?: '';
        $storedDigits = preg_replace('/[^0-9]/', '', $stored);
        $storedSuffix = substr($storedDigits, -4);

        if ($storedSuffix !== $suffix) {
            // V1.4.3: 失败计数 + 达阈值锁定 (统一 helper)
            if ($block = $this->bumpFailure($ipKey)) {
                return $block;
            }
            // 模糊提示, 不暴露是单号错还是电话错
            return response()->json([
                'code' => 403,
                'message' => '验证失败, 请检查工单号和电话后 4 位',
            ], 403);
        }

        // 成功 → 清失败计数
        \Illuminate\Support\Facades\Cache::forget("portal:repair:fail:{$ipKey}");

        // 脱敏返回 — 只展示客户可见的内容
        return response()->json([
            'code' => 0,
            'data' => $this->presentPublic($ro),
        ]);
    }

    /** V1.4.3 (REVIEW P1): 失败锁定 — 同 IP 连续失败 5 次锁 15 分钟 */
    private function bumpFailure(string $ipKey): ?JsonResponse
    {
        $failKey = "portal:repair:fail:{$ipKey}";
        $fails = \Illuminate\Support\Facades\Cache::add($failKey, 1, 300)
            ? 1
            : (int) \Illuminate\Support\Facades\Cache::increment($failKey);
        if ($fails >= 5) {
            \Illuminate\Support\Facades\Cache::put("portal:repair:lock:{$ipKey}", 1, 900);
            \Illuminate\Support\Facades\Cache::forget($failKey);
            return response()->json([
                'code' => 429,
                'message' => '查询尝试过多, 请 15 分钟后再试',
            ], 429);
        }
        return null;
    }

    private function presentPublic(RepairOrder $ro): array
    {
        $status = is_object($ro->status) ? $ro->status->value : $ro->status;
        $methodType = $ro->method_type;

        // 物流: 客户看脱敏 (不显示内部备注/费用)
        $shipments = $ro->shipments->map(fn ($s) => [
            'direction'       => $s->direction, // outbound / inbound
            'direction_label' => $s->direction === 'outbound' ? '寄出' : '寄回',
            'carrier'         => $s->carrier,
            'tracking_no'     => $s->tracking_no,
            'shipped_at'      => $s->shipped_at?->toDateTimeString(),
            'estimated_arrival' => $s->estimated_arrival?->toDateTimeString(),
            'actual_arrival'  => $s->actual_arrival?->toDateTimeString(),
        ])->values();

        // 维修进度: 公开只显示 status 转换, 不显示内部人员
        $progress = $ro->progressLogs->map(fn ($p) => [
            'status'        => $p->status_after,
            'status_label'  => $this->statusLabel($p->status_after),
            'description'   => $p->description,
            'action_at'     => $p->action_at?->toDateTimeString(),
        ])->values();

        return [
            'code'              => $ro->code,
            'equipment_brand'   => $ro->equipment_brand,
            'equipment_model'   => $ro->equipment_model,
            'status'            => $status,
            'status_label'      => $this->statusLabel($status),
            'fault_description' => $ro->fault_description,
            'received_at'       => $ro->received_at?->toDateTimeString(),
            'expected_finish_at'=> $ro->expected_finish_at?->toDateTimeString(),
            'method_type'       => $methodType,
            'method_label'      => $methodType ? $this->methodLabel(is_string($methodType) ? $methodType : $methodType->value) : null,
            'is_warranty'       => (bool) $ro->is_warranty,
            'is_paid'           => in_array($methodType, ['paid_repair', 'paid_replace']),
            // 物流 + 进度 (脱敏)
            'shipments'         => $shipments,
            'progress'          => $progress,
            'progress_count'    => $progress->count(),
            'created_at'        => $ro->created_at?->toDateTimeString(),
            // 客户姓名脱敏
            'customer_name'     => $ro->customer ? mb_substr($ro->customer->name, 0, 1) . '**' : null,
            'project_name'      => $ro->project?->name,
        ];
    }

    private function statusLabel(string $s): string
    {
        return match ($s) {
            'received'        => '已接件',
            'sent_for_repair' => '寄修中',
            'in_repair'       => '维修中',
            'repaired'        => '已修好',
            'sent_back'       => '寄回中',
            'closed'          => '已关闭',
            'cancelled'       => '已取消',
            default           => $s,
        };
    }

    private function methodLabel(string $m): string
    {
        return match ($m) {
            'free_warranty' => '🆓 免费（保内）',
            'free_contract' => '🆓 免费（合同）',
            'paid_repair'   => '💰 付费维修',
            'paid_replace'  => '💰 付费换新',
            'returned'      => '↩️ 退回不修',
            default        => $m,
        };
    }
}
