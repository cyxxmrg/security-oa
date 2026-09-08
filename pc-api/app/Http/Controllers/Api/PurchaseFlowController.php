<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequirement;
use App\Models\PurchasePlan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseContract;
use App\Models\PurchasePaymentRequest;
use App\Models\PurchasePayment;
use App\Models\PurchaseShipment;
use App\Models\PurchaseContractFile;
use App\Models\PurchasePaymentVoucher;
use App\Models\WorkOrder;
use App\Models\ExternalConstructionWork;
use App\Models\Project;
use App\Services\PurchaseFlowService;
// 合并两侧 import: HEAD 需要 AuthScope(assertSourceAccess), origin/main 需要 PrivateFileStorage(私有附件下载)
use App\Support\AuthScope;
use App\Support\PrivateFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * V0.6.2 采购协同 — 8 步自动流转 API
 *
 * 全部走 PurchaseFlowService, 状态机 + 自动建下一阶段 + 审批中心同步
 *
 *  POST   /api/purchase-flow/requirements                  新建需求
 *  GET    /api/purchase-flow/requirements/{id}/trace      单需求全链路
 *  GET    /api/purchase-flow/by-source/{type}/{id}        从来源反查
 *  POST   /api/purchase-flow/requirements/{id}/approve
 *  POST   /api/purchase-flow/plans                         新建计划
 *  POST   /api/purchase-flow/plans/{id}/submit
 *  POST   /api/purchase-flow/plans/{id}/approve
 *  POST   /api/purchase-flow/orders                        从 plan 创建 PO
 *  POST   /api/purchase-flow/orders/{id}/submit
 *  POST   /api/purchase-flow/orders/{id}/approve
 *  POST   /api/purchase-flow/contracts                     从 PO 起草
 *  POST   /api/purchase-flow/contracts/{id}/sign
 *  POST   /api/purchase-flow/payment-requests              从合同申请
 *  POST   /api/purchase-flow/payment-requests/{id}/approve
 *  POST   /api/purchase-flow/payments                      执行付款
 *  POST   /api/purchase-flow/shipments                     发货
 *  POST   /api/purchase-flow/shipments/{id}/update-status
 *  POST   /api/purchase-flow/shipments/{id}/auto-inbound  自动建入库单
 *  POST   /api/purchase-flow/shipments/{id}/confirm-inbound
 *  GET    /api/purchase-flow/logs?entity_type=&entity_id=
 */
class PurchaseFlowController extends Controller
{
    public function __construct(protected PurchaseFlowService $flow) {}

    // ============== 公共: 撤回/取消 ==============

    /**
     * 撤回任意实体 (V0.6.3)
     * POST /api/purchase-flow/{entityType}/{id}/cancel
     * body: { remark?: string }
     */
    public function cancel(Request $request, string $entityType, int $id): JsonResponse
    {
        $data = $request->validate(['remark' => 'nullable|string|max:500']);
        try {
            $r = $this->flow->cancel($entityType, $id, $request->user(), $data['remark'] ?? '业务方撤回');
            return response()->json(['code' => 0, 'data' => $r, 'message' => '已撤回']);
        } catch (\InvalidArgumentException $e) {
            \Log::error('cancel_CATCH', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            \Log::error('cancel_CATCH', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1002, 'message' => $e->getMessage()], 422);
        }
    }

    // ============== 需求 ==============

    public function createRequirement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'nullable|string|max:200',
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'material'     => 'required|string|max:200',
            'spec'         => 'nullable|string|max:200',
            'spec_text'    => 'nullable|string|max:500',
            'quantity'     => 'required|numeric|min:0.01',
            'unit'         => 'nullable|string|max:20',
            'budget'       => 'nullable|numeric|min:0',
            'need_date'    => 'nullable|date',
            'priority'     => 'nullable|in:low,medium,high,urgent',
            'project_id'   => 'nullable|integer|exists:projects,id',
            'source_type'  => 'required|in:work_order,external_work,project,stock_alert,manual,customer_contract',
            'source_id'    => 'nullable|integer',
            'remark'       => 'nullable|string',
        ]);

        $req = $this->flow->createRequirement($data, $request->user());
        return response()->json(['code' => 0, 'data' => $req]);
    }

    /** 提交需求审批 (需求创建时已 pending, 此端点为前端流程对齐保留) */
    public function submitRequirement(int $id): JsonResponse
    {
        $req = PurchaseRequirement::findOrFail($id);
        return response()->json(['code' => 0, 'data' => $req, 'message' => '需求已提交, 等待审批']);
    }

    public function approveRequirement(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['remark' => 'nullable|string|max:500']);
        $req = $this->flow->approveRequirement($id, $request->user(), $data['remark'] ?? '');
        return response()->json(['code' => 0, 'data' => $req]);
    }

    /**
     * 从来源反查 (用于工单/发包详情页"已发起采购"展示)
     */
    public function bySource(string $type, int $id): JsonResponse
    {
        $reqs = PurchaseRequirement::where('source_type', $type)->where('source_id', $id)
            ->orderBy('created_at', 'desc')->get();
        return response()->json(['code' => 0, 'data' => $reqs]);
    }

    public function trace(string $entityType, int $id): JsonResponse
    {
        $trace = $this->flow->trace($entityType, $id);
        return response()->json([
            'code' => 0,
            'data' => [
                'requirement'  => $trace['requirement'] ?? null,
                'plans'        => $trace['plans'] ?? [],
                'orders'       => $trace['orders'] ?? [],
                'contracts'    => $trace['contracts'] ?? [],
                'payment_reqs' => $trace['payment_reqs'] ?? [],
                'payments'     => $trace['payments'] ?? [],
                'shipments'    => $trace['shipments'] ?? [],
                'logs'         => $trace['logs'] ?? [],
            ],
        ]);
    }

    // ============== 计划 ==============

    public function createPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'          => 'required|string|max:200',
            'project_id'     => 'nullable|integer|exists:projects,id',
            'total_amount'   => 'nullable|numeric|min:0',
            'plan_date'      => 'nullable|date',
            'priority'       => 'nullable|in:low,medium,high,urgent',
            'requirement_ids'=> 'nullable|array',
            'requirement_ids.*' => 'integer|exists:purchase_requirements,id',
            'remark'         => 'nullable|string',
        ]);
        $plan = $this->flow->createPlan($data, $request->user(), $data['requirement_ids'] ?? []);
        return response()->json(['code' => 0, 'data' => $plan]);
    }

    public function submitPlan(int $id, Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->flow->submitPlan($id, $request->user())]);
    }

    public function approvePlan(int $id, Request $request): JsonResponse
    {
        $data = $request->validate(['remark' => 'nullable|string|max:500']);
        return response()->json(['code' => 0, 'data' => $this->flow->approvePlan($id, $request->user(), $data['remark'] ?? '')]);
    }

    // ============== 采购单 ==============

    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id'      => 'required|integer|exists:purchase_plans,id',
            'supplier_id'  => 'required|integer|exists:suppliers,id',
            'title'        => 'nullable|string|max:200',
            'total_amount' => 'required|numeric|min:0',
            'tender_id'    => 'nullable|integer|exists:tender_projects,id',
            'quote_id'     => 'nullable|integer',
            'path'         => 'required|in:quote,bid,manual',
            'notes'        => 'nullable|string',
        ]);
        $po = $this->flow->planToOrder($data['plan_id'], $data, $data['path'], $request->user());
        return response()->json(['code' => 0, 'data' => $po]);
    }

    public function submitOrder(int $id, Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->flow->submitOrder($id, $request->user())]);
    }

    public function approveOrder(int $id, Request $request): JsonResponse
    {
        $data = $request->validate(['remark' => 'nullable|string|max:500']);
        return response()->json(['code' => 0, 'data' => $this->flow->approveOrder($id, $request->user(), $data['remark'] ?? '')]);
    }

    // ============== 合同 ==============

    public function createContract(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'          => 'required|integer|exists:purchase_orders,id',
            'title'             => 'nullable|string|max:200',
            'total_amount'      => 'nullable|numeric|min:0',
            'signed_at'         => 'nullable|date',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date',
            'payment_terms'     => 'nullable|string|max:200',
            'payment_plan'      => 'nullable|array',
            'delivery_address'  => 'nullable|string|max:200',
            'remark'            => 'nullable|string',
        ]);
        $c = $this->flow->createContract($data['order_id'], $data, $request->user());
        return response()->json(['code' => 0, 'data' => $c]);
    }

    public function signContract(int $id, Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->flow->signContract($id, $request->user())]);
    }

    // ============== 付款申请 ==============

    public function createPaymentRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id'  => 'required|integer|exists:purchase_contracts,id',
            'amount'       => 'required|numeric|min:0.01',
            'payment_type' => 'nullable|in:full,partial,final,deposit',
            'stage_label'  => 'nullable|string|max:50',
            'request_date' => 'nullable|date',
            'reason'       => 'nullable|string',
        ]);
        return response()->json(['code' => 0, 'data' => $this->flow->createPaymentRequest($data['contract_id'], $data, $request->user())]);
    }

    public function approvePaymentRequest(int $id, Request $request): JsonResponse
    {
        $data = $request->validate(['remark' => 'nullable|string|max:500']);
        return response()->json(['code' => 0, 'data' => $this->flow->approvePaymentRequest($id, $request->user(), $data['remark'] ?? '')]);
    }

    // ============== 财务付款 ==============

    public function executePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_request_id' => 'required|integer|exists:purchase_payment_requests,id',
            'payment_method'     => 'required|in:transfer,cash,check,other',
            'paid_at'            => 'nullable|date',
            'voucher_no'         => 'nullable|string|max:100',
            'remark'             => 'nullable|string',
        ]);
        try {
            $req = PurchasePaymentRequest::findOrFail($data['payment_request_id']);
            $pay = $this->flow->executePayment($req->id, $data, $request->user());
            return response()->json(['code' => 0, 'data' => $pay]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    // ============== 收货 ==============

    public function createShipment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id'         => 'required|integer|exists:purchase_contracts,id',
            'shipped_at'          => 'nullable|date',
            'expected_arrival_at' => 'nullable|date',
            'carrier'             => 'nullable|string|max:100',
            'tracking_no'         => 'nullable|string|max:100',
            'consignee'           => 'nullable|string|max:50',
            'remark'              => 'nullable|string',
        ]);
        $sh = $this->flow->createShipment($data['contract_id'], $data, $request->user());
        return response()->json(['code' => 0, 'data' => $sh]);
    }

    public function updateShipmentStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,shipped,in_transit,arrived,received,inspected',
            'remark' => 'nullable|string|max:500',
        ]);
        return response()->json(['code' => 0, 'data' => $this->flow->updateShipmentStatus($id, $data['status'], $request->user(), $data['remark'] ?? '')]);
    }

    public function autoInbound(int $id, Request $request): JsonResponse
    {
        $r = $this->flow->autoCreateInbound($id, $request->user());
        return response()->json(['code' => 0, 'data' => $r]);
    }

    public function confirmInbound(int $id, Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->flow->confirmInbound($id, $request->user())]);
    }

    // ============== V0.6.2.2 合同附件/清单/付款凭证/发货计划 ==============

    /** 列出合同附件 */
    public function listContractFiles(int $id): JsonResponse
    {
        $rows = $this->flow->listContractFiles($id);
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    /** 上传合同附件 (PDF/图片) */
    public function uploadContractFile(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,png,jpeg,jpg,doc,docx|max:20480',  // 20MB
        ]);
        $record = $this->flow->uploadContractFile($id, $request->file('file'), $request->user());
        return response()->json(['code' => 0, 'data' => [
            'id'   => $record->id,
            'name' => $record->file_name,
            'url'  => "/api/purchase-flow/contracts/{$id}/files/{$record->id}/download",
            'size' => $record->size,
        ], 'message' => '合同附件已上传']);
    }

    /** 下载合同附件 */
    public function downloadContractFile(int $id, int $fid)
    {
        // 合并说明: 保留 HEAD 的"合同必须存在"守卫 + origin/main 的 PrivateFileStorage 私有下载
        PurchaseContract::findOrFail($id);
        $file = PurchaseContractFile::where('contract_id', $id)->findOrFail($fid);
        return PrivateFileStorage::download($file->file_path, $file->file_name, [
            'Content-Type' => $file->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** 列出合同清单 */
    public function listContractItems(int $id): JsonResponse
    {
        PurchaseContract::findOrFail($id);
        $rows = \App\Models\PurchaseContractItem::where('contract_id', $id)
            ->orderBy('id')->get();
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    /** 同步合同清单 (从 PO.items) */
    public function syncContractItems(int $id, Request $request): JsonResponse
    {
        $r = $this->flow->syncContractItems($id, $request->user());
        return response()->json(['code' => 0, 'data' => $r, 'message' => $r['skipped'] ? '已存在, 跳过' : "已同步 {$r['count']} 行清单"]);
    }

    /** 添加合同清单行 */
    public function addContractItem(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'material'   => 'required|string|max:200',
            'spec'       => 'nullable|string|max:200',
            'qty'        => 'required|numeric|min:0.01',
            'unit'       => 'nullable|string|max:20',
            'unit_price' => 'required|numeric|min:0',
            'remark'     => 'nullable|string|max:500',
        ]);
        $item = $this->flow->addContractItem($id, $data, $request->user());
        return response()->json(['code' => 0, 'data' => $item, 'message' => '已添加清单行']);
    }

    /** 修改合同清单行 */
    public function updateContractItem(Request $request, int $id, int $iid): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'material'   => 'nullable|string|max:200',
            'spec'       => 'nullable|string|max:200',
            'qty'        => 'nullable|numeric|min:0.01',
            'unit'       => 'nullable|string|max:20',
            'unit_price' => 'nullable|numeric|min:0',
            'remark'     => 'nullable|string|max:500',
        ]);
        $item = $this->flow->updateContractItem($id, $iid, $data, $request->user());
        return response()->json(['code' => 0, 'data' => $item, 'message' => '已更新清单行']);
    }

    /** 删除合同清单行 */
    public function deleteContractItem(int $id, int $iid, Request $request): JsonResponse
    {
        $this->flow->removeContractItem($id, $iid, $request->user());
        return response()->json(['code' => 0, 'message' => '已删除清单行']);
    }

    /** 上传付款凭证 */
    public function uploadPaymentVoucher(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file'  => 'required|file|mimes:pdf,png,jpeg,jpg|max:20480',  // 20MB
            'remark'=> 'nullable|string|max:500',
        ]);
        $record = $this->flow->uploadPaymentVoucher($id, $request->file('file'), $request->user(), $request->input('remark'));
        return response()->json(['code' => 0, 'data' => [
            'id'   => $record->id,
            'name' => $record->file_name,
            'url'  => "/api/purchase-flow/payment-requests/{$id}/vouchers/{$record->id}/download",
            'size' => $record->size,
        ], 'message' => '付款凭证已上传']);
    }

    /** 下载付款凭证 */
    public function downloadPaymentVoucher(int $id, int $vid): StreamedResponse|JsonResponse
    {
        $paymentRequest = PurchasePaymentRequest::findOrFail($id);
        PurchaseContract::findOrFail($paymentRequest->contract_id);
        $file = PurchasePaymentVoucher::where('payment_request_id', $id)->findOrFail($vid);
        return $this->downloadPurchaseFile($file->file_path, $file->file_name, $file->mime);
    }

    /** 列出付款凭证 */
    public function listPaymentVouchers(int $id): JsonResponse
    {
        $rows = $this->flow->listPaymentVouchers($id);
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    private function downloadPurchaseFile(string $path, string $name, ?string $mime): StreamedResponse|JsonResponse
    {
        $privateDisk = Storage::disk('attachments');
        if ($privateDisk->exists($path)) {
            return $privateDisk->download($path, $name, ['Content-Type' => $mime ?: 'application/octet-stream']);
        }

        $legacyDisk = Storage::disk('public');
        if ($legacyDisk->exists($path)) {
            return $legacyDisk->download($path, $name, ['Content-Type' => $mime ?: 'application/octet-stream']);
        }

        return response()->json(['code' => 404, 'message' => '文件不存在'], 404);
    }

    /** 设置发货预期 (按合同清单行拆分) */
    public function setShippingPlan(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'contract_item_id' => 'nullable|integer',
            'expected_at'      => 'nullable|date',
            'carrier'          => 'nullable|string|max:100',
            'tracking_no'      => 'nullable|string|max:100',
            'shipped_at'       => 'nullable|date',
            'status'           => 'nullable|in:planned,shipped,in_transit,arrived,received',
            'remark'           => 'nullable|string|max:500',
        ]);
        $plan = $this->flow->setShippingPlan($id, $data, $request->user());
        return response()->json(['code' => 0, 'data' => $plan, 'message' => '已设置发货预期']);
    }

    /** 添加快递单号 (contract_item_id 可空 = 整单) */
    public function addTracking(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'contract_item_id' => 'nullable|integer',
            'carrier'          => 'required|string|max:100',
            'tracking_no'      => 'required|string|max:100',
            'shipped_at'       => 'nullable|date',
            'remark'           => 'nullable|string|max:500',
        ]);
        $plan = $this->flow->addTracking($id, $data, $request->user());
        return response()->json(['code' => 0, 'data' => $plan, 'message' => '已记录快递单号']);
    }

    /** 列出合同的发货/快递记录 */
    public function listShipping(int $id): JsonResponse
    {
        $rows = $this->flow->listShipping($id);
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    // ============== 日志 ==============

    public function logs(Request $request): JsonResponse
    {
        $q = \DB::table('purchase_status_logs');
        if ($request->filled('entity_type')) $q->where('entity_type', $request->entity_type);
        if ($request->filled('entity_id'))   $q->where('entity_id', $request->entity_id);
        $scopedEntities = [
            PurchaseRequirement::class => PurchaseRequirement::query()->select('id'),
            PurchasePlan::class => PurchasePlan::query()->select('id'),
            PurchaseOrder::class => PurchaseOrder::query()->select('id'),
            PurchaseContract::class => PurchaseContract::query()->select('id'),
            PurchasePaymentRequest::class => PurchasePaymentRequest::query()->select('id'),
            PurchasePayment::class => PurchasePayment::query()->select('id'),
            PurchaseShipment::class => PurchaseShipment::query()->select('id'),
        ];
        $entityTypes = [
            PurchaseFlowService::ENTITY_REQUIREMENT => PurchaseRequirement::class,
            PurchaseFlowService::ENTITY_PLAN => PurchasePlan::class,
            PurchaseFlowService::ENTITY_ORDER => PurchaseOrder::class,
            PurchaseFlowService::ENTITY_CONTRACT => PurchaseContract::class,
            PurchaseFlowService::ENTITY_PAYMENT_REQ => PurchasePaymentRequest::class,
            PurchaseFlowService::ENTITY_PAYMENT => PurchasePayment::class,
            PurchaseFlowService::ENTITY_SHIPMENT => PurchaseShipment::class,
        ];
        $q->where(function ($scope) use ($entityTypes, $scopedEntities): void {
            foreach ($entityTypes as $type => $modelClass) {
                $scope->orWhere(function ($branch) use ($type, $modelClass, $scopedEntities): void {
                    $branch->where('entity_type', $type)
                        ->whereIn('entity_id', $scopedEntities[$modelClass]);
                });
            }
        });
        $limit = max(1, min((int) $request->input('limit', 200), 200));
        $rows = $q->orderBy('created_at', 'desc')->limit($limit)->get();
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    /**
     * 列出采购单 (V0.6.2.2: 给采购详情页用)
     * GET /api/purchase-flow/orders-list?keyword=&status=
     * 不走 /purchase/orders 老端点 (那个不存在)
     */
    public function listOrders(Request $request): JsonResponse
    {
        // V0.6.4 招标联动: 加载 tender 关系, 让前端显示"来源招标"
        $q = PurchaseOrder::with(['supplier:id,name,code', 'plan:id,code,title', 'tender:id,code,name']);
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $q->where(function ($w) use ($kw) {
                $w->where('code', 'like', "%{$kw}%")
                  ->orWhere('po_no', 'like', "%{$kw}%")
                  ->orWhere('title', 'like', "%{$kw}%");
            });
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('project_id')) $q->where('project_id', $request->project_id);
        $limit = max(1, min((int) $request->input('per_page', 200), 200));
        $rows = $q->orderBy('id', 'desc')->limit($limit)->get();
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    /**
     * 列出采购需求 (按 project_id 过滤)
     * GET /api/purchase-flow/requirements-list?project_id=X
     */
    public function listRequirements(Request $request): JsonResponse
    {
        $q = PurchaseRequirement::query();
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $q->where(function ($w) use ($kw) {
                $w->where('code', 'like', "%{$kw}%")
                  ->orWhere('material', 'like', "%{$kw}%")
                  ->orWhere('title', 'like', "%{$kw}%");
            });
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('project_id')) $q->where('project_id', $request->project_id);
        $limit = max(1, min((int) $request->input('per_page', 200), 200));
        $rows = $q->orderBy('id', 'desc')->limit($limit)->get();
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    /**
     * 列出采购合同 (按 project_id 过滤)
     * GET /api/purchase-flow/contracts-list?project_id=X
     */
    public function listPurchaseContracts(Request $request): JsonResponse
    {
        $q = PurchaseContract::with(['supplier:id,name,code']);
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $q->where(function ($w) use ($kw) {
                $w->where('code', 'like', "%{$kw}%")
                  ->orWhere('title', 'like', "%{$kw}%");
            });
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('project_id')) $q->where('project_id', $request->project_id);
        $limit = max(1, min((int) $request->input('per_page', 200), 200));
        $rows = $q->orderBy('id', 'desc')->limit($limit)->get();
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    // ============== 一键发起 (从来源) ==============
    /**
     * 维修工单完成时一键发起采购
     * POST /api/purchase-flow/from-work-order/{workOrderId}
     */
    public function fromWorkOrder(int $workOrderId, Request $request): JsonResponse
    {
        $wo = WorkOrder::findOrFail($workOrderId);
        $this->assertSourceAccess($wo->project_id, [$wo->created_by, $wo->assigned_to], $request->user());
        if ($wo->status !== 'completed') {
            return response()->json(['code' => 1, 'message' => "工单状态 {$wo->status}, 仅 completed 可发起采购"], 422);
        }
        $data = $request->validate([
            'material'  => 'required|string|max:200',
            'quantity'  => 'required|numeric|min:0.01',
            'unit'      => 'nullable|string|max:20',
            'spec'      => 'nullable|string|max:200',
            'budget'    => 'nullable|numeric|min:0',
            'priority'  => 'nullable|in:low,medium,high,urgent',
            'remark'    => 'nullable|string',
        ]);
        $req = $this->flow->createRequirement(array_merge($data, [
            'name'        => "[维修] {$wo->code} - {$data['material']}",
            'project_id'  => $wo->project_id,
            'source_type' => 'work_order',
            'source_id'   => $wo->id,
        ]), $request->user());
        return response()->json(['code' => 0, 'data' => $req, 'message' => '采购需求已创建']);
    }

    /**
     * 施工发包关闭时一键发起采购
     * POST /api/purchase-flow/from-external-work/{workId}
     */
    public function fromExternalWork(int $workId, Request $request): JsonResponse
    {
        $w = ExternalConstructionWork::findOrFail($workId);
        $this->assertSourceAccess($w->project_id, [$w->created_by], $request->user());
        $data = $request->validate([
            'material' => 'required|string|max:200',
            'quantity' => 'required|numeric|min:0.01',
            'unit'     => 'nullable|string|max:20',
            'spec'     => 'nullable|string|max:200',
            'budget'   => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'remark'   => 'nullable|string',
        ]);
        $req = $this->flow->createRequirement(array_merge($data, [
            'name'        => "[施工] {$w->code} - {$data['material']}",
            'project_id'  => $w->project_id,
            'source_type' => 'external_work',
            'source_id'   => $w->id,
        ]), $request->user());
        return response()->json(['code' => 0, 'data' => $req, 'message' => '采购需求已创建']);
    }

    private function assertSourceAccess(?int $projectId, array $ownerIds, $user): void
    {
        if (!$user || AuthScope::isUnrestricted($user)) {
            return;
        }
        if (in_array((int) $user->id, array_map('intval', array_filter($ownerIds)), true)) {
            return;
        }
        if ($projectId && Project::query()->whereKey($projectId)->where(function ($query) use ($user): void {
            $query->where('manager_id', $user->id)
                ->orWhereHas('members', function ($memberQuery) use ($user): void {
                    $memberQuery->whereKey($user->id)
                        ->where('project_members.status', 'active');
                });
        })->exists()) {
            return;
        }
        abort(403, '无权从该来源发起采购');
    }
}
