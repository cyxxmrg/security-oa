<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenderProject;
use App\Models\TenderBid;
use App\Models\TenderAttachment;
use App\Models\TenderDeposit;
use App\Models\TenderDepositRule;
use App\Models\PurchaseOrder;
use App\Models\PurchaseItem;
use App\Models\Payable;
use App\Services\PurchaseFlowService;
use App\Services\FileUploadService;
use App\Services\TenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * V0.6.0 招标中心 — 内部 API
 *
 * 路由: /api/tenders
 *   GET    /tenders                       项目列表
 *   POST   /tenders                       新建项目 (草稿)
 *   GET    /tenders/{id}                  项目详情
 *   PUT    /tenders/{id}                  修改项目
 *   POST   /tenders/{id}/publish          发布 (生成 public_token)
 *   POST   /tenders/{id}/close            关闭
 *   POST   /tenders/{id}/cancel           取消
 *   POST   /tenders/{id}/evaluate         评标打分
 *   POST   /tenders/{id}/award            中标 (自动生成 PO + 应付)
 *   GET    /tenders/{id}/bids             投标列表
 *   POST   /tenders/{id}/bids             新建投标 (内部代理用, 正常走 PortalController)
 *   GET    /tenders/{id}/attachments      附件列表
 *   POST   /tenders/{id}/attachments      上传附件
 *   DELETE /tenders/{id}/attachments/{att} 删除附件
 */
class TenderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = TenderProject::query();
        if ($kw = $request->input('keyword')) {
            $q->where(function ($qq) use ($kw) {
                $qq->where('name', 'like', "%{$kw}%")
                   ->orWhere('code', 'like', "%{$kw}%");
            });
        }
        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }
        if ($pid = $request->input('project_id')) {
            $q->where('project_id', $pid);
        }
        $total = (clone $q)->count();
        $list  = $q->with(['awardedSupplier:id,name,code', 'creator:id,name', 'project:id,name,project_no'])
                    ->orderByDesc('id')
                    ->paginate($request->input('per_page', 20));
        return response()->json(['code' => 0, 'data' => ['items' => $list->items(), 'total' => $total]]);
    }

    public function store(Request $request): JsonResponse
    {
        // V1.2.10: title 别名 → name
        if ($request->has('title') && !$request->has('name')) {
            $request->merge(['name' => $request->input('title')]);
        }
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'title'       => 'nullable|string|max:200',  // 别名
            'project_id'  => 'nullable|integer|exists:projects,id',
            'rfq_id'      => 'nullable|integer|exists:external_quote_requests,id',
            'type'        => 'nullable|in:rfq,tender,negotiation',
            'description' => 'nullable|string|max:2000',
            'required_items'   => 'nullable|array',
            'invited_supplier_ids' => 'nullable|array',
            'invited_supplier_ids.*' => 'integer|exists:suppliers,id',
            'deadline'    => 'nullable|date',
            'open_at'     => 'nullable|date',
            'score_config' => 'nullable|array',
            'budget'      => 'nullable|numeric|min:0',  // 可选预算
        ]);
        unset($data['title']);
        $data['code'] = 'T-' . date('Ymd') . '-' . str_pad((string)(TenderProject::whereDate('created_at', today())->count() + 1), 3, '0', STR_PAD_LEFT);
        $data['status']      = 'draft';
        $data['public_token'] = (string) Str::uuid();
        $data['created_by']  = $request->user()->id;
        $t = TenderProject::create($data);
        return response()->json(['code' => 0, 'data' => $t], 201);
    }

    public function show(int $id): JsonResponse
    {
        $t = TenderProject::with([
            'project:id,name,project_no',
            'creator:id,name',
            'awardedSupplier:id,name,code',
            'attachments' => fn($q) => $q->whereNull('tender_bid_id'),
        ])->findOrFail($id);
        // 投标摘要 (轻量, 不含 items)
        $bids = $t->bids()->with('supplier:id,name,code')->orderBy('total_score', 'desc')->get();
        return response()->json(['code' => 0, 'data' => array_merge($t->toArray(), ['bids_summary' => $bids])]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $t = TenderProject::findOrFail($id);
        if (in_array($t->status, ['awarded', 'cancelled', 'closed'])) {
            return response()->json(['code' => 1001, 'message' => '该状态不可修改'], 422);
        }
        $data = $request->validate([
            'name'      => 'sometimes|required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'required_items'   => 'nullable|array',
            'invited_supplier_ids' => 'nullable|array',
            'invited_supplier_ids.*' => 'integer|exists:suppliers,id',
            'deadline'  => 'nullable|date',
            'open_at'   => 'nullable|date',
            'score_config' => 'nullable|array',
        ]);
        $t->fill($data)->save();
        return response()->json(['code' => 0, 'data' => $t]);
    }

    public function publish(int $id): JsonResponse
    {
        $t = TenderProject::findOrFail($id);
        if ($t->status !== 'draft') {
            return response()->json(['code' => 1001, 'message' => '仅草稿状态可发布'], 422);
        }
        $t->status     = 'bidding';
        $t->publish_at = now();
        if (!$t->public_token) {
            $t->public_token = (string) Str::uuid();
        }
        $t->save();
        return response()->json(['code' => 0, 'message' => '已发布', 'data' => $t]);
    }

    public function close(int $id): JsonResponse
    {
        $t = TenderProject::findOrFail($id);
        $t->status = 'closed';
        $t->save();
        return response()->json(['code' => 0, 'message' => '已关闭']);
    }

    public function cancel(int $id): JsonResponse
    {
        $t = TenderProject::findOrFail($id);
        $t->status = 'cancelled';
        $t->save();
        return response()->json(['code' => 0, 'message' => '已取消']);
    }

    // 评标打分 (内部用) — 接收 { bid_id, scores: { technical, price, business } }
    public function evaluate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'evaluations' => 'required|array|min:1',
            'evaluations.*.bid_id'   => 'required|integer|exists:tender_bids,id',
            'evaluations.*.technical' => 'required|numeric|min:0|max:100',
            'evaluations.*.price'     => 'required|numeric|min:0|max:100',
            'evaluations.*.business'  => 'required|numeric|min:0|max:100',
        ]);
        $t = TenderProject::findOrFail($id);
        // 评分权重 (从 score_config 读, 缺省 40/40/20)
        $cfg = $t->score_config ?: ['technical' => 40, 'price' => 40, 'business' => 20];
        $wT = (float)($cfg['technical'] ?? 40);
        $wP = (float)($cfg['price'] ?? 40);
        $wB = (float)($cfg['business'] ?? 20);
        $wSum = max(0.0001, $wT + $wP + $wB);

        // V1.2.10 修复 N+1: 一次性加载所有 bid, 避免循环内逐条查询
        $bidIds = array_column($data['evaluations'], 'bid_id');
        $bids = TenderBid::where('tender_project_id', $id)
            ->whereIn('id', $bidIds)
            ->get()
            ->keyBy('id');

        foreach ($data['evaluations'] as $e) {
            $bid = $bids->get($e['bid_id']);
            if (!$bid) continue;
            $score = [
                'technical' => (float)$e['technical'],
                'price'     => (float)$e['price'],
                'business'  => (float)$e['business'],
            ];
            $total = round(($score['technical'] * $wT + $score['price'] * $wP + $score['business'] * $wB) / $wSum, 2);
            $bid->scores = $score;
            $bid->total_score = $total;
            $bid->status = 'shortlisted';
            $bid->save();
        }
        $t->status = 'evaluating';
        $t->save();
        return response()->json(['code' => 0, 'message' => '已记录评分']);
    }

    // V0.6.4 中标 (自动生成 PO + 应付 + 物料明细 + 审计) — 整事务包裹
    public function award(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'bid_id' => 'required|integer|exists:tender_bids,id',
        ]);
        $t = TenderProject::with('bids')->findOrFail($id);
        if (in_array($t->status, ['awarded', 'cancelled', 'closed'])) {
            return response()->json(['code' => 1001, 'message' => '该状态不可定标'], 422);
        }
        $bid = $t->bids()->find($data['bid_id']);
        if (!$bid) {
            return response()->json(['code' => 1001, 'message' => '投标不属于该项目'], 422);
        }

        $flow = app(PurchaseFlowService::class);
        $tenderService = app(TenderService::class);
        $user = $request->user();

        $result = DB::transaction(function () use ($t, $bid, $user, $flow) {
            // 1) 中标 — bid & tender 状态
            $bid->status = 'awarded';
            $bid->save();
            $t->bids()->where('id', '!=', $bid->id)->update(['status' => 'rejected']);

            $t->awarded_bid_id      = $bid->id;
            $t->awarded_supplier_id = $bid->supplier_id;
            $t->awarded_at          = now();
            $t->status              = 'awarded';

            // 2) 自动落账: PO (让 PurchaseOrder booted hook 生成 PO{YYYYMMDD}{4位})
            //    path=bid 表明这条 PO 来自招标
            $po = PurchaseOrder::create([
                'supplier_id'    => $bid->supplier_id,
                'tender_id'      => $t->id,
                'title'          => "招标中标: {$t->name}",
                'total_amount'   => $bid->total_amount,
                'path'           => 'bid',
                'status'         => PurchaseFlowService::STATUS_ORDER_DRAFT,
                'created_by'     => $user->id,
            ]);
            $t->awarded_po_id = $po->id;

            // 3) 复制 bid.items → PurchaseItem (line items)
            $itemsCopied = 0;
            foreach ($bid->items()->get() as $bidItem) {
                PurchaseItem::create([
                    'purchase_order_id' => $po->id,
                    'item_name'         => $bidItem->name,
                    'specification'     => $bidItem->spec,
                    'quantity'          => $bidItem->quantity,
                    'unit'              => $bidItem->unit ?? '件',
                    'unit_price'        => $bidItem->unit_price,
                    'total_price'       => $bidItem->total_price,
                    'received_quantity' => 0,
                    'notes'             => null,
                ]);
                $itemsCopied++;
            }

            // 4) 自动建 Payable (firstOrCreate 幂等 — 重复 award 不会爆)
            $payable = Payable::firstOrCreate(
                ['po_id' => $po->id, 'supplier_id' => $bid->supplier_id],
                [
                    'amount'           => $bid->total_amount,
                    'paid_amount'      => 0,
                    'remaining_amount' => $bid->total_amount,
                    'due_date'         => now()->addDays(30)->toDateString(),
                    'payment_term'     => '月结30天',
                    'status'           => 'pending',
                    'ref_no'           => 'AP-' . date('Ymd') . '-' . str_pad($po->id, 4, '0', STR_PAD_LEFT),
                    'description'      => "招标中标应付款: {$t->name}",
                    'tender_id'        => $t->id,
                ]
            );

            // 5) 保存 tender (含 awarded_po_id)
            $t->save();

            // 6) 写审计 (purchase_status_logs) — 复用 PurchaseFlowService 的 log
            $flow->log('order', $po->id, null, $po->status, 'create_from_tender', $user, "从招标 #{$t->id} ({$t->code}) 中标生成, 复制 {$itemsCopied} 行物料");
            $flow->log('payable', $payable->id, null, $payable->status, 'create_from_tender', $user, "从 PO #{$po->id} 自动建应付 ¥{$payable->amount}");

            return [
                'po'           => $po->only(['id', 'code', 'po_no', 'total_amount', 'status']),
                'payable'      => $payable->only(['id', 'ref_no', 'amount', 'status']),
                'items_copied' => $itemsCopied,
            ];
        });

        // V0.6.5 Sprint 4: 联动保证金 — winner 留 paid (待合同后退)，其他自动 refund
        $depositResult = null;
        try {
            $tenderService->onTenderAward($t->id, $bid->supplier_id);
            $deposits = $tenderService->listDeposits($t->id);
            $depositResult = [
                'winner_deposit_status'    => optional($deposits->firstWhere('supplier_id', $bid->supplier_id))->status,
                'refunded_supplier_count'  => $deposits->where('status', 'refunded')->count(),
                'total_deposits'           => $deposits->count(),
            ];
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // 保证金联动失败不影响主流程
            $depositResult = ['error' => $e->getMessage()];
        }

        return response()->json([
            'code'    => 0,
            'message' => '定标成功, 已自动落账',
            'data'    => [
                'tender'   => $t->fresh(),
                'bid'      => $bid->fresh()->load('items'),
                'auto'     => $result,
                'deposits' => $depositResult,
            ],
        ]);
    }

    /**
     * V0.6.4 招标联动 — 联查下游 (PO + Payable + 入库单)
     * GET /api/tenders/{id}/downstream
     */
    public function downstream(int $id): JsonResponse
    {
        $t = TenderProject::with([
            'awardedPO' => function ($q) {
                $q->with(['items', 'payables' => function ($p) {
                    $p->with('payments');
                }]);
            },
            'purchaseOrders' => function ($q) {
                $q->with(['items', 'payables.payments']);
            },
        ])->findOrFail($id);

        // 关联的入库单 (通过 PO 反查: contract -> shipment -> stock_record)
        $poIds = $t->purchaseOrders->pluck('id')->all();
        $stockRecords = collect();
        if (!empty($poIds)) {
            $stockRecords = \DB::table('stock_records')
                ->where('related_type', 'purchase_shipment')
                ->whereIn('related_id', function ($sub) use ($poIds) {
                    $sub->select('id')->from('purchase_shipments')
                        ->whereIn('contract_id', function ($s2) use ($poIds) {
                            $s2->select('id')->from('purchase_contracts')
                                ->whereIn('purchase_order_id', $poIds);
                        });
                })
                ->orderByDesc('id')
                ->get(['id', 'record_no', 'type', 'quantity', 'related_id', 'related_type', 'operator_id', 'created_at']);
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'tender'        => $t->only(['id', 'code', 'name', 'status', 'awarded_at', 'awarded_bid_id', 'awarded_supplier_id', 'awarded_po_id']),
                'purchase_orders' => $t->purchaseOrders->map(function ($po) {
                    return [
                        'id'           => $po->id,
                        'code'         => $po->code,
                        'po_no'        => $po->po_no,
                        'status'       => $po->status,
                        'total_amount' => (float) $po->total_amount,
                        'title'        => $po->title,
                        'items_count'  => $po->items->count(),
                        'items'        => $po->items->map(fn ($i) => [
                            'id'        => $i->id,
                            'name'      => $i->item_name,
                            'spec'      => $i->specification,
                            'quantity'  => (float) $i->quantity,
                            'unit'      => $i->unit,
                            'unit_price'=> (float) $i->unit_price,
                            'total'     => (float) $i->total_price,
                        ]),
                        'payables' => $po->payables->map(fn ($p) => [
                            'id'           => $p->id,
                            'ref_no'       => $p->ref_no,
                            'amount'       => (float) $p->amount,
                            'paid_amount'  => (float) $p->paid_amount,
                            'remaining'    => (float) $p->remaining_amount,
                            'status'       => $p->status,
                            'due_date'     => $p->due_date?->toDateString(),
                            'paid_count'   => $p->payments->count(),
                        ]),
                    ];
                }),
                'stock_records' => $stockRecords->map(fn ($s) => [
                    'id'         => $s->id,
                    'record_no'  => $s->record_no,
                    'type'       => $s->type,
                    'quantity'   => $s->quantity,
                    'related_id' => $s->related_id,
                    'created_at' => $s->created_at,
                ]),
                'summary' => [
                    'has_po'             => $t->purchaseOrders->count() > 0,
                    'po_count'           => $t->purchaseOrders->count(),
                    'total_amount'       => (float) $t->purchaseOrders->sum('total_amount'),
                    'payable_total'      => (float) $t->purchaseOrders->flatMap->payables->sum('amount'),
                    'payable_paid'       => (float) $t->purchaseOrders->flatMap->payables->sum('paid_amount'),
                    'inbound_count'      => $stockRecords->count(),
                ],
            ],
        ]);
    }

    // 投标列表 (内部)
    public function bids(Request $request, int $id): JsonResponse
    {
        $t = TenderProject::findOrFail($id);
        $bids = $t->bids()
                   ->with(['supplier:id,name,code', 'items'])
                   ->orderByRaw('total_score DESC NULLS LAST, total_amount ASC')
                   ->get();
        return response()->json(['code' => 0, 'data' => $bids]);
    }

    // 内部代供应商提交投标 (调试/E2E 用)
    public function storeBid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'total_amount' => 'required|numeric|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
            'technical_proposal' => 'nullable|string|max:5000',
            'remark' => 'nullable|string|max:1000',
            'items'  => 'nullable|array',
            'items.*.name'       => 'required|string',
            'items.*.spec'       => 'nullable|string',
            'items.*.unit'       => 'nullable|string',
            'items.*.quantity'   => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'auto_submit' => 'sometimes|boolean',
        ]);
        $t = TenderProject::findOrFail($id);
        if (!in_array($t->status, [TenderProject::STATUS_OPEN, 'bidding', 'published'])) {
            return response()->json(['code' => 1001, 'message' => '该项目不在投标期'], 422);
        }
        // V0.6.5 Sprint 4: 保证金门控 — 没缴保证金不能投标
        $service = app(TenderService::class);
        $eligibility = $service->checkBidEligibility($t->id, $data['supplier_id']);
        if (!$eligibility['eligible']) {
            return response()->json([
                'code' => 1003,
                'message' => '保证金未缴, 不能投标: ' . $eligibility['reason'],
                'data' => ['deposit_required' => true, 'reason' => $eligibility['reason']],
            ], 422);
        }
        // 防止重复投标
        $exists = $t->bids()->where('supplier_id', $data['supplier_id'])->first();
        if ($exists) {
            return response()->json(['code' => 1002, 'message' => '该供应商已投标'], 422);
        }
        $bid = $t->bids()->create([
            'supplier_id'    => $data['supplier_id'],
            'total_amount'   => $data['total_amount'],
            'lead_time_days' => $data['lead_time_days'] ?? null,
            'technical_proposal' => $data['technical_proposal'] ?? null,
            'remark'         => $data['remark'] ?? null,
            'status'         => !empty($data['auto_submit']) ? 'submitted' : 'draft',
            'submitted_at'   => !empty($data['auto_submit']) ? now() : null,
            'code'           => 'BID-' . date('Ymd') . '-' . str_pad((string)(TenderBid::whereDate('created_at', today())->count() + 1), 3, '0', STR_PAD_LEFT),
        ]);
        if (!empty($data['items'])) {
            foreach ($data['items'] as $it) {
                $bid->items()->create([
                    'name'        => $it['name'],
                    'spec'        => $it['spec'] ?? null,
                    'unit'        => $it['unit'] ?? '件',
                    'quantity'    => $it['quantity'],
                    'unit_price'  => $it['unit_price'],
                    'total_price' => round($it['quantity'] * $it['unit_price'], 2),
                ]);
            }
        }
        return response()->json(['code' => 0, 'data' => $bid->load('items', 'supplier')], 201);
    }

    // 附件上传 (内部 — 招标文件)
    public function uploadAttachment(Request $request, int $id, FileUploadService $uploader): JsonResponse
    {
        $request->validate([
            // 合规 (audit-2026-06-28 C2): 加 mimes — 招标附件存到 public disk，无 mimes 等于公开 RCE
            'file'     => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip,rar,dwg',
            'category' => 'nullable|in:tender_doc,drawing,technical,qualification,other',
            'visibility' => 'nullable|in:public,eval_only',
        ]);
        $t = TenderProject::findOrFail($id);

        // 统一上传服务 (P1 重构): 自动 extension + 真实 MIME 双重校验 + SHA256
        $result = $uploader->store($request, 'file', [
            'disk'         => 'public',
            'subdir'       => "tenders/{$t->id}/" . date('Ymd'),
            'allowed_ext'  => ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','zip','rar','dwg'],
            'allowed_mime' => ['application/pdf','application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg','image/png','application/zip','application/x-rar-compressed',
                'application/acad','application/dwg','image/vnd.dwg','application/dxf'],
            'max_size'     => 51200,
        ]);

        $att  = TenderAttachment::create([
            'tender_project_id' => $t->id,
            'uploaded_by_user_id' => $request->user()->id,
            'file_name' => $result['original_name'],
            'file_path' => $result['path'],
            'mime_type' => $result['mime'],
            'file_size' => $result['size'],
            'category'  => $request->input('category', 'other'),
            'visibility' => $request->input('visibility', 'public'),
        ]);
        return response()->json(['code' => 0, 'data' => $att]);
    }

    public function listAttachments(int $id): JsonResponse
    {
        $list = TenderAttachment::where('tender_project_id', $id)
                                ->whereNull('tender_bid_id')
                                ->orderBy('id')->get();
        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function deleteAttachment(int $id, int $att): JsonResponse
    {
        $a = TenderAttachment::where('tender_project_id', $id)->findOrFail($att);
        \Storage::disk('public')->delete($a->file_path);
        $a->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    // ============================================================
    // V0.6.5 Sprint 4 — 状态机 + 审核 + 撤回 + 保证金 端点
    // ============================================================

    /** POST /api/tenders/{id}/submit-review  草稿→待审核 */
    public function submitReview(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        try {
            $t = app(TenderService::class)->submitReview($id, $data['note'] ?? null);
            return response()->json(['code' => 0, 'message' => '已提交审核', 'data' => $t]);
        } catch (\RuntimeException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    /** POST /api/tenders/{id}/approve  pending_review→open */
    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        try {
            $t = app(TenderService::class)->approve($id, $data['note'] ?? null);
            return response()->json(['code' => 0, 'message' => '审核通过, 已发布', 'data' => $t]);
        } catch (\RuntimeException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    /** POST /api/tenders/{id}/reject  pending_review→rejected|draft */
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason'        => 'required|string|max:1000',
            'back_to_draft' => 'sometimes|boolean',
        ]);
        try {
            $t = app(TenderService::class)->reject($id, $data['reason'], (bool)($data['back_to_draft'] ?? false));
            return response()->json(['code' => 0, 'message' => '已驳回', 'data' => $t]);
        } catch (\RuntimeException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    /** POST /api/tenders/{id}/withdraw  open→withdrawn (需 bid_count=0) */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        try {
            $t = app(TenderService::class)->withdraw($id, $data['reason']);
            return response()->json(['code' => 0, 'message' => '已撤回', 'data' => $t]);
        } catch (\RuntimeException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    /** POST /api/tenders/{id}/cancel-v2  任意非终态→cancelled */
    public function cancelV2(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        try {
            $t = app(TenderService::class)->cancel($id, $data['reason']);
            return response()->json(['code' => 0, 'message' => '已废标', 'data' => $t]);
        } catch (\RuntimeException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    /** GET /api/tenders/pending-review  审核队列 */
    public function pendingReview(): JsonResponse
    {
        $page = app(TenderService::class)->listPendingReview();
        return response()->json(['code' => 0, 'data' => ['items' => $page->items(), 'total' => $page->total()]]);
    }

    /** PUT /api/tenders/{id}/deposit-rule  设置/更新 保证金规则 */
    public function setDepositRule(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'required'                    => 'required|boolean',
            'amount'                      => 'required|numeric|min:0|max:99999999.99',
            'deadline_hours_before_open'  => 'nullable|integer|min:1|max:720',
            'refund_policy'               => 'nullable|array',
            'refund_policy.auto_refund_days'             => 'nullable|integer|min:1|max:90',
            'refund_policy.forfeit_on_no_contract_sign_days' => 'nullable|integer|min:1|max:90',
            'bank_account'                => 'nullable|string|max:200',
            'note'                        => 'nullable|string|max:1000',
        ]);
        try {
            $rule = app(TenderService::class)->setDepositRule($id, $data);
            return response()->json(['code' => 0, 'message' => '已设置保证金规则', 'data' => $rule]);
        } catch (\RuntimeException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    /** GET /api/tenders/{id}/deposits  列出所有保证金记录 */
    public function listDeposits(int $id): JsonResponse
    {
        $deposits = app(TenderService::class)->listDeposits($id);
        $rule = TenderDepositRule::where('tender_project_id', $id)->first();
        return response()->json(['code' => 0, 'data' => [
            'rule'     => $rule,
            'deposits' => $deposits,
            'summary'  => [
                'total'           => $deposits->count(),
                'paid'            => $deposits->where('status', 'paid')->count(),
                'pending'         => $deposits->where('status', 'pending')->count(),
                'refunded'        => $deposits->where('status', 'refunded')->count(),
                'forfeited'       => $deposits->where('status', 'forfeited')->count(),
                'partial_refund'  => $deposits->where('status', 'partial_refund')->count(),
                'total_paid_amt'  => (float) $deposits->where('status', 'paid')->sum('amount'),
                'total_refund_amt'=> (float) $deposits->whereIn('status', ['refunded', 'partial_refund'])->sum('refund_amount'),
            ],
        ]]);
    }

    /** POST /api/tenders/{id}/deposits  创建一条 pending 记录（邀请供应商时） */
    public function createDeposit(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'amount'      => 'nullable|numeric|min:0',
        ]);
        $rule = TenderDepositRule::where('tender_project_id', $id)->first();
        $amount = $data['amount'] ?? ($rule ? (float) $rule->amount : 0);
        $deposit = TenderDeposit::firstOrCreate(
            ['tender_project_id' => $id, 'supplier_id' => $data['supplier_id']],
            ['amount' => $amount, 'status' => TenderDeposit::STATUS_PENDING]
        );
        return response()->json(['code' => 0, 'message' => '已登记', 'data' => $deposit]);
    }

    /** POST /api/tenders/{id}/deposits/{depositId}/mark-paid  财务确认收款 */
    public function markDepositPaid(Request $request, int $id, int $depositId): JsonResponse
    {
        $data = $request->validate(['voucher_path' => 'nullable|string|max:500']);
        try {
            $d = app(TenderService::class)->markDepositPaid($depositId, $data['voucher_path'] ?? null);
            return response()->json(['code' => 0, 'message' => '已确认收款', 'data' => $d]);
        } catch (\RuntimeException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    /** POST /api/tenders/{id}/deposits/{depositId}/refund  退还保证金 */
    public function refundDeposit(Request $request, int $id, int $depositId): JsonResponse
    {
        $data = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01',
            'method'         => 'required|in:bank_transfer,cash,original_channel',
            'reason'         => 'required|string|max:500',
            'voucher_path'   => 'nullable|string|max:500',
        ]);
        try {
            $d = app(TenderService::class)->refundDeposit(
                $depositId,
                (float) $data['refund_amount'],
                $data['method'],
                $data['reason'],
                $data['voucher_path'] ?? null
            );
            return response()->json(['code' => 0, 'message' => '已退还', 'data' => $d]);
        } catch (\RuntimeException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    /** POST /api/tenders/{id}/deposits/{depositId}/forfeit  没收保证金 */
    public function forfeitDeposit(Request $request, int $id, int $depositId): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        try {
            $d = app(TenderService::class)->forfeitDeposit($depositId, $data['reason']);
            return response()->json(['code' => 0, 'message' => '已没收', 'data' => $d]);
        } catch (\RuntimeException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }
}
