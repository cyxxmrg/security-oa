<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ClearsListCache;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * V0.5.5 维修工单 API
 *
 * GET    /api/work-orders                  列表 (含过滤)
 * POST   /api/work-orders                  新建
 * GET    /api/work-orders/{id}             详情
 * PUT    /api/work-orders/{id}             修改
 * DELETE /api/work-orders/{id}             删除 (仅 pending/cancelled)
 * POST   /api/work-orders/{id}/assign      派单
 * POST   /api/work-orders/{id}/start       开始
 * POST   /api/work-orders/{id}/resolve     完成
 * POST   /api/work-orders/{id}/cancel      取消
 * POST   /api/work-orders/{id}/convert-to-repair  转返修 (V0.5.5 关键)
 * GET    /api/work-orders/stats            看板统计
 */
class WorkOrderController extends Controller
{
    use ClearsListCache;

    public function __construct(private readonly WorkOrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        // V1.3.1: 缓存响应 JSON, 避免 Eloquent 序列化开销
        $cacheKey = 'work_orders:index:' . ($request->user()?->id ?? 0) . ':' . md5(serialize($request->all()));
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(json_decode($cached, true));
        }

        $perPage = (int) ($request->per_page ?? 20);
        $perPage = max(1, min($perPage, 200));

        $q = WorkOrder::with(['customer:id,name', 'project:id,name', 'assignee:id,username,name'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $q->where('priority', $request->priority);
        }
        if ($request->filled('assigned_to')) {
            $q->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('customer_id')) {
            $q->where('customer_id', $request->customer_id);
        }
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $q->where(function ($x) use ($kw) {
                $x->where('code', 'ilike', "%{$kw}%")
                  ->orWhere('fault_description', 'ilike', "%{$kw}%")
                  ->orWhere('contact_name', 'ilike', "%{$kw}%")
                  ->orWhere('contact_phone', 'ilike', "%{$kw}%");
            });
        }
        if ($request->filled('days')) {
            $days = max(1, min((int) $request->days, 365));
            $q->where('created_at', '>=', now()->subDays($days));
        }

        $list = $q->paginate($perPage);
        $list->getCollection()->transform(fn ($wo) => $this->present($wo));

        $json = json_encode(['code' => 0, 'data' => $list->toArray()]);
        Cache::put($cacheKey, $json, 30);

        return response()->json(json_decode($json, true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'        => 'nullable|integer|exists:customers,id',
            'project_id'         => 'nullable|integer|exists:projects,id',
            'equipment_id'       => 'nullable|integer',
            'contact_name'       => 'required_without:customer_id|string|max:64',
            'contact_phone'      => 'nullable|string|max:32',
            'address'            => 'nullable|string|max:255',
            'service_type'       => 'nullable|string|max:16',
            'priority'           => 'nullable|string|in:low,medium,high,urgent',
            'fault_description'  => 'required|string|max:2000',
            'equipment_brand'    => 'nullable|string|max:64',
            'equipment_model'    => 'nullable|string|max:64',
            'serial_no'          => 'nullable|string|max:64',
            'scheduled_at'       => 'nullable|date',
            'is_billable'        => 'boolean',
            'charge_type'        => 'nullable|string|in:warranty_free,contract_free,paid',
            'contract_id'        => 'nullable|integer|exists:project_contracts,id',
            'min_charge'         => 'nullable|integer|in:120,300,500,800',
            'remarks'            => 'nullable|string|max:1000',
        ]);

        $data['code'] = $this->nextCode();
        $data['status'] = WorkOrderStatus::PENDING;
        $data['created_by'] = $request->user()?->id;
        $data['priority'] = $data['priority'] ?? 'medium';
        $data['is_billable'] = $data['is_billable'] ?? true;
        $data['charge_type'] = $data['charge_type'] ?? 'paid';

        // V0.5.7 块1 — 项目阶段校验: 只有结算/质保阶段才能创建售后工单
        if (!empty($data['project_id'])) {
            $project = \App\Models\Project::find($data['project_id']);
            if ($project) {
                $stage = is_object($project->stage) ? $project->stage->value : $project->stage;
                if (!in_array($stage, ['settlement', 'warranty'], true)) {
        $this->clearListCache('work_orders:index');
                    return response()->json([
                        'code' => 422,
                        'message' => "项目 #{$project->id} 当前阶段为「{$stage}」, 需进入「结算」或「质保」阶段后才能创建售后工单",
                        'data' => ['project_id' => $project->id, 'current_stage' => $stage],
                    ], 422);
                }
            }
        }

        $wo = WorkOrder::create($data);

        Audit::write('work_order_created', "工单 {$wo->code} 创建", [
            'work_order_id' => $wo->id,
            'priority'      => $wo->priority->value,
        ]);

        return response()->json(['code' => 0, 'data' => $this->present($wo), 'message' => '已创建']);
    }

    public function show(int $id): JsonResponse
    {
        // v0.5.8 修复: customers 表无 phone 列 (phone 在 customer_contacts 关联表), 移除避免 42703
        $wo = WorkOrder::with(['customer:id,name', 'project:id,name', 'assignee:id,username,name', 'creator:id,username,name', 'repairOrder:id,code,status'])->findOrFail($id);
        return response()->json(['code' => 0, 'data' => $this->present($wo, withRelations: true)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $wo = WorkOrder::findOrFail($id);
        if (!$wo->isEditable()) {
        $this->clearListCache('work_orders:index');
            return response()->json(['code' => 422, 'message' => "工单 {$wo->code} 已锁定, 不可编辑"], 422);
        }

        $data = $request->validate([
            'contact_name'       => 'sometimes|string|max:64',
            'contact_phone'      => 'nullable|string|max:32',
            'address'            => 'nullable|string|max:255',
            'priority'           => 'sometimes|in:low,medium,high,urgent',
            'fault_description'  => 'sometimes|string|max:2000',
            'equipment_brand'    => 'nullable|string|max:64',
            'equipment_model'    => 'nullable|string|max:64',
            'serial_no'          => 'nullable|string|max:64',
            'scheduled_at'       => 'nullable|date',
        ]);

        $wo->fill($data);
        $wo->save();

        return response()->json(['code' => 0, 'data' => $this->present($wo), 'message' => '已更新']);
    }

    public function destroy(int $id): JsonResponse
    {
        $wo = WorkOrder::findOrFail($id);
        if (!in_array($wo->status, [WorkOrderStatus::PENDING, WorkOrderStatus::CANCELLED], true)) {
        $this->clearListCache('work_orders:index');
            return response()->json(['code' => 422, 'message' => "工单 {$wo->code} 状态 {$wo->status->value} 不可删除"], 422);
        }
        $wo->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'engineer_id' => 'required|integer|exists:users,id',
            'note'        => 'nullable|string|max:200',
        ]);
        $wo = WorkOrder::findOrFail($id);
        $wo = $this->service->assign($wo, $data['engineer_id'], $data['note'] ?? null);
        return response()->json(['code' => 0, 'data' => $this->present($wo), 'message' => '已派单']);
    }

    public function start(int $id): JsonResponse
    {
        $wo = WorkOrder::findOrFail($id);
        $wo = $this->service->start($wo);
        return response()->json(['code' => 0, 'data' => $this->present($wo), 'message' => '已开始']);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'result_notes'       => 'required|string|max:2000',
            'service_fee'        => 'nullable|numeric|min:0',
            'parts_cost'         => 'nullable|numeric|min:0',
            'customer_signature' => 'nullable|string|max:1048576',
            // V1.2.13: 收款方式
            'payment_method'     => 'nullable|string|in:cash,receivable',
        ]);
        $wo = WorkOrder::findOrFail($id);
        if ($wo->service_type === 'on_site' && empty($data['customer_signature'])) {
            return response()->json(['code' => 422, 'message' => '上门服务工单完成时必须提供客户签字'], 422);
        }

        // 现场处理完毕：结算
        $serviceFee = (float) ($data['service_fee'] ?? 0);
        $partsCost  = (float) ($data['parts_cost'] ?? 0);
        $total      = $serviceFee + $partsCost;

        $wo = $this->service->resolve(
            $wo, $data['result_notes'], $serviceFee, $partsCost,
            $data['customer_signature'] ?? null
        );

        // V1.2.13: 应收款挂账
        if (($data['payment_method'] ?? 'cash') === 'receivable' && $total > 0 && $wo->customer_id) {
            \App\Models\CustomerReceivable::create([
                'customer_id'     => $wo->customer_id,
                'project_id'      => $wo->project_id,
                'source_type'     => 'work_order',
                'source_id'       => $wo->id,
                'ref_no'          => $wo->code,
                'receivable_type' => \App\Models\CustomerReceivable::TYPE_WARRANTY,
                'amount'          => $total,
                'received_amount' => 0,
                'status'          => \App\Models\CustomerReceivable::STATUS_PENDING,
                'note'            => '工单 ' . $wo->code . ' 服务费',
                'created_by'      => $request->user()?->id,
            ]);
        }

        return response()->json(['code' => 0, 'data' => $this->present($wo), 'message' => '已解决']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $wo = WorkOrder::findOrFail($id);
        $wo = $this->service->cancel($wo, $data['reason']);
        return response()->json(['code' => 0, 'data' => $this->present($wo), 'message' => '已取消']);
    }

    /**
     * V0.5.5 关键端点: 工单转返修 (事务 + 双向 audit)
     */
    public function convertToRepair(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason'              => 'required|string|max:500',
            'expected_finish_at'  => 'nullable|date',
            'method_type'         => 'nullable|string|in:free_warranty,free_contract,paid_repair,paid_replace,returned',
            'remarks'             => 'nullable|string|max:1000',
            // V1.2.13: 转返修时可更新设备信息
            'equipment_model'     => 'nullable|string|max:64',
            'serial_no'           => 'nullable|string|max:64',
        ]);

        $wo = WorkOrder::findOrFail($id);
        if ($wo->status !== WorkOrderStatus::IN_PROGRESS) {
            return response()->json([
                'code' => 422,
                'message' => "只有「进行中」的工单才能转为返修, 当前: {$wo->status->value}"
            ], 422);
        }
        if ($wo->is_locked) {
            return response()->json(['code' => 422, 'message' => "工单 {$wo->code} 已锁定"], 422);
        }

        return DB::transaction(function () use ($wo, $data, $request) {
            // 1) 工单状态变更
            $wo->status = WorkOrderStatus::CONVERTED_TO_REPAIR;
            $wo->result_notes = "[转返修] " . $data['reason'];
            $wo->completed_at = now();
            $wo->save();
            $wo->lock();

            // 2) 创建返修单 (字段复制)
            $repair = \App\Models\RepairOrder::create([
                'code'                => $this->nextRepairCode(),
                'source_type'         => 'work_order',
                'source_id'           => $wo->id,
                'source_code'         => $wo->code,
                'customer_id'         => $wo->customer_id,
                'project_id'          => $wo->project_id,
                'equipment_id'        => $wo->equipment_id,
                'contact_name'        => $wo->contact_name,
                'contact_phone'       => $wo->contact_phone,
                'address'             => $wo->address,
                'equipment_brand'     => $wo->equipment_brand,
                'equipment_model'     => $data['equipment_model'] ?? $wo->equipment_model,
                'serial_no'           => $data['serial_no'] ?? $wo->serial_no,
                'fault_description'   => $wo->fault_description,
                'severity'            => $wo->priority->toSeverity(),
                'received_by'         => $request->user()?->id ?? $wo->assigned_to,
                'received_at'         => now(),
                'expected_finish_at'  => $data['expected_finish_at'] ?? null,
                'status'              => \App\Enums\RepairOrderStatus::RECEIVED,
                'method_type'         => $data['method_type'] ?? null,
                'is_warranty'         => $wo->priority === WorkOrderPriority::URGENT ? false : false,
                'remarks'             => $data['remarks'] ?? null,
                'created_by'          => $request->user()?->id,
            ]);

            // 3) 双向关联
            $wo->converted_repair_id = $repair->id;
            $wo->save();

            // 4) 写 audit (双向)
            \App\Support\Audit::write('work_order_converted_to_repair', sprintf(
                '工单 %s 转为返修单 %s: %s',
                $wo->code, $repair->code, $data['reason']
            ), [
                'work_order_id' => $wo->id,
                'repair_id'     => $repair->id,
                'reason'        => $data['reason'],
            ]);
            \App\Support\Audit::write('repair_order_created_from_work_order', sprintf(
                '返修单 %s 由工单 %s 转换而来',
                $repair->code, $wo->code
            ), [
                'work_order_id' => $wo->id,
                'repair_id'     => $repair->id,
            ]);

            // V0.5.7 块1 — 同步到项目 timeline (如果工单有关联项目)
            if ($wo->project_id) {
                try {
                    \App\Models\ConstructionLog::create([
                        'project_id'    => $wo->project_id,
                        'log_date'      => now()->toDateString(),
                        'log_type'      => 'maintenance',
                        'title'         => sprintf('工单 %s 转返修 %s', $wo->code, $repair->code),
                        'content'       => "原因: {$data['reason']}",
                        'weather'       => null,
                        'temperature'   => null,
                        'logged_by'     => $request->user()?->id,
                        'logged_at'     => now(),
                    ]);
                } catch (\Throwable $e) {
                    \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
                    // ConstructionLog 表 schema 可能有差异, 静默失败不阻塞主流程
                }
            }

            return response()->json([
                'code' => 0,
                'data' => [
                    'work_order'   => $this->present($wo->fresh()),
                    'repair_order' => $repair,
                ],
                'message' => "已转为返修单 {$repair->code}",
            ]);
        });
    }

    /**
     * 看板统计
     */
    public function stats(Request $request): JsonResponse
    {
        $days = (int) ($request->days ?? 30);
        $days = max(1, min($days, 365));

        $byStatus = WorkOrder::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $byPriority = WorkOrder::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->all();

        $total = WorkOrder::where('created_at', '>=', now()->subDays($days))->count();
        $converted = WorkOrder::where('status', WorkOrderStatus::CONVERTED_TO_REPAIR)
            ->where('created_at', '>=', now()->subDays($days))->count();

        return response()->json([
            'code' => 0,
            'data' => [
                'days'              => $days,
                'total'             => $total,
                'converted_to_repair' => $converted,
                'conversion_rate'   => $total > 0 ? round($converted / $total * 100, 1) : 0,
                'by_status'         => [
                    'pending'             => (int) ($byStatus['pending'] ?? 0),
                    'assigned'            => (int) ($byStatus['assigned'] ?? 0),
                    'in_progress'         => (int) ($byStatus['in_progress'] ?? 0),
                    'resolved'            => (int) ($byStatus['resolved'] ?? 0),
                    'cancelled'           => (int) ($byStatus['cancelled'] ?? 0),
                    'converted_to_repair' => (int) ($byStatus['converted_to_repair'] ?? 0),
                ],
                'by_priority' => [
                    'low'    => (int) ($byPriority['low'] ?? 0),
                    'medium' => (int) ($byPriority['medium'] ?? 0),
                    'high'   => (int) ($byPriority['high'] ?? 0),
                    'urgent' => (int) ($byPriority['urgent'] ?? 0),
                ],
            ],
        ]);
    }

    // =============== 私有 ===============

    private function present(WorkOrder $wo, bool $withRelations = false): array
    {
        $arr = [
            'id'                  => $wo->id,
            'code'                => $wo->code,
            'customer_id'         => $wo->customer_id,
            'customer_name'       => $wo->customer?->name,
            'project_id'          => $wo->project_id,
            'project_name'        => $wo->project?->name,
            'contact_name'        => $wo->contact_name,
            'contact_phone'       => $wo->contact_phone,
            'address'             => $wo->address,
            'service_type'        => $wo->service_type,
            'priority'            => $wo->priority->value,
            'priority_label'      => $wo->priority->label(),
            'priority_color'      => $wo->priority->color(),
            'fault_description'   => $wo->fault_description,
            'equipment_brand'     => $wo->equipment_brand,
            'equipment_model'     => $wo->equipment_model,
            'serial_no'           => $wo->serial_no,
            'assigned_to'         => $wo->assigned_to,
            'assignee_name'       => $wo->assignee?->name,
            'scheduled_at'        => $wo->scheduled_at?->toDateTimeString(),
            'started_at'          => $wo->started_at?->toDateTimeString(),
            'completed_at'        => $wo->completed_at?->toDateTimeString(),
            'status'              => $wo->status->value,
            'status_label'        => $wo->status->label(),
            'status_color'        => $wo->status->color(),
            'is_billable'         => $wo->is_billable,
            'charge_type'         => $wo->charge_type ?? 'paid',
            'charge_type_label'   => ['warranty_free'=>'保内免费','contract_free'=>'合同内免费','paid'=>'收费'][$wo->charge_type ?? 'paid'] ?? '收费',
            'contract_id'         => $wo->contract_id,
            'contract_name'       => $wo->contract?->name,
            'min_charge'          => $wo->min_charge,
            'service_fee'         => (float) $wo->service_fee,
            'parts_cost'          => (float) $wo->parts_cost,
            'total_cost'          => (float) $wo->total_cost,
            'result_notes'        => $wo->result_notes,
            'converted_repair_id' => $wo->converted_repair_id,
            'is_locked'           => $wo->is_locked,
            'created_at'          => $wo->created_at?->toDateTimeString(),
        ];
        if ($withRelations && $wo->relationLoaded('repairOrder') && $wo->repairOrder) {
            $arr['repair_order'] = [
                'id'     => $wo->repairOrder->id,
                'code'   => $wo->repairOrder->code,
                'status' => $wo->repairOrder->status,
            ];
        }
        return $arr;
    }

    private function nextCode(): string
    {
        $year = now()->format('Y');
        // V0.5.5 修: 用 MAX(code) + 1 而非依赖 sequence, 避免 race condition
        $lastSeq = (int) WorkOrder::where('code', 'like', "WO{$year}-%")
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(code FROM 'WO[0-9]{4}-([0-9]+)') AS INTEGER)), 0) as seq")
            ->value('seq');
        return sprintf('WO%s-%03d', $year, $lastSeq + 1);
    }

    private function nextRepairCode(): string
    {
        $year = now()->format('Y');
        $lastSeq = (int) \App\Models\RepairOrder::where('code', 'like', "RN{$year}-%")
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(code FROM 'RN[0-9]{4}-([0-9]+)') AS INTEGER)), 0) as seq")
            ->value('seq');
        return sprintf('RN%s-%03d', $year, $lastSeq + 1);
    }
}
