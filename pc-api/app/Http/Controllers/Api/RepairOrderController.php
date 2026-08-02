<?php

namespace App\Http\Controllers\Api;

use App\Enums\RepairOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\RepairAttachment;
use App\Models\RepairMethod;
use App\Models\RepairOrder;
use App\Models\RepairProgressLog;
use App\Models\RepairShipment;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * V0.5.5 返修单 API (主单)
 *
 * GET    /api/repair-orders                列表
 * POST   /api/repair-orders                新建
 * GET    /api/repair-orders/{id}           详情
 * PUT    /api/repair-orders/{id}           修改
 * DELETE /api/repair-orders/{id}           删除
 * POST   /api/repair-orders/{id}/cancel    取消
 * POST   /api/repair-orders/{id}/ship-out  寄出 (自动创建 outbound shipment)
 * POST   /api/repair-orders/{id}/ship-back 寄回 (自动创建 inbound shipment)
 * POST   /api/repair-orders/{id}/in-repair 标记维修中
 * POST   /api/repair-orders/{id}/repaired  修好
 * POST   /api/repair-orders/{id}/close     关闭
 * GET    /api/repair-orders/stats          看板统计
 */
class RepairOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->per_page ?? 20);
        $perPage = max(1, min($perPage, 200));

        $q = RepairOrder::with([
            'customer:id,name',
            'project:id,name',
            'receiver:id,username,name',
            'creator:id,username,name',
        ])->orderByDesc('id');

        if ($request->filled('status'))     $q->where('status', $request->status);
        if ($request->filled('method_type'))$q->where('method_type', $request->method_type);
        if ($request->filled('source_type'))$q->where('source_type', $request->source_type);
        if ($request->filled('customer_id'))$q->where('customer_id', $request->customer_id);
        if ($request->filled('project_id')) $q->where('project_id', $request->project_id);
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $q->where(function ($x) use ($kw) {
                $x->where('code', 'ilike', "%{$kw}%")
                  ->orWhere('source_code', 'ilike', "%{$kw}%")
                  ->orWhere('fault_description', 'ilike', "%{$kw}%")
                  ->orWhere('contact_name', 'ilike', "%{$kw}%")
                  ->orWhere('equipment_brand', 'ilike', "%{$kw}%")
                  ->orWhere('equipment_model', 'ilike', "%{$kw}%")
                  ->orWhere('serial_no', 'ilike', "%{$kw}%");
            });
        }
        if ($request->filled('days')) {
            $days = max(1, min((int) $request->days, 365));
            $q->where('created_at', '>=', now()->subDays($days));
        }

        $list = $q->paginate($perPage);
        $list->getCollection()->transform(fn ($r) => $this->present($r));
        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_type'         => 'nullable|in:customer,work_order,internal',
            'source_id'           => 'nullable|integer',
            'source_code'         => 'nullable|string|max:32',
            'customer_id'         => 'nullable|integer|exists:customers,id',
            'project_id'          => 'nullable|integer|exists:projects,id',
            'contact_name'        => 'required_without:customer_id|string|max:64',
            'contact_phone'       => 'nullable|string|max:32',
            'address'             => 'nullable|string|max:255',
            'equipment_brand'     => 'nullable|string|max:64',
            'equipment_model'     => 'nullable|string|max:64',
            'serial_no'           => 'nullable|string|max:64',
            'fault_type'          => 'nullable|string|max:32',
            'fault_description'   => 'required|string|max:2000',
            'severity'            => 'nullable|in:low,medium,high',
            'expected_finish_at'  => 'nullable|date',
            'method_type'         => 'nullable|in:free_warranty,free_contract,paid_repair,paid_replace,returned',
            'is_warranty'         => 'boolean',
            'warranty_until'      => 'nullable|date',
            'remarks'             => 'nullable|string|max:1000',
        ]);

        $data['code'] = $this->nextCode();
        $data['status'] = RepairOrderStatus::RECEIVED;
        $data['received_by'] = $request->user()?->id;
        $data['received_at'] = $data['received_at'] ?? now();
        $data['created_by'] = $request->user()?->id;
        $data['source_type'] = $data['source_type'] ?? 'customer';
        $data['severity'] = $data['severity'] ?? 'medium';
        $data['is_warranty'] = $data['is_warranty'] ?? false;

        // V0.5.7 块1 — 返修单也校验项目阶段
        if (!empty($data['project_id'])) {
            $project = \App\Models\Project::find($data['project_id']);
            if ($project) {
                $stage = is_object($project->stage) ? $project->stage->value : $project->stage;
                if (!in_array($stage, ['settlement', 'warranty'], true)) {
                    return response()->json([
                        'code' => 422,
                        'message' => "项目 #{$project->id} 当前阶段为「{$stage}」, 需进入「结算」或「质保」阶段后才能创建返修单",
                    ], 422);
                }
            }
        }

        $ro = RepairOrder::create($data);

        Audit::write('repair_order_created', "返修单 {$ro->code} 接件", [
            'repair_id'   => $ro->id,
            'source_type' => $ro->source_type->value,
        ]);

        return response()->json(['code' => 0, 'data' => $this->present($ro), 'message' => '已接件']);
    }

    public function show(int $id): JsonResponse
    {
        $ro = RepairOrder::with([
            'customer:id,name',
            'project:id,name',
            'receiver:id,username,name',
            'creator:id,username,name',
            'shipments',
            'methods',
            'progressLogs.actor:id,username,name',
            'attachments',
        ])->findOrFail($id);

        return response()->json(['code' => 0, 'data' => $this->present($ro, withRelations: true)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ro = RepairOrder::findOrFail($id);
        if ($ro->status->isTerminal()) {
            return response()->json(['code' => 422, 'message' => "返修单 {$ro->code} 已终态, 不可修改"], 422);
        }
        $data = $request->validate([
            'contact_name'        => 'sometimes|string|max:64',
            'contact_phone'       => 'nullable|string|max:32',
            'address'             => 'nullable|string|max:255',
            'equipment_brand'     => 'nullable|string|max:64',
            'equipment_model'     => 'nullable|string|max:64',
            'serial_no'           => 'nullable|string|max:64',
            'fault_description'   => 'sometimes|string|max:2000',
            'severity'            => 'sometimes|in:low,medium,high',
            'expected_finish_at'  => 'nullable|date',
            'method_type'         => 'nullable|in:free_warranty,free_contract,paid_repair,paid_replace,returned',
            'is_warranty'         => 'boolean',
            'warranty_until'      => 'nullable|date',
            'remarks'             => 'nullable|string|max:1000',
        ]);
        $ro->fill($data);
        $ro->save();
        return response()->json(['code' => 0, 'data' => $this->present($ro), 'message' => '已更新']);
    }

    public function destroy(int $id): JsonResponse
    {
        $ro = RepairOrder::findOrFail($id);
        if (!in_array($ro->status, [RepairOrderStatus::RECEIVED, RepairOrderStatus::CANCELLED], true)) {
            return response()->json(['code' => 422, 'message' => "返修单 {$ro->code} 状态 {$ro->status->value} 不可删除"], 422);
        }
        $ro->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    // ============== 状态机端点 (V0.5.5) ==============

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $ro = RepairOrder::findOrFail($id);
        $this->ensureTransition($ro, RepairOrderStatus::CANCELLED);
        return DB::transaction(function () use ($ro, $data, $request) {
            $ro->status = RepairOrderStatus::CANCELLED;
            $ro->remarks = ($ro->remarks ?? '') . "\n[取消] " . $data['reason'];
            $ro->save();
            Audit::write('repair_order_cancelled', "返修单 {$ro->code} 取消: {$data['reason']}", [
                'repair_id' => $ro->id,
                'reason'    => $data['reason'],
            ]);
            return response()->json(['code' => 0, 'data' => $this->present($ro->fresh()), 'message' => '已取消']);
        });
    }

    /**
     * 寄出 (去程) — 自动创建 outbound shipment
     * body: { carrier, tracking_no, cost?, receiver_name/phone/address, estimated_arrival? }
     */
    public function shipOut(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'carrier'            => 'required|string|max:32',
            'tracking_no'        => 'required|string|max:64',
            'cost'               => 'nullable|numeric|min:0',
            'estimated_arrival'  => 'nullable|date',
            'sender_name'        => 'required|string|max:64',
            'sender_phone'       => 'nullable|string|max:32',
            'sender_address'     => 'nullable|string|max:255',
            'receiver_name'      => 'required|string|max:64',
            'receiver_phone'     => 'nullable|string|max:32',
            'receiver_address'   => 'required|string|max:255',
            'remarks'            => 'nullable|string|max:500',
        ]);

        $ro = RepairOrder::findOrFail($id);
        $this->ensureTransition($ro, RepairOrderStatus::SENT_FOR_REPAIR);
        // 同一返修单不能有 2 条 outbound
        if (RepairShipment::where('repair_order_id', $ro->id)->where('direction', 'outbound')->exists()) {
            return response()->json(['code' => 422, 'message' => '已有去程物流, 请勿重复创建'], 422);
        }

        return DB::transaction(function () use ($ro, $data, $request) {
            $ship = RepairShipment::create([
                'repair_order_id'  => $ro->id,
                'direction'        => 'outbound',
                'carrier'          => $data['carrier'],
                'tracking_no'      => $data['tracking_no'],
                'cost'             => $data['cost'] ?? 0,
                'shipped_at'       => now(),
                'estimated_arrival'=> $data['estimated_arrival'] ?? null,
                'delivery_status'  => 'in_transit',
                'sender_name'      => $data['sender_name'],
                'sender_phone'     => $data['sender_phone'] ?? null,
                'sender_address'   => $data['sender_address'] ?? null,
                'receiver_name'    => $data['receiver_name'],
                'receiver_phone'   => $data['receiver_phone'] ?? null,
                'receiver_address' => $data['receiver_address'],
                'remarks'          => $data['remarks'] ?? null,
                'created_by'       => $request->user()?->id,
            ]);

            $ro->shipping_cost += (float) ($data['cost'] ?? 0);
            $ro->status = RepairOrderStatus::SENT_FOR_REPAIR;
            $ro->save();

            Audit::write('repair_shipped_out', sprintf(
                '返修 %s 寄出: %s %s',
                $ro->code, $data['carrier'], $data['tracking_no']
            ), [
                'repair_id'    => $ro->id,
                'shipment_id'  => $ship->id,
                'tracking_no'  => $data['tracking_no'],
            ]);

            return response()->json([
                'code' => 0,
                'data' => $this->present($ro->fresh(['shipments']), withRelations: true),
                'message' => '已寄出',
            ]);
        });
    }

    /**
     * 寄回 (回程) — 自动创建 inbound shipment
     */
    public function shipBack(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'carrier'            => 'required|string|max:32',
            'tracking_no'        => 'required|string|max:64',
            'cost'               => 'nullable|numeric|min:0',
            'estimated_arrival'  => 'nullable|date',
            'sender_name'        => 'required|string|max:64',
            'sender_phone'       => 'nullable|string|max:32',
            'sender_address'     => 'nullable|string|max:255',
            'receiver_name'      => 'required|string|max:64',
            'receiver_phone'     => 'nullable|string|max:32',
            'receiver_address'   => 'required|string|max:255',
            'remarks'            => 'nullable|string|max:500',
        ]);

        $ro = RepairOrder::findOrFail($id);
        $this->ensureTransition($ro, RepairOrderStatus::SENT_BACK);
        if (RepairShipment::where('repair_order_id', $ro->id)->where('direction', 'inbound')->exists()) {
            return response()->json(['code' => 422, 'message' => '已有回程物流'], 422);
        }

        return DB::transaction(function () use ($ro, $data, $request) {
            $ship = RepairShipment::create([
                'repair_order_id'  => $ro->id,
                'direction'        => 'inbound',
                'carrier'          => $data['carrier'],
                'tracking_no'      => $data['tracking_no'],
                'cost'             => $data['cost'] ?? 0,
                'shipped_at'       => now(),
                'estimated_arrival'=> $data['estimated_arrival'] ?? null,
                'delivery_status'  => 'in_transit',
                'sender_name'      => $data['sender_name'],
                'sender_phone'     => $data['sender_phone'] ?? null,
                'sender_address'   => $data['sender_address'] ?? null,
                'receiver_name'    => $data['receiver_name'],
                'receiver_phone'   => $data['receiver_phone'] ?? null,
                'receiver_address' => $data['receiver_address'],
                'remarks'          => $data['remarks'] ?? null,
                'created_by'       => $request->user()?->id,
            ]);

            $ro->shipping_cost += (float) ($data['cost'] ?? 0);
            $ro->status = RepairOrderStatus::SENT_BACK;
            $ro->save();

            Audit::write('repair_shipped_back', sprintf(
                '返修 %s 寄回: %s %s',
                $ro->code, $data['carrier'], $data['tracking_no']
            ), [
                'repair_id'   => $ro->id,
                'shipment_id' => $ship->id,
            ]);

            return response()->json([
                'code' => 0,
                'data' => $this->present($ro->fresh(['shipments']), withRelations: true),
                'message' => '已寄回',
            ]);
        });
    }

    /**
     * 标记维修中
     */
    public function markInRepair(Request $request, int $id): JsonResponse
    {
        $ro = RepairOrder::findOrFail($id);
        $this->ensureTransition($ro, RepairOrderStatus::IN_REPAIR);
        $ro->status = RepairOrderStatus::IN_REPAIR;
        $ro->save();
        Audit::write('repair_in_repair', "返修 {$ro->code} 进入维修中", ['repair_id' => $ro->id]);
        return response()->json(['code' => 0, 'data' => $this->present($ro->fresh()), 'message' => '已进入维修']);
    }

    /**
     * 标记修好
     * V0.5.5: 至少要有 1 条 method 记录
     */
    public function markRepaired(Request $request, int $id): JsonResponse
    {
        $ro = RepairOrder::findOrFail($id);
        $this->ensureTransition($ro, RepairOrderStatus::REPAIRED);
        $hasMethod = RepairMethod::where('repair_order_id', $ro->id)->exists();
        if (!$hasMethod) {
            return response()->json(['code' => 422, 'message' => '标记修好前必须先创建至少 1 条维修方式记录'], 422);
        }
        $ro->status = RepairOrderStatus::REPAIRED;
        $ro->save();
        Audit::write('repair_repaired', "返修 {$ro->code} 已修好", ['repair_id' => $ro->id]);
        return response()->json(['code' => 0, 'data' => $this->present($ro->fresh()), 'message' => '已修好']);
    }

    /**
     * 关闭
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'delivery_method'    => 'required|string|in:return_to_work_order,customer_pickup,courier',
            'note'               => 'nullable|string|max:500',
            'carrier'            => 'nullable|string|max:64',
            'tracking_no'        => 'nullable|string|max:64',
            'customer_signature' => 'nullable|string|max:1048576',
        ]);

        $ro = RepairOrder::findOrFail($id);
        $this->ensureTransition($ro, RepairOrderStatus::CLOSED);

        return DB::transaction(function () use ($ro, $data, $request) {
            $ro->status = RepairOrderStatus::CLOSED;
            $ro->remarks = $data['note'] ?? '已交付客户';
            $ro->save();

            // 创建进度日志
            \App\Models\RepairProgressLog::create([
                'repair_order_id' => $ro->id,
                'progress'  => '已交付客户',
                'description' => $data['note'] ?? '',
                'action_by' => $request->user()?->id,
            ]);

            // 快递邮寄 → 创建物流记录
            if ($data['delivery_method'] === 'courier' && !empty($data['carrier'])) {
                \App\Models\RepairShipment::create([
                    'repair_order_id' => $ro->id,
                    'direction' => 'inbound',
                    'carrier'   => $data['carrier'],
                    'tracking_no' => $data['tracking_no'] ?? '',
                    'sender_name' => $ro->contact_name,
                    'receiver_name' => $ro->contact_name,
                    'receiver_address' => $ro->address ?? '',
                    'shipped_at' => now(),
                    'status' => 'shipped',
                ]);
            }

            // 客户签字保存
            if (!empty($data['customer_signature'])) {
                $ro->customer_signature = $data['customer_signature'];
                $ro->save();
            }

            Audit::write('repair_closed', "返修 {$ro->code} 已关闭（{$data['delivery_method']}）", ['repair_id' => $ro->id]);
            return response()->json(['code' => 0, 'data' => $this->present($ro->fresh()), 'message' => '已关闭']);
        });
    }

    public function stats(Request $request): JsonResponse
    {
        $days = (int) ($request->days ?? 30);
        $days = max(1, min($days, 365));

        $byStatus = RepairOrder::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $byMethod = RepairOrder::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('method_type')
            ->select('method_type', DB::raw('COUNT(*) as count'))
            ->groupBy('method_type')
            ->pluck('count', 'method_type')
            ->all();

        $total = RepairOrder::where('created_at', '>=', now()->subDays($days))->count();
        $closed = RepairOrder::where('status', RepairOrderStatus::CLOSED)
            ->where('created_at', '>=', now()->subDays($days))->count();
        $totalCost = (float) RepairOrder::where('created_at', '>=', now()->subDays($days))->sum('total_cost');
        $avgCycle = null;
        $avgRow = RepairOrder::query()
            ->where('status', RepairOrderStatus::CLOSED)
            ->whereNotNull('received_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - received_at))/86400) as days')
            ->value('days');
        $avgCycle = $avgRow ? round((float) $avgRow, 1) : null;

        return response()->json([
            'code' => 0,
            'data' => [
                'days'              => $days,
                'total'             => $total,
                'closed'            => $closed,
                'close_rate'        => $total > 0 ? round($closed / $total * 100, 1) : 0,
                'total_cost'        => $totalCost,
                'avg_cycle_days'    => $avgCycle,
                'by_status'         => [
                    'received'         => (int) ($byStatus['received'] ?? 0),
                    'sent_for_repair'  => (int) ($byStatus['sent_for_repair'] ?? 0),
                    'in_repair'        => (int) ($byStatus['in_repair'] ?? 0),
                    'repaired'         => (int) ($byStatus['repaired'] ?? 0),
                    'sent_back'        => (int) ($byStatus['sent_back'] ?? 0),
                    'closed'           => (int) ($byStatus['closed'] ?? 0),
                    'cancelled'        => (int) ($byStatus['cancelled'] ?? 0),
                ],
                'by_method' => [
                    'free_warranty'  => (int) ($byMethod['free_warranty'] ?? 0),
                    'free_contract'  => (int) ($byMethod['free_contract'] ?? 0),
                    'paid_repair'    => (int) ($byMethod['paid_repair'] ?? 0),
                    'paid_replace'   => (int) ($byMethod['paid_replace'] ?? 0),
                    'returned'       => (int) ($byMethod['returned'] ?? 0),
                ],
            ],
        ]);
    }

    private function ensureTransition(RepairOrder $ro, RepairOrderStatus $to): void
    {
        if ($ro->status->isTerminal()) {
            throw new \LogicException("返修单 {$ro->code} 已终态 ({$ro->status->value})");
        }
        if (!$ro->status->canTransitionTo($to)) {
            throw new \LogicException("非法状态转换: {$ro->status->value} → {$to->value}");
        }
    }

    private function present(RepairOrder $ro, bool $withRelations = false): array
    {
        $arr = [
            'id'                  => $ro->id,
            'code'                => $ro->code,
            'source_type'         => $ro->source_type->value,
            'source_label'        => $ro->source_type->label(),
            'source_id'           => $ro->source_id,
            'source_code'         => $ro->source_code,
            'customer_id'         => $ro->customer_id,
            'customer_name'       => $ro->customer?->name,
            'project_id'          => $ro->project_id,
            'project_name'        => $ro->project?->name,
            'contact_name'        => $ro->contact_name,
            'contact_phone'       => $ro->contact_phone,
            'address'             => $ro->address,
            'equipment_brand'     => $ro->equipment_brand,
            'equipment_model'     => $ro->equipment_model,
            'serial_no'           => $ro->serial_no,
            'fault_type'          => $ro->fault_type,
            'fault_description'   => $ro->fault_description,
            'severity'            => $ro->severity,
            'received_by'         => $ro->received_by,
            'receiver_name'       => $ro->receiver?->name,
            'received_at'         => $ro->received_at?->toDateTimeString(),
            'expected_finish_at'  => $ro->expected_finish_at?->toDateTimeString(),
            'status'              => $ro->status->value,
            'status_label'        => $ro->status->label(),
            'status_color'        => $ro->status->color(),
            'method_type'         => $ro->method_type?->value,
            'method_label'        => $ro->method_type?->label(),
            'is_paid'             => $ro->method_type?->isPaid() ?? false,
            'parts_cost'          => (float) $ro->parts_cost,
            'labor_cost'          => (float) $ro->labor_cost,
            'shipping_cost'       => (float) $ro->shipping_cost,
            'total_cost'          => (float) $ro->total_cost,
            'is_warranty'         => $ro->is_warranty,
            'warranty_until'      => $ro->warranty_until?->toDateString(),
            'remarks'             => $ro->remarks,
            'created_at'          => $ro->created_at?->toDateTimeString(),
        ];
        if ($withRelations) {
            if ($ro->relationLoaded('shipments')) {
                $arr['shipments'] = $ro->shipments->map(fn ($s) => [
                    'id' => $s->id,
                    'direction' => $s->direction->value,
                    'direction_label' => $s->direction->label(),
                    'carrier' => $s->carrier,
                    'tracking_no' => $s->tracking_no,
                    'cost' => (float) $s->cost,
                    'shipped_at' => $s->shipped_at?->toDateTimeString(),
                    'estimated_arrival' => $s->estimated_arrival?->toDateTimeString(),
                    'actual_arrival' => $s->actual_arrival?->toDateTimeString(),
                    'delivery_status' => $s->delivery_status,
                    'sender_name' => $s->sender_name,
                    'receiver_name' => $s->receiver_name,
                    'remarks' => $s->remarks,
                ])->values();
            }
            if ($ro->relationLoaded('methods')) {
                $arr['methods'] = $ro->methods->map(fn ($m) => [
                    'id' => $m->id,
                    'method_type' => $m->method_type->value,
                    'method_label' => $m->method_type->label(),
                    'method_category' => $m->method_category,
                    'estimated_cost' => (float) $m->estimated_cost,
                    'actual_cost' => (float) $m->actual_cost,
                    'parts_replaced' => $m->parts_replaced,
                    'hours_spent' => (float) $m->hours_spent,
                    'vendor_id' => $m->vendor_id,
                    'payment_method' => $m->payment_method,
                    'payment_status' => $m->payment_status,
                    'paid_at' => $m->paid_at?->toDateTimeString(),
                    'invoice_no' => $m->invoice_no,
                    'remarks' => $m->remarks,
                ])->values();
            }
            if ($ro->relationLoaded('progressLogs')) {
                $arr['progress_logs'] = $ro->progressLogs->map(fn ($l) => [
                    'id' => $l->id,
                    'progress' => $l->progress,
                    'description' => $l->description,
                    'cost_added' => (float) $l->cost_added,
                    'is_paid' => $l->is_paid,
                    'actor_name' => $l->actor?->name,
                    'action_at' => $l->action_at?->toDateTimeString(),
                ])->values();
            }
            if ($ro->relationLoaded('attachments')) {
                $arr['attachments'] = $ro->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'file_name' => $a->file_name,
                    'file_type' => $a->file_type,
                    'category' => $a->category,
                    'file_path' => $a->file_path,
                ])->values();
            }
        }
        return $arr;
    }

    private function nextCode(): string
    {
        $year = now()->format('Y');
        // V0.5.5 修: 用 MAX(code) + 1 避免 race condition
        $lastSeq = (int) RepairOrder::where('code', 'like', "RN{$year}-%")
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(code FROM 'RN[0-9]{4}-([0-9]+)') AS INTEGER)), 0) as seq")
            ->value('seq');
        return sprintf('RN%s-%03d', $year, $lastSeq + 1);
    }

    // ============ V0.5.5.2 A6 — 附件上传 (物流凭证图/过程照片) ============

    public function listAttachments(int $repairOrderId): JsonResponse
    {
        $rows = \App\Models\RepairAttachment::where('repair_order_id', $repairOrderId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'file_name'    => $a->file_name,
                'file_path'    => $a->file_path,
                'file_url'     => asset('storage/' . $a->file_path),
                'file_type'    => $a->file_type,
                'category'     => $a->category,
                'uploaded_by'  => $a->uploaded_by,
                'uploaded_at'  => $a->uploaded_at?->toDateTimeString(),
            ]);
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    public function uploadAttachment(Request $request, int $repairOrderId): JsonResponse
    {
        $request->validate([
            'file'     => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip,rar',
            'category' => 'nullable|string|in:receipt,shipping,process,repaired,other',
        ]);
        $ro = RepairOrder::findOrFail($repairOrderId);

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $dir = "repairs/{$ro->code}/" . date('Ymd');
        $path = $file->storeAs($dir, uniqid('att_') . '.' . $ext, 'public');

        $att = \App\Models\RepairAttachment::create([
            'repair_order_id' => $ro->id,
            'file_name'       => $file->getClientOriginalName(),
            'file_path'       => $path,
            'file_type'       => $file->getMimeType(),
            'file_size'       => $file->getSize(),
            'category'        => $request->input('category', 'other'),
            'uploaded_by'     => $request->user()?->id,
            'uploaded_at'     => now(),
        ]);

        return response()->json(['code' => 0, 'data' => [
            'id'        => $att->id,
            'file_name' => $att->file_name,
            'file_url'  => asset('storage/' . $att->file_path),
        ], 'message' => '上传成功']);
    }

    /**
     * V1.2.10 修复 IDOR: 校验 uploaded_by (owner 或 admin 可删)
     */
    public function deleteAttachment(Request $request, int $repairOrderId, int $id): JsonResponse
    {
        $att = \App\Models\RepairAttachment::where('repair_order_id', $repairOrderId)->findOrFail($id);
        $user = $request->user();
        if ($att->uploaded_by !== $user?->id && !$this->isAdmin($user)) {
            return response()->json(['code' => 403, 'message' => '只能删除自己上传的附件'], 403);
        }
        \Illuminate\Support\Facades\Storage::disk('public')->delete($att->file_path);
        $att->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    private function isAdmin($user): bool
    {
        if (!$user) return false;
        try {
            return in_array('admin', $user->activeRoles()->pluck('name')->all(), true);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
