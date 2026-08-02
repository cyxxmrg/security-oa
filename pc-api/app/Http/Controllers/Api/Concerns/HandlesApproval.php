<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ApprovalRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 审批中心 3 大类 Controller 共享逻辑
 * 1) list 过滤 + 分页
 * 2) 详情字段转换
 * 3) 审批流程时间线追加
 * 4) 单号生成
 */
trait HandlesApproval
{
    protected function baseQuery(Request $request, string $type)
    {
        $q = ApprovalRecord::query()->where('type', $type)->orderByDesc('id');
        $this->applyApprovalScope($q, $request);
        if ($request->filled('sub_type')) $q->where('sub_type', $request->sub_type);
        if ($request->filled('priority')) $q->where('priority', $request->priority);
        if ($request->filled('status'))   $q->where('status', $request->status);
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $q->where(function ($w) use ($kw) {
                $w->where('code', 'like', "%{$kw}%")->orWhere('title', 'like', "%{$kw}%");
            });
        }
        return $q;
    }

    protected function applyApprovalScope($query, Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }
        if ($this->canViewAllApprovals($user)) {
            return;
        }
        $query->where(function ($w) use ($user) {
            $w->where('applicant_id', $user->id)
              ->orWhere('current_approver_id', $user->id)
              ->orWhereJsonContains('cc', $user->id)
              ->orWhereJsonContains('cc', (string) $user->id);
        });
    }

    protected function canCurrentUserView(ApprovalRecord $r): bool
    {
        $user = request()->user();
        if (!$user) return false;
        if ($this->canViewAllApprovals($user)) return true;
        $cc = is_array($r->cc) ? $r->cc : [];
        return (int) $r->applicant_id === (int) $user->id
            || (int) $r->current_approver_id === (int) $user->id
            || in_array($user->id, $cc, true)
            || in_array((string) $user->id, $cc, true);
    }

    protected function canViewAllApprovals($user): bool
    {
        if (($user->is_system ?? false) === true || ($user->user_type ?? null) === 'system') {
            return true;
        }
        try {
            $roles = $user->activeRoles()->pluck('roles.name')->all();
            if (in_array('admin', $roles, true)) {
                return true;
            }
        } catch (\Throwable $e) {
            // V1.2.10 修复空捕获: 至少记录日志, 避免权限异常被静默吞掉
            \Illuminate\Support\Facades\Log::warning('HandlesApproval: activeRoles 异常, fallback 到权限检查', [
                'msg' => $e->getMessage(),
                'user_id' => $user?->id,
            ]);
        }
        try {
            return $user->hasActivePermissionTo('settings.approval')
                || $user->hasActivePermissionTo('system.role');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function perPage(Request $request): int
    {
        $pp = (int) $request->input('per_page', 20);
        return $pp > 0 && $pp <= 200 ? $pp : 20;
    }

    protected function transformPaginated(LengthAwarePaginator $rows): array
    {
        return [
            'current_page' => $rows->currentPage(),
            'data'         => collect($rows->items())->map(fn (ApprovalRecord $r) => $this->transform($r))->all(),
            'total'        => $rows->total(),
            'per_page'     => $rows->perPage(),
        ];
    }

    protected function transform(ApprovalRecord $r): array
    {
        $applicant = $r->applicant_id ? User::find($r->applicant_id) : null;
        // V1.2.14p: 关联 project (审批单里只有 project_id)
        $projectName = null;
        if (($r->payload['project_id'] ?? null) && $r->sub_type === 'material-request') {
            $project = \DB::table('projects')->where('id', $r->payload['project_id'])->select('id', 'name')->first();
            if ($project) $projectName = $project->name;
        }
        // V1.2.14p: 把 quantity 提取到顶层 (方便列表显示申领总数)
        $quantityTotal = 0;
        $itemsEnriched = []; // V1.2.14p: 物料明细带名称
        if ($r->sub_type === 'material-request' && is_array($r->payload['items'] ?? null)) {
            // 批量查 inventory_items + warehouses 一次拉完避免 N+1
            $itemIds = array_filter(array_map(fn($i) => $i['inventory_item_id'] ?? null, $r->payload['items']));
            $warehouseIds = array_filter(array_map(fn($i) => $i['warehouse_id'] ?? null, $r->payload['items']));
            $itemsMap = $itemIds ? DB::table('inventory_items')->whereIn('id', $itemIds)->select('id', 'name', 'code', 'specification', 'unit')->get()->keyBy('id') : collect();
            $warehousesMap = $warehouseIds ? DB::table('warehouses')->whereIn('id', $warehouseIds)->select('id', 'name')->get()->keyBy('id') : collect();
            foreach ($r->payload['items'] as $it) {
                $quantityTotal += (int)($it['quantity'] ?? 0);
                $ii = $it['inventory_item_id'] ?? null;
                $wi = $it['warehouse_id'] ?? null;
                $itemsEnriched[] = [
                    'inventoryItem' => $ii && $itemsMap->has($ii) ? [
                        'id' => (int)$ii,
                        'name' => $itemsMap->get($ii)->name,
                        'code' => $itemsMap->get($ii)->code,
                        'specification' => $itemsMap->get($ii)->specification,
                        'unit' => $itemsMap->get($ii)->unit,
                    ] : null,
                    'warehouse' => $wi && $warehousesMap->has($wi) ? [
                        'id' => (int)$wi,
                        'name' => $warehousesMap->get($wi)->name,
                    ] : null,
                    'quantity'   => (int)($it['quantity'] ?? 0),
                    'inventory_item_id' => $ii,
                    'warehouse_id' => $wi,
                ];
            }
        }
        return [
            'id'                  => $r->id,
            'code'                => $r->code,
            'type'                => $r->type,
            'subType'             => $r->sub_type,
            'title'               => $r->title,
            'priority'            => $r->priority,
            'status'              => $r->status,
            'amount'              => (float) $r->amount,
            'bankAccount'         => $r->bank_account,
            'startDate'           => $r->start_date?->format('Y-m-d'),
            'endDate'             => $r->end_date?->format('Y-m-d'),
            'toStage'             => $r->to_stage,
            'applicantId'         => $r->applicant_id,
            'currentApproverId'   => $r->current_approver_id,
            'initiator'           => $applicant ? ['id' => $applicant->id, 'name' => $applicant->name] : null,
            // V1.2.14p: 项目名直接返回 (避免前端二次调用)
            'projectName'         => $projectName,
            'projectId'           => $r->payload['project_id'] ?? null,
            'quantityTotal'       => $quantityTotal,
            'itemsEnriched'       => $itemsEnriched,
            'payload'             => $r->payload ?? new \stdClass(),
            'flow'                => $r->flow ?? [],
            'cc'                  => $r->cc ?? [],
            'comment'             => $r->comment,
            'created_at'          => $r->created_at?->toDateTimeString(),
            'updated_at'          => $r->updated_at?->toDateTimeString(),
        ];
    }

    protected function appendFlow(ApprovalRecord $r, string $action, string $comment, ?string $operatorName = null): void
    {
        $flow = is_array($r->flow) ? $r->flow : [];
        $flow[] = [
            'operator' => $operatorName ?? (request()->user()?->name ?? '—'),
            'action'   => $action,
            'time'     => now()->toDateTimeString(),
            'comment'  => $comment,
        ];
        // V1.2.7 P2-4 fix: 直接用 setAttribute 避免 array cast 的 overloaded property 修改问题
        $r->setAttribute('flow', $flow);
    }

    /**
     * V1.2.7k: 判断当前用户是否有权审批此单 (防止自审)
     * - system 账号可越权
     * - current_approver_id 命中自己可审
     * - 申请人自审: 业务场景下禁止, system 仍可越权
     */
    protected function canCurrentUserApprove(ApprovalRecord $r): bool
    {
        $user = request()->user();
        if (!$user) return false;
        if ($r->status !== ApprovalRecord::STATUS_PENDING) return false;
        // 系统账号越权
        if (($user->is_system ?? false) === true || ($user->user_type ?? null) === 'system') {
            return true;
        }
        // 当前审批人命中
        if ((int) $r->current_approver_id === (int) $user->id) {
            return true;
        }
        return false;
    }

    protected function nextCode(string $prefix): string
    {
        $year = now()->format('Y');
        $count = ApprovalRecord::where('code', 'like', "{$prefix}-{$year}-%")->count() + 1;
        return sprintf('%s-%s-%04d', $prefix, $year, $count);
    }
}
