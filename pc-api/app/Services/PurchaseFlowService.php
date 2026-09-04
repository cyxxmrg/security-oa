<?php

namespace App\Services;

use App\Concerns\GeneratesUniqueCode;
use App\Models\PurchaseRequirement;
use App\Models\PurchasePlan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseContract;
use App\Models\PurchasePaymentRequest;
use App\Models\PurchasePayment;
use App\Models\PurchaseShipment;
use App\Models\PurchaseContractFile;
use App\Models\PurchaseContractItem;
use App\Models\PurchasePaymentVoucher;
use App\Models\PurchaseShippingPlan;
use App\Models\Payable;
use App\Models\FinancePayment;
use App\Models\StockRecord;
use App\Models\InventoryItem;
use App\Models\ApprovalRecord;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\ExternalConstructionWork;
use App\Models\ExternalQuote;
use App\Models\Project;
use App\Models\TenderProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * V0.6.2 采购协同 8 步自动流转引擎
 *
 *   需求 → 询价/招标 → 采购单 → 合同 → 付款申请 → 财务付款 → 收货 → 入库
 *
 * 关键点:
 * 1. 每一步都自动创建/更新下一阶段的实体 (避免人工断链)
 * 2. 每步都打 purchase_status_logs (审计)
 * 3. 关键节点 (需求审批/PO 审批/付款审批) 自动提交到 approval_records_v2
 * 4. 收货后自动建 stock_records (type=in, related=purchase_shipment), 但需要采购员 confirmInbound
 */
class PurchaseFlowService
{
    use GeneratesUniqueCode;

    public const ENTITY_REQUIREMENT = 'requirement';
    public const ENTITY_PLAN = 'plan';
    public const ENTITY_ORDER = 'order';
    public const ENTITY_CONTRACT = 'contract';
    public const ENTITY_PAYMENT_REQ = 'payment_request';
    public const ENTITY_PAYMENT = 'payment';
    public const ENTITY_SHIPMENT = 'shipment';

    public const STATUS_REQ_PENDING    = 'pending';
    public const STATUS_REQ_APPROVED   = 'approved';
    public const STATUS_REQ_MERGED     = 'merged';
    public const STATUS_REQ_FULFILLED  = 'fulfilled';
    public const STATUS_REQ_REJECTED   = 'rejected';
    public const STATUS_REQ_CANCELLED  = 'cancelled';

    public const STATUS_PLAN_DRAFT     = 'draft';
    public const STATUS_PLAN_SUBMITTED = 'submitted';
    public const STATUS_PLAN_APPROVED  = 'approved';
    public const STATUS_PLAN_FULFILLED = 'fulfilled';
    public const STATUS_PLAN_REJECTED  = 'rejected';

    public const STATUS_ORDER_DRAFT    = 'draft';
    public const STATUS_ORDER_PENDING  = 'pending';
    public const STATUS_ORDER_APPROVED = 'approved';
    public const STATUS_ORDER_FULFILLED = 'fulfilled';
    public const STATUS_ORDER_REJECTED  = 'rejected';
    public const STATUS_ORDER_CANCELLED = 'cancelled';

    public const STATUS_CONTRACT_DRAFT    = 'draft';
    public const STATUS_CONTRACT_SIGNING  = 'signing';
    public const STATUS_CONTRACT_SIGNED   = 'signed';
    public const STATUS_CONTRACT_EFFECTIVE = 'effective';
    public const STATUS_CONTRACT_CANCELLED = 'cancelled';

    public const STATUS_PAYREQ_PENDING  = 'pending';
    public const STATUS_PAYREQ_APPROVED = 'approved';
    public const STATUS_PAYREQ_PAID     = 'paid';
    public const STATUS_PAYREQ_REJECTED = 'rejected';

    public const STATUS_PAY_PROCESSING = 'processing';
    public const STATUS_PAY_COMPLETED  = 'success';
    public const STATUS_PAY_FAILED     = 'failed';

    public const STATUS_SHIP_PENDING    = 'pending';
    public const STATUS_SHIP_SHIPPED    = 'shipped';
    public const STATUS_SHIP_IN_TRANSIT = 'in_transit';
    public const STATUS_SHIP_ARRIVED    = 'arrived';
    public const STATUS_SHIP_RECEIVED   = 'received';
    public const STATUS_SHIP_INSPECTED  = 'inspected';
    public const STATUS_SHIP_INBOUNDED  = 'inbounded';

    /**
     * 阶段 0: 任意来源创建采购需求
     * - work_order: 维修工单缺料
     * - external_work: 施工发包
     * - project: 项目物料
     * - stock_alert: 库存预警
     * - manual: 手工
     * - customer_contract: 客户合同条款 (设备代购)
     */
    public function createRequirement(array $data, ?User $user = null): PurchaseRequirement
    {
        return DB::transaction(function () use ($data, $user) {
            if (!$user) {
                throw new \DomainException('创建人不能为空');
            }
            if ((float) ($data['quantity'] ?? 0) <= 0) {
                throw new \DomainException('采购需求数量必须大于 0');
            }
            if (!empty($data['project_id'])) {
                Project::findOrFail((int) $data['project_id']);
            }
            $req = PurchaseRequirement::create([
                'name'        => $data['name'] ?? null,
                'project_id'  => $data['project_id'] ?? null,
                'inventory_item_id' => $data['inventory_item_id'] ?? null,
                'material'    => $data['material'],
                'spec'        => $data['spec'] ?? null,
                'spec_text'   => $data['spec_text'] ?? ($data['spec'] ?? null),
                'quantity'    => $data['quantity'],
                'unit'        => $data['unit'] ?? '件',
                'budget'      => $data['budget'] ?? null,
                'need_date'   => $data['need_date'] ?? null,
                'priority'    => $data['priority'] ?? 'medium',
                'status'      => self::STATUS_REQ_PENDING,
                'source_type' => $data['source_type'] ?? 'manual',
                'source_id'   => $data['source_id'] ?? null,
                'creator'     => $user->name,
                'created_by'  => $user->id,
                'remark'      => $data['remark'] ?? null,
            ]);
            $this->log(self::ENTITY_REQUIREMENT, $req->id, null, self::STATUS_REQ_PENDING, 'submit', $user, "从 {$req->source_type} 创建需求");
            $this->syncRequirementApprovalRecord($req, $user);
            return $req;
        });
    }

    /**
     * 阶段 1: 审批需求 → 走审批中心
     * 提交后, 状态变 pending; 审批通过 → approved (可继续走计划/询价/招标)
     */
    public function approveRequirement(int $reqId, ?User $user = null, string $remark = ''): PurchaseRequirement
    {
        return DB::transaction(function () use ($reqId, $user, $remark) {
            if (!$user) {
                throw new \DomainException('审批人不能为空');
            }
            $approval = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_requirement')
                ->whereJsonContains('payload->requirement_id', $reqId)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->lockForUpdate()
                ->firstOrFail();
            $flowService = app(ApprovalFlowService::class);
            $flowResult = $flowService->advanceFlow($approval, $user, $remark ?: '同意');
            $req = PurchaseRequirement::allData()->lockForUpdate()->findOrFail($reqId);
            if ($req->status !== self::STATUS_REQ_PENDING) {
                throw new \RuntimeException("需求当前状态 {$req->status} 不可审批");
            }
            $approval->forceFill([
                'flow' => $flowResult['flow'],
                'status' => $flowResult['status'],
                'current_approver_id' => $flowResult['current_approver_id'],
                'comment' => $remark ?: '同意',
            ])->save();

            if ($flowResult['status'] === ApprovalRecord::STATUS_APPROVED) {
                $this->syncApprovalBusinessState($approval, $user, self::STATUS_REQ_APPROVED, $remark);
            }
            return $req->fresh();
        });
    }

    /**
     * 阶段 2: 把需求聚合到计划 (多个 req → 1 plan)
     * 也可独立创建计划 (无 source)
     */
    public function createPlan(array $data, ?User $user = null, array $requirementIds = []): PurchasePlan
    {
        return DB::transaction(function () use ($data, $user, $requirementIds) {
            $requirements = collect();
            foreach (array_values(array_unique(array_map('intval', $requirementIds))) as $requirementId) {
                $requirement = PurchaseRequirement::lockForUpdate()->findOrFail($requirementId);
                if ($requirement->status !== self::STATUS_REQ_APPROVED) {
                    throw new \RuntimeException("需求 {$requirement->id} 当前状态 {$requirement->status} 不可加入采购计划");
                }
                $requirements->push($requirement);
            }

            $projectId = $data['project_id'] ?? null;
            if ($projectId) {
                Project::findOrFail($projectId);
            }
            $requirementProjectIds = $requirements->pluck('project_id')->filter()->unique()->values();
            if ($projectId && $requirementProjectIds->contains(fn ($id) => (int) $id !== (int) $projectId)) {
                throw new \RuntimeException('采购计划项目与需求项目不匹配');
            }
            if (!$projectId && $requirementProjectIds->count() > 1) {
                throw new \RuntimeException('同一采购计划不能合并多个项目的需求');
            }
            $projectId ??= $requirementProjectIds->first();

            $plan = PurchasePlan::create([
                'requirement_id' => $requirements->first()?->id,
                'project_id'     => $projectId,
                'title'          => $data['title'],
                'total_amount'   => $data['total_amount'] ?? 0,
                'plan_date'      => $data['plan_date'] ?? today(),
                'priority'       => $data['priority'] ?? 'medium',
                'status'         => self::STATUS_PLAN_DRAFT,
                'submitter_id'   => $user?->id,
                'created_by'     => $user?->id,
                'remark'         => $data['remark'] ?? null,
            ]);
            // 关联多个需求 (用 merge_plan_id)
            foreach ($requirements as $requirement) {
                    $requirement->update([
                        'status'        => self::STATUS_REQ_MERGED,
                        'merged_plan_id'=> $plan->id,
                        'merged_at'     => now(),
                    ]);
            }
            $this->log(self::ENTITY_PLAN, $plan->id, null, self::STATUS_PLAN_DRAFT, 'create', $user, "聚合 {$requirements->count()} 个需求");
            return $plan;
        });
    }

    public function submitPlan(int $planId, ?User $user = null): PurchasePlan
    {
        return DB::transaction(function () use ($planId, $user) {
            if (!$user) {
                throw new \DomainException('提交人不能为空');
            }
            $plan = PurchasePlan::lockForUpdate()->findOrFail($planId);
            if ($plan->status !== self::STATUS_PLAN_DRAFT) {
                throw new \RuntimeException("计划当前状态 {$plan->status} 不可提交");
            }
            $plan->update([
                'status'       => self::STATUS_PLAN_SUBMITTED,
                'submitter_id' => $user->id,
                'submitted_at' => now(),
            ]);
            $this->log(self::ENTITY_PLAN, $plan->id, self::STATUS_PLAN_DRAFT, self::STATUS_PLAN_SUBMITTED, 'submit', $user);

            $template = app(ApprovalFlowService::class)->resolveTemplate('purchase_plan', 'operation');
            if (!$template) {
                throw new \DomainException('未找到采购计划的启用审批流程模板');
            }
            $flowService = app(ApprovalFlowService::class);
            $flowData = $flowService->initFlow($template, $user, '提交采购计划审批');
            ApprovalRecord::create([
                'code' => $this->nextCode('PLAN-AP'),
                'type' => 'operation',
                'sub_type' => 'purchase_plan',
                'title' => '[采购计划] ' . ($plan->code ?? '#' . $plan->id) . ' 审批 (¥' . number_format($plan->total_amount ?? 0, 2) . ')',
                'priority' => 'normal',
                'status' => ApprovalRecord::STATUS_PENDING,
                'amount' => $plan->total_amount ?? 0,
                'applicant_id' => $user->id,
                'current_approver_id' => $flowData['current_approver_id'],
                'payload' => [
                    'plan_id' => $plan->id,
                    'plan_no' => $plan->code,
                    'title' => $plan->title,
                    'total_amount' => $plan->total_amount,
                    'project_id' => $plan->project_id,
                    '_approval_flow' => $flowData['definition'],
                ],
                'flow' => $flowData['flow'],
            ]);
            return $plan->fresh();
        });
    }

    public function approvePlan(int $planId, ?User $user = null, string $remark = ''): PurchasePlan
    {
        return DB::transaction(function () use ($planId, $user, $remark) {
            if (!$user) {
                throw new \DomainException('审批人不能为空');
            }
            $approval = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_plan')
                ->whereJsonContains('payload->plan_id', $planId)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->lockForUpdate()
                ->firstOrFail();
            $flowService = app(ApprovalFlowService::class);
            $flowResult = $flowService->advanceFlow($approval, $user, $remark ?: '同意');
            $plan = PurchasePlan::allData()->lockForUpdate()->findOrFail($planId);
            if ($plan->status !== self::STATUS_PLAN_SUBMITTED) {
                throw new \RuntimeException("计划当前状态 {$plan->status} 不可审批");
            }
            $approval->forceFill([
                'flow' => $flowResult['flow'],
                'status' => $flowResult['status'],
                'current_approver_id' => $flowResult['current_approver_id'],
                'comment' => $remark ?: '同意',
            ])->save();
            if ($flowResult['status'] === ApprovalRecord::STATUS_APPROVED) {
                $this->syncApprovalBusinessState($approval, $user, self::STATUS_PLAN_APPROVED, $remark);
            }
            return $plan->fresh();
        });
    }

    /**
     * 阶段 3: 计划 → 询价 OR 招标 OR 手工直采
     * decision:
     *   'quote'  → 走 external_quote_requests 询价 (小额)
     *   'bid'    → 走 tender_projects 招标 (大额, V0.6.0 已实现, 这里只回写 plan_id)
     *   'manual' → 直接转 PO
     */
    public function planToOrder(int $planId, array $data, string $path = 'manual', ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($planId, $data, $path, $user) {
            $plan = PurchasePlan::lockForUpdate()->findOrFail($planId);
            if ($plan->status !== self::STATUS_PLAN_APPROVED) {
                throw new \RuntimeException("计划当前状态 {$plan->status} 不可生成采购单");
            }
            $totalAmount = (float) $data['total_amount'];
            if ($totalAmount <= 0 || ((float) $plan->total_amount > 0 && $totalAmount - (float) $plan->total_amount > 0.0001)) {
                throw new \RuntimeException('采购单金额超过采购计划金额');
            }
            $supplierId = (int) $data['supplier_id'];
            $tenderId = $data['tender_id'] ?? null;
            $quoteId = $data['quote_id'] ?? null;

            if ($path === 'manual' && ($tenderId || $quoteId)) {
                throw new \RuntimeException('手工采购路径不能绑定招标或报价来源');
            }

            if ($path === 'quote') {
                if (!$quoteId) {
                    throw new \RuntimeException('询价路径必须提供报价单');
                }
                $quote = ExternalQuote::with('request')->lockForUpdate()->findOrFail((int) $quoteId);
                if ($quote->status !== ExternalQuote::STATUS_AWARDED
                    || $quote->request?->status !== \App\Models\ExternalQuoteRequest::STATUS_AWARDED
                    || (int) $quote->request?->awarded_quote_id !== (int) $quote->id
                    || (int) $quote->supplier_id !== $supplierId
                    || ($plan->project_id && $quote->request?->project_id && (int) $plan->project_id !== (int) $quote->request->project_id)
                    || abs($totalAmount - (float) $quote->total_amount) > 0.0001) {
                    throw new \RuntimeException('报价单未定标或与供应商不匹配');
                }
            }

            if ($path === 'bid') {
                if (!$tenderId) {
                    throw new \RuntimeException('招标路径必须提供招标项目');
                }
                $tender = TenderProject::with('awardedBid')->lockForUpdate()->findOrFail((int) $tenderId);
                if (!in_array($tender->status, [TenderProject::STATUS_CLOSED, 'awarded'], true)
                    || !$tender->awarded_bid_id
                    || (int) $tender->awarded_supplier_id !== $supplierId
                    || ($plan->project_id && $tender->project_id && (int) $plan->project_id !== (int) $tender->project_id)
                    || !$tender->awardedBid
                    || abs($totalAmount - (float) $tender->awardedBid->total_amount) > 0.0001) {
                    throw new \RuntimeException('招标项目未定标或与计划/供应商不匹配');
                }
            }

            $po = PurchaseOrder::create([
                'plan_id'              => $plan->id,
                'source_requirement_id'=> $plan->requirement_id,
                'project_id'           => $plan->project_id,
                'supplier_id'          => $supplierId,
                'po_no'                => $data['po_no'] ?? null,
                'code'                 => $data['code'] ?? null,
                'title'                => $data['title'] ?? $plan->title,
                'total_amount'         => $totalAmount,
                'tender_id'            => $tenderId,
                'path'                 => $path,
                'quote_id'             => $quoteId,
                'status'               => self::STATUS_ORDER_DRAFT,
                'created_by'           => $user?->id,
                'notes'                => $data['notes'] ?? null,
            ]);
            $this->log(self::ENTITY_ORDER, $po->id, null, self::STATUS_ORDER_DRAFT, 'create', $user, "从计划 {$plan->code} 经 {$path} 路径生成");
            return $po;
        });
    }

    /**
     * 阶段 3 续: PO 提交审批 → 走审批中心
     */
    public function submitOrder(int $orderId, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $user) {
            if (!$user) {
                throw new \DomainException('提交人不能为空');
            }
            $po = PurchaseOrder::lockForUpdate()->findOrFail($orderId);
            if ($po->status !== self::STATUS_ORDER_DRAFT) {
                throw new \RuntimeException("采购单当前状态 {$po->status} 不可提交");
            }
            $po->update(['status' => self::STATUS_ORDER_PENDING]);
            $this->log(self::ENTITY_ORDER, $po->id, self::STATUS_ORDER_DRAFT, self::STATUS_ORDER_PENDING, 'submit', $user);

            // 提交到审批中心 (type=operation, sub_type=purchase_order)
            try {
                $payload = [
                    'purchase_order_id' => $po->id,
                    'supplier_id'       => $po->supplier_id,
                    'total_amount'      => $po->total_amount,
                    'tender_id'         => $po->tender_id,
                    'path'              => $po->path,
                ];
                $exists = ApprovalRecord::where('type', 'operation')
                    ->where('sub_type', 'purchase_order')
                    ->where('status', 'pending')
                    ->whereJsonContains('payload->purchase_order_id', $po->id)
                    ->exists();
                if (!$exists) {
                    $flowService = app(ApprovalFlowService::class);
                    $template = $flowService->resolveTemplate('purchase_order', 'operation');
                    if (!$template) {
                        throw new \DomainException('未找到采购订单的启用审批流程模板');
                    }
                    $flowData = $flowService->initFlow($template, $user, '提交采购订单审批');
                    ApprovalRecord::create([
                        'code'         => $this->nextCode('PO-AP'),
                        'type'         => 'operation',
                        'sub_type'     => 'purchase_order',
                        'title'        => "[PO] {$po->po_no} 采购单审批 (¥{$po->total_amount})",
                        'priority'     => 'normal',
                        'status'       => ApprovalRecord::STATUS_PENDING,
                        'amount'       => $po->total_amount,
                        'applicant_id' => $user->id,
                        'current_approver_id' => $flowData['current_approver_id'],
                        'payload'      => array_merge($payload, ['_approval_flow' => $flowData['definition']]),
                        'flow'         => $flowData['flow'],
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::error('PO submit -> approval failed: ' . $e->getMessage());
                throw $e;
            }
            return $po->fresh();
        });
    }

    public function approveOrder(int $orderId, ?User $user = null, string $remark = ''): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $user, $remark) {
            if (!$user) {
                throw new \DomainException('审批人不能为空');
            }
            $approval = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_order')
                ->whereJsonContains('payload->purchase_order_id', $orderId)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->lockForUpdate()
                ->firstOrFail();
            $flowService = app(ApprovalFlowService::class);
            $flowResult = $flowService->advanceFlow($approval, $user, $remark ?: '同意');
            $po = PurchaseOrder::allData()->lockForUpdate()->findOrFail($orderId);
            if ($po->status !== self::STATUS_ORDER_PENDING) {
                throw new \RuntimeException("采购单当前状态 {$po->status} 不可审批");
            }
            $approval->forceFill([
                'flow' => $flowResult['flow'],
                'status' => $flowResult['status'],
                'current_approver_id' => $flowResult['current_approver_id'],
                'comment' => $remark ?: '同意',
            ])->save();
            if ($flowResult['status'] !== ApprovalRecord::STATUS_APPROVED) {
                return $po->fresh();
            }

            $this->syncApprovalBusinessState($approval, $user, self::STATUS_ORDER_APPROVED, $remark);
            return $po->fresh();
        });
    }

    /**
     * 阶段 4: PO → 合同
     * 同时回填 contract_id 冗余字段
     */
    public function createContract(int $orderId, array $data, ?User $user = null): PurchaseContract
    {
        return DB::transaction(function () use ($orderId, $data, $user) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($orderId);
            if ($po->status !== self::STATUS_ORDER_APPROVED) {
                throw new \RuntimeException("采购单当前状态 {$po->status} 不可生成合同");
            }
            if (array_key_exists('total_amount', $data)
                && abs((float) $data['total_amount'] - (float) $po->total_amount) > 0.0001) {
                throw new \RuntimeException('合同金额必须与采购单金额一致');
            }
            $c = PurchaseContract::create([
                'plan_id'           => $po->plan_id,
                'purchase_order_id' => $po->id,
                'project_id'        => $po->project_id,
                'supplier_id'       => $po->supplier_id,
                'title'             => $data['title'] ?? $po->title,
                'total_amount'      => $data['total_amount'] ?? $po->total_amount,
                'signed_at'         => $data['signed_at'] ?? null,
                'start_date'        => $data['start_date'] ?? null,
                'end_date'          => $data['end_date'] ?? null,
                'payment_terms'     => $data['payment_terms'] ?? null,
                'payment_plan'      => $data['payment_plan'] ?? null,
                'delivery_address'  => $data['delivery_address'] ?? null,
                'status'            => self::STATUS_CONTRACT_DRAFT,
                'signer'            => $user?->name,
                'signer_id'         => $user?->id,
                'remark'            => $data['remark'] ?? null,
            ]);
            // 回填 PO 冗余
            $po->update(['contract_id' => $c->id]);
            $this->log(self::ENTITY_CONTRACT, $c->id, null, self::STATUS_CONTRACT_DRAFT, 'create', $user, "从 PO {$po->po_no} 起草");
            // V0.6.2.2: 自动同步合同清单 (从 PO.items)
            $this->autoSyncContractItems($c, $user);
            return $c;
        });
    }

    public function signContract(int $contractId, ?User $user = null): PurchaseContract
    {
        return DB::transaction(function () use ($contractId, $user) {
            $c = PurchaseContract::lockForUpdate()->findOrFail($contractId);
            if (!in_array($c->status, [self::STATUS_CONTRACT_DRAFT, self::STATUS_CONTRACT_SIGNING], true)) {
                throw new \RuntimeException("合同当前状态 {$c->status} 不可签署");
            }
            $fromStatus = $c->status;
            $c->update([
                'status'   => self::STATUS_CONTRACT_SIGNED,
                'signer'   => $user?->name ?? $c->signer,
                'signer_id'=> $user?->id ?? $c->signer_id,
                'signed_at'=> $c->signed_at ?? today(),
            ]);
            $this->log(self::ENTITY_CONTRACT, $c->id, $fromStatus, self::STATUS_CONTRACT_SIGNED, 'sign', $user);
            return $c->fresh();
        });
    }

    /**
     * 阶段 5: 合同 → 付款申请
     * 按 payment_plan 自动分阶段生成
     */
    public function createPaymentRequest(int $contractId, array $data, ?User $user = null): PurchasePaymentRequest
    {
        return DB::transaction(function () use ($contractId, $data, $user) {
            if (!$user) {
                throw new \DomainException('申请人不能为空');
            }
            $c = PurchaseContract::lockForUpdate()->findOrFail($contractId);
            if (!in_array($c->status, [self::STATUS_CONTRACT_SIGNED, self::STATUS_CONTRACT_EFFECTIVE], true)) {
                throw new \RuntimeException("合同当前状态 {$c->status} 不可申请付款");
            }
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new \RuntimeException('付款申请金额必须大于 0');
            }
            $existingAmount = (float) PurchasePaymentRequest::where('contract_id', $c->id)
                ->whereIn('status', [
                    self::STATUS_PAYREQ_PENDING,
                    self::STATUS_PAYREQ_APPROVED,
                    self::STATUS_PAYREQ_PAID,
                ])
                ->sum('amount');
            if ((float) $c->total_amount > 0 && $existingAmount + $amount - (float) $c->total_amount > 0.0001) {
                throw new \RuntimeException('付款申请金额超过合同未申请金额');
            }
            $req = PurchasePaymentRequest::create([
                'contract_id'  => $c->id,
                'supplier_id'  => $c->supplier_id,
                'amount'       => $amount,
                'payment_type' => $data['payment_type'] ?? 'full',
                'stage_label'  => $data['stage_label'] ?? null,
                'request_date' => $data['request_date'] ?? today(),
                'status'       => self::STATUS_PAYREQ_PENDING,
                'applicant'    => $user?->name,
                'applicant_id' => $user->id,
                'reason'       => $data['reason'] ?? null,
                'payable_id'   => $c->purchaseOrder?->id ? Payable::allData()->where('po_id', $c->purchase_order_id)->value('id') : null,
            ]);
            $this->log(self::ENTITY_PAYMENT_REQ, $req->id, null, self::STATUS_PAYREQ_PENDING, 'submit', $user, $req->stage_label ? "[{$req->stage_label}] 付款申请" : '付款申请');
            $this->syncPaymentRequestApprovalRecord($req, $user);
            return $req;
        });
    }

    public function approvePaymentRequest(int $reqId, ?User $user = null, string $remark = ''): PurchasePaymentRequest
    {
        return DB::transaction(function () use ($reqId, $user, $remark) {
            if (!$user) {
                throw new \DomainException('审批人不能为空');
            }
            $approval = ApprovalRecord::where('type', 'finance')
                ->where('sub_type', 'purchase_payment')
                ->whereJsonContains('payload->payment_request_id', $reqId)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->lockForUpdate()
                ->firstOrFail();
            $flowResult = app(ApprovalFlowService::class)->advanceFlow($approval, $user, $remark ?: '同意');
            $req = PurchasePaymentRequest::allData()->lockForUpdate()->findOrFail($reqId);
            if ($req->status !== self::STATUS_PAYREQ_PENDING) {
                throw new \RuntimeException('只有待审批的付款申请可以审批');
            }
            $approval->forceFill([
                'flow' => $flowResult['flow'],
                'status' => $flowResult['status'],
                'current_approver_id' => $flowResult['current_approver_id'],
                'comment' => $remark ?: '同意',
            ])->save();
            if ($flowResult['status'] === ApprovalRecord::STATUS_APPROVED) {
                $this->syncApprovalBusinessState($approval, $user, self::STATUS_PAYREQ_APPROVED, $remark);
            }
            return $req->fresh();
        });
    }

    public function syncApprovalBusinessState(ApprovalRecord $approval, User $user, string $status, string $comment = ''): void
    {
        $payload = is_array($approval->payload) ? $approval->payload : [];
        $subType = (string) $approval->sub_type;

        if ($subType === 'purchase_requirement' && !empty($payload['requirement_id'])) {
            $requirement = PurchaseRequirement::allData()->lockForUpdate()->findOrFail((int) $payload['requirement_id']);
            if ($requirement->status !== self::STATUS_REQ_PENDING) {
                throw new \RuntimeException("需求当前状态 {$requirement->status} 不可更新为 {$status}");
            }
            $requirement->update([
                'status'        => $status,
                'reviewed_by'   => $user->id,
                'reviewed_at'   => now(),
                'review_remark' => $comment,
            ]);
            $this->log(self::ENTITY_REQUIREMENT, $requirement->id, self::STATUS_REQ_PENDING, $status, $status === self::STATUS_REQ_APPROVED ? 'approve' : 'reject', $user, $comment);
            return;
        }

        if ($subType === 'purchase_plan' && !empty($payload['plan_id'])) {
            $plan = PurchasePlan::allData()->lockForUpdate()->findOrFail((int) $payload['plan_id']);
            if ($plan->status !== self::STATUS_PLAN_SUBMITTED) {
                throw new \RuntimeException("计划当前状态 {$plan->status} 不可更新为 {$status}");
            }
            $plan->update([
                'status'         => $status,
                'approver_id'    => $user->id,
                'approved_at'    => now(),
                'approve_remark' => $comment,
            ]);
            $this->log(self::ENTITY_PLAN, $plan->id, self::STATUS_PLAN_SUBMITTED, $status, $status === self::STATUS_PLAN_APPROVED ? 'approve' : 'reject', $user, $comment);
            return;
        }

        if ($subType === 'purchase_order' && !empty($payload['purchase_order_id'])) {
            $order = PurchaseOrder::allData()->lockForUpdate()->findOrFail((int) $payload['purchase_order_id']);
            if ($order->status !== self::STATUS_ORDER_PENDING) {
                throw new \RuntimeException("采购单当前状态 {$order->status} 不可更新为 {$status}");
            }
            $order->update([
                'status'      => $status,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            $this->log(self::ENTITY_ORDER, $order->id, self::STATUS_ORDER_PENDING, $status, $status === self::STATUS_ORDER_APPROVED ? 'approve' : 'reject', $user, $comment);
            if ($status === self::STATUS_ORDER_APPROVED) {
                Payable::allData()->firstOrCreate(
                    ['po_id' => $order->id, 'supplier_id' => $order->supplier_id],
                    [
                        'project_id'       => $order->project_id,
                        'amount'           => $order->total_amount,
                        'paid_amount'      => 0,
                        'remaining_amount' => $order->total_amount,
                        'due_date'         => today()->addDays(30),
                        'payment_term'     => '月结30天',
                        'status'           => 'pending',
                        'ref_no'           => 'AP-' . date('Ymd') . '-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                        'description'      => "采购单 {$order->po_no} 应付",
                        'tender_id'        => $order->tender_id,
                    ]
                );
            }
            return;
        }

        if ($subType === 'purchase_payment' && !empty($payload['payment_request_id'])) {
            $request = PurchasePaymentRequest::allData()->lockForUpdate()->findOrFail((int) $payload['payment_request_id']);
            if ($request->status !== self::STATUS_PAYREQ_PENDING) {
                throw new \RuntimeException("付款申请当前状态 {$request->status} 不可更新为 {$status}");
            }
            $request->update([
                'status'         => $status,
                'approver_id'    => $user->id,
                'approved_at'    => now(),
                'approve_remark' => $comment,
            ]);
            $this->log(self::ENTITY_PAYMENT_REQ, $request->id, self::STATUS_PAYREQ_PENDING, $status, $status === self::STATUS_PAYREQ_APPROVED ? 'approve' : 'reject', $user, $comment);
        }
    }

    /**
     * 阶段 6: 财务付款 — 写实付 + 更新 payable
     */
    public function executePayment(int $reqId, array $data, ?User $user = null): PurchasePayment
    {
        return DB::transaction(function () use ($reqId, $data, $user) {
            $req = PurchasePaymentRequest::allData()->with('payments')->lockForUpdate()->findOrFail($reqId);
            if ($req->status !== self::STATUS_PAYREQ_APPROVED) {
                throw new \RuntimeException('付款申请未审批或已付款，不能执行付款');
            }

            $existingPaid = (float) PurchasePayment::where('payment_request_id', $req->id)
                ->where('status', self::STATUS_PAY_COMPLETED)
                ->sum('amount');
            $amount = isset($data['amount']) ? (float) $data['amount'] : (float) $req->amount;
            $remainingRequestAmount = (float) $req->amount - $existingPaid;
            if ($amount <= 0) {
                throw new \RuntimeException('付款金额必须大于0');
            }
            if ($amount - $remainingRequestAmount > 0.0001) {
                throw new \RuntimeException('付款金额超过该申请剩余可付金额');
            }

            $payable = null;
            if ($req->payable_id) {
            $payable = Payable::allData()->lockForUpdate()->find($req->payable_id);
                if ($payable && (float) $payable->remaining_amount <= 0.0001 && (float) $payable->paid_amount <= 0.0001 && (float) $payable->amount > 0) {
                    $payable->update([
                        'remaining_amount' => $payable->amount,
                        'status' => 'pending',
                    ]);
                    $payable->refresh();
                }
                if ($payable && $amount - (float) $payable->remaining_amount > 0.0001) {
                    throw new \RuntimeException('付款金额超过应付账款剩余金额');
                }
            }

            $pay = PurchasePayment::create([
                'payment_request_id' => $req->id,
                'contract_id'        => $req->contract_id,
                'supplier_id'        => $req->supplier_id,
                'amount'             => $amount,
                'payment_method'     => $data['payment_method'] ?? 'transfer',
                'paid_at'            => $data['paid_at'] ?? today(),
                'voucher_no'         => $data['voucher_no'] ?? null,
                'operator'           => $user?->name,
                'operator_id'        => $user?->id,
                'status'             => self::STATUS_PAY_COMPLETED,
                'remark'             => $data['remark'] ?? null,
            ]);

            $requestWasPaid = $req->status === self::STATUS_PAYREQ_PAID;
            if ($amount >= $remainingRequestAmount - 0.0001) {
                $req->update(['status' => self::STATUS_PAYREQ_PAID]);
            }
            $this->log(self::ENTITY_PAYMENT, $pay->id, null, self::STATUS_PAY_COMPLETED, 'execute', $user, "实付 ¥{$pay->amount}");
            if (!$requestWasPaid && $req->status === self::STATUS_PAYREQ_PAID) {
                $this->log(self::ENTITY_PAYMENT_REQ, $req->id, self::STATUS_PAYREQ_APPROVED, self::STATUS_PAYREQ_PAID, 'paid', $user);
            }

            if ($payable) {
                $newPaid = (float) $payable->paid_amount + (float) $pay->amount;
                $newRemaining = (float) $payable->amount - $newPaid;
                $payable->update([
                    'paid_amount'       => $newPaid,
                    'remaining_amount'  => max(0, $newRemaining),
                    'paid_date'         => $pay->paid_at,
                    'status'            => $newRemaining <= 0.0001 ? 'fully_paid' : 'partial',
                ]);
                FinancePayment::create([
                    'payable_id'   => $payable->id,
                    'amount'       => $pay->amount,
                    'payment_date' => $pay->paid_at,
                    'method'       => $pay->payment_method,
                    'voucher_no'   => $pay->voucher_no,
                    'operator'     => $pay->operator,
                    'remark'       => $pay->remark ?: "采购付款 {$pay->code}",
                ]);
            }
            return $pay;
        });
    }

    /**
     * 阶段 7: 合同 → 收货 (供应商发货)
     * 状态: pending → shipped → in_transit → arrived
     */
    public function createShipment(int $contractId, array $data, ?User $user = null): PurchaseShipment
    {
        return DB::transaction(function () use ($contractId, $data, $user) {
            $c = PurchaseContract::lockForUpdate()->findOrFail($contractId);
            if (!in_array($c->status, [self::STATUS_CONTRACT_SIGNED, self::STATUS_CONTRACT_EFFECTIVE], true)) {
                throw new \RuntimeException("合同当前状态 {$c->status} 不可创建收货单");
            }
            $sh = PurchaseShipment::create([
                'contract_id'        => $c->id,
                'supplier_id'        => $c->supplier_id,
                'shipped_at'         => $data['shipped_at'] ?? today(),
                'expected_arrival_at'=> $data['expected_arrival_at'] ?? null,
                'carrier'            => $data['carrier'] ?? null,
                'tracking_no'        => $data['tracking_no'] ?? null,
                'status'             => self::STATUS_SHIP_SHIPPED,
                'consignee'          => $data['consignee'] ?? $user?->name,
                'remark'             => $data['remark'] ?? null,
            ]);
            $this->log(self::ENTITY_SHIPMENT, $sh->id, null, self::STATUS_SHIP_SHIPPED, 'ship', $user, "承运商: {$sh->carrier}, 单号 {$sh->tracking_no}");
            return $sh;
        });
    }

    public function updateShipmentStatus(int $shipId, string $newStatus, ?User $user = null, string $remark = ''): PurchaseShipment
    {
        return DB::transaction(function () use ($shipId, $newStatus, $user, $remark) {
            $sh = PurchaseShipment::lockForUpdate()->findOrFail($shipId);
            $old = $sh->status;
            if ($old === $newStatus) {
                return $sh->fresh();
            }
            $allowed = [
                self::STATUS_SHIP_PENDING    => [self::STATUS_SHIP_SHIPPED],
                self::STATUS_SHIP_SHIPPED    => [self::STATUS_SHIP_IN_TRANSIT, self::STATUS_SHIP_ARRIVED],
                self::STATUS_SHIP_IN_TRANSIT => [self::STATUS_SHIP_ARRIVED],
                self::STATUS_SHIP_ARRIVED    => [self::STATUS_SHIP_RECEIVED],
                self::STATUS_SHIP_RECEIVED   => [self::STATUS_SHIP_INSPECTED],
                self::STATUS_SHIP_INSPECTED  => [self::STATUS_SHIP_INBOUNDED],
                self::STATUS_SHIP_INBOUNDED  => [],
            ];
            if (!in_array($newStatus, $allowed[$old] ?? [], true)) {
                throw new \RuntimeException("收货单状态 {$old} 不可变更为 {$newStatus}");
            }
            $update = ['status' => $newStatus];
            if ($newStatus === self::STATUS_SHIP_ARRIVED) {
                $update['arrived_at'] = today();
            }
            $sh->update($update);
            $this->log(self::ENTITY_SHIPMENT, $sh->id, $old, $newStatus, $newStatus, $user, $remark);
            return $sh->fresh();
        });
    }

    /**
     * 阶段 8: 收货 → 自动建入库单 (stock_records type=in)
     * 但需要采购员 confirmInbound 才最终入库
     */
    public function autoCreateInbound(int $shipId, ?User $user = null): StockRecord
    {
        return DB::transaction(function () use ($shipId, $user) {
            $sh = PurchaseShipment::with('contract.itemsList')->lockForUpdate()->findOrFail($shipId);
            if (!in_array($sh->status, [self::STATUS_SHIP_ARRIVED, self::STATUS_SHIP_RECEIVED], true)) {
                throw new \RuntimeException("收货单当前状态 {$sh->status} 不可生成入库流水");
            }
            if ($sh->stock_record_id) {
                return StockRecord::allData()->findOrFail($sh->stock_record_id);
            }

            $existing = StockRecord::allData()->where('related_type', 'purchase_shipment')
                ->where('related_id', $sh->id)
                ->orderBy('id')
                ->first();
            if ($existing) {
                $sh->update(['stock_record_id' => $existing->id, 'status' => self::STATUS_SHIP_INSPECTED]);
                return $existing;
            }

            $items = $sh->contract?->itemsList ?? collect();
            if ($items->isEmpty()) {
                throw new \RuntimeException('合同没有物料清单，无法生成入库单');
            }

            $firstRecord = null;
            $lastRecord = null;
            foreach ($items as $index => $contractItem) {
                if (!$contractItem->inventory_item_id) {
                    throw new \RuntimeException("合同物料「{$contractItem->material}」未关联库存物资，无法入库");
                }
                $inventoryItem = InventoryItem::lockForUpdate()->findOrFail($contractItem->inventory_item_id);
                $warehouseId = $inventoryItem->warehouse_id ?: \DB::table('warehouses')->orderBy('id')->value('id');
                if (!$warehouseId) {
                    throw new \RuntimeException('没有可用仓库，无法生成采购入库流水');
                }
                $quantity = max(1, (int) round((float) $contractItem->qty));
                $inventoryItem->increment('current_stock', $quantity);
                $inventoryItem->refresh();
                $lastRecord = StockRecord::create([
                    'record_no'         => 'INB-' . date('Ymd') . '-' . str_pad($sh->id, 4, '0', STR_PAD_LEFT) . '-' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                    'inventory_item_id' => $inventoryItem->id,
                    'warehouse_id'      => $warehouseId,
                    'type'              => 'in',
                    'quantity'          => $quantity,
                    'remaining_stock'   => (int) $inventoryItem->current_stock,
                    'related_id'        => $sh->id,
                    'related_type'      => 'purchase_shipment',
                    'party_type'        => 'supplier',
                    'party_id'          => $sh->supplier_id,
                    'operator_id'       => $user?->id,
                    'remark'            => "采购到货 {$sh->code} / {$contractItem->material}",
                ]);
                $firstRecord ??= $lastRecord;
            }
            $sh->update(['stock_record_id' => $firstRecord->id, 'status' => self::STATUS_SHIP_INSPECTED]);
            $this->log(self::ENTITY_SHIPMENT, $sh->id, $sh->getOriginal('status'), self::STATUS_SHIP_INSPECTED, 'auto_inbound', $user, "自动生成入库流水 {$firstRecord->record_no}");
            return $firstRecord;
        });
    }

    /**
     * 采购员确认入库 → 状态变 inbounded
     */
    public function confirmInbound(int $shipId, ?User $user = null): PurchaseShipment
    {
        return DB::transaction(function () use ($shipId, $user) {
            $sh = PurchaseShipment::lockForUpdate()->findOrFail($shipId);
            if ($sh->inbound_confirmed) {
                return $sh->fresh();
            }
            if (!$sh->stock_record_id) {
                $this->autoCreateInbound($shipId, $user);
                $sh->refresh();
            }
            $sh->update([
                'status'                => self::STATUS_SHIP_INBOUNDED,
                'inbound_confirmed'     => true,
                'inbound_confirmed_by'  => $user?->id,
                'inbound_confirmed_at'  => now(),
            ]);
            $this->log(self::ENTITY_SHIPMENT, $sh->id, self::STATUS_SHIP_INSPECTED, self::STATUS_SHIP_INBOUNDED, 'confirm_inbound', $user, '采购员确认入库');

            $contract = $sh->contract ? PurchaseContract::lockForUpdate()->find($sh->contract->id) : null;
            if ($contract && $contract->plan_id) {
                $reqIds = \DB::table('purchase_requirements')->where('merged_plan_id', $contract->plan_id)->pluck('id');
                PurchaseRequirement::allData()->whereIn('id', $reqIds)->lockForUpdate()->get()->each->update(['status' => self::STATUS_REQ_FULFILLED]);
                PurchasePlan::allData()->lockForUpdate()->where('id', $contract->plan_id)->update(['status' => self::STATUS_PLAN_FULFILLED]);
                PurchaseOrder::allData()->lockForUpdate()->where('id', $contract->purchase_order_id)->update(['status' => self::STATUS_ORDER_FULFILLED]);
            }
            return $sh->fresh();
        });
    }

    /**
     * 阶段 N: 撤回/取消任意实体 (业务方主动撤回)
     * 适用: 需求/计划/PO/合同/付款申请/付款/收货
     * 规则:
     *   - 仅 pending/submitted/draft 状态可撤回
     *   - 已 approved/fulfilled/signed/paid 的不可撤回 (用 cancel 流程)
     */
    public function cancel(string $entityType, int $entityId, ?User $user = null, string $remark = '业务方撤回'): array
    {
        return DB::transaction(function () use ($entityType, $entityId, $user, $remark) {
            $model = match ($entityType) {
                self::ENTITY_REQUIREMENT => PurchaseRequirement::lockForUpdate()->findOrFail($entityId),
                self::ENTITY_PLAN         => PurchasePlan::lockForUpdate()->findOrFail($entityId),
                self::ENTITY_ORDER        => PurchaseOrder::lockForUpdate()->findOrFail($entityId),
                self::ENTITY_CONTRACT     => PurchaseContract::lockForUpdate()->findOrFail($entityId),
                self::ENTITY_PAYMENT_REQ  => PurchasePaymentRequest::lockForUpdate()->findOrFail($entityId),
                self::ENTITY_SHIPMENT     => PurchaseShipment::lockForUpdate()->findOrFail($entityId),
                default => throw new \InvalidArgumentException("不支持的 entity_type: $entityType"),
            };

            $from = $model->status;
            $cancellable = ['pending', 'submitted', 'draft', 'shipped', 'in_transit'];
            if (!in_array($from, $cancellable, true)) {
                throw new \RuntimeException("当前状态 {$from} 不可撤回, 仅 pending/submitted/draft/shipped/in_transit 可撤回");
            }

            $model->update(['status' => 'cancelled']);
            $this->cancelApproval($entityType, $entityId, $user, $remark);
            $this->log($entityType, $entityId, $from, 'cancelled', 'cancel', $user, $remark);
            return ['cancelled' => true, 'entity_type' => $entityType, 'entity_id' => $entityId, 'from' => $from];
        });
    }

    /**
     * 状态机日志写入
     */
    public function log(string $entityType, int $entityId, ?string $from, string $to, string $action, ?User $user = null, ?string $remark = null, array $payload = []): void
    {
        \DB::table('purchase_status_logs')->insert([
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'from_status'   => $from,
            'to_status'     => $to,
            'action'        => $action,
            'operator_id'   => $user?->id,
            'operator_name' => $user?->name,
            'remark'        => $remark,
            'payload'       => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'created_at'    => now(),
        ]);
    }

    // ==================== V0.6.2.2 合同附件/清单/付款凭证/发货计划 ====================

    /**
     * 同步合同清单 (从 PO.line_items)
     * 规则: 仅当清单为空时才同步 (首次创建), 避免覆盖用户已编辑的内容
     */
    public function syncContractItems(int $contractId, ?User $user = null): array
    {
        return DB::transaction(function () use ($contractId, $user) {
            $contract = PurchaseContract::with('purchaseOrder.items')->lockForUpdate()->findOrFail($contractId);
            $this->assertContractEditable($contract);
            $existing = PurchaseContractItem::where('contract_id', $contractId)->count();
            if ($existing > 0) {
                return ['skipped' => true, 'reason' => '清单已存在, 未同步', 'count' => $existing];
            }
            $po = $contract->purchaseOrder;
            if (!$po) {
                return ['skipped' => true, 'reason' => '合同未关联 PO', 'count' => 0];
            }
            $items = $po->items ?? collect();
            $created = 0;
            foreach ($items as $it) {
                PurchaseContractItem::create([
                    'contract_id' => $contractId,
                    'material'    => $it->item_name ?? $it->material ?? '(未命名)',
                    'spec'        => $it->specification ?? $it->spec ?? null,
                    'qty'         => (float)($it->quantity ?? $it->qty ?? 0),
                    'unit'        => $it->unit ?? '件',
                    'unit_price'  => (float)($it->unit_price ?? 0),
                    'subtotal'    => (float)($it->total_price ?? ((float)($it->quantity ?? 0) * (float)($it->unit_price ?? 0))),
                    'remark'      => $it->notes ?? $it->remark ?? null,
                ]);
                $created++;
            }
            $this->log(self::ENTITY_CONTRACT, $contractId, null, 'sync_items', 'sync_items', $user, "从 PO#{$po->id} 同步 {$created} 行清单");
            return ['skipped' => false, 'count' => $created, 'contract_id' => $contractId];
        });
    }

    public function addContractItem(int $contractId, array $data, ?User $user = null): PurchaseContractItem
    {
        return DB::transaction(function () use ($contractId, $data, $user) {
            $contract = PurchaseContract::lockForUpdate()->findOrFail($contractId);
            $this->assertContractEditable($contract);
            $qty = (float)($data['qty'] ?? 0);
            $unitPrice = (float)($data['unit_price'] ?? 0);
            if ($qty <= 0) {
                throw new \RuntimeException('合同清单数量必须大于 0');
            }
            $item = PurchaseContractItem::create([
                'contract_id' => $contractId,
                'inventory_item_id' => $data['inventory_item_id'] ?? null,
                'material'    => $data['material'],
                'spec'        => $data['spec'] ?? null,
                'qty'         => $qty,
                'unit'        => $data['unit'] ?? '件',
                'unit_price'  => $unitPrice,
                'subtotal'    => $qty * $unitPrice,
                'remark'      => $data['remark'] ?? null,
            ]);
            $this->log(self::ENTITY_CONTRACT, $contractId, null, 'add_item', 'add_item', $user, "新增清单: {$item->material} x {$item->qty} {$item->unit}");
            return $item;
        });
    }

    public function updateContractItem(int $contractId, int $itemId, array $data, ?User $user = null): PurchaseContractItem
    {
        return DB::transaction(function () use ($contractId, $itemId, $data, $user) {
            $contract = PurchaseContract::lockForUpdate()->findOrFail($contractId);
            $this->assertContractEditable($contract);
            $item = PurchaseContractItem::where('contract_id', $contractId)->where('id', $itemId)->lockForUpdate()->firstOrFail();
            $qty = isset($data['qty']) ? (float)$data['qty'] : (float)$item->qty;
            $unitPrice = isset($data['unit_price']) ? (float)$data['unit_price'] : (float)$item->unit_price;
            if ($qty <= 0) {
                throw new \RuntimeException('合同清单数量必须大于 0');
            }
            $item->update([
                'inventory_item_id' => $data['inventory_item_id'] ?? $item->inventory_item_id,
                'material'   => $data['material'] ?? $item->material,
                'spec'       => array_key_exists('spec', $data) ? $data['spec'] : $item->spec,
                'qty'        => $qty,
                'unit'       => $data['unit'] ?? $item->unit,
                'unit_price' => $unitPrice,
                'subtotal'   => $qty * $unitPrice,
                'remark'     => array_key_exists('remark', $data) ? $data['remark'] : $item->remark,
            ]);
            $this->log(self::ENTITY_CONTRACT, $contractId, null, 'update_item', 'update_item', $user, "修改清单: {$item->material}, 单价 ¥{$unitPrice}");
            return $item->fresh();
        });
    }

    public function removeContractItem(int $contractId, int $itemId, ?User $user = null): void
    {
        DB::transaction(function () use ($contractId, $itemId, $user) {
            $contract = PurchaseContract::lockForUpdate()->findOrFail($contractId);
            $this->assertContractEditable($contract);
            $item = PurchaseContractItem::where('contract_id', $contractId)->where('id', $itemId)->lockForUpdate()->firstOrFail();
            $label = $item->material;
            $item->delete();
            $this->log(self::ENTITY_CONTRACT, $contractId, null, 'remove_item', 'remove_item', $user, "删除清单: {$label}");
        });
    }

    /** 上传合同文件 */
    public function uploadContractFile(int $contractId, \Illuminate\Http\UploadedFile $file, ?User $user = null): PurchaseContractFile
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($contractId, $file, $user, &$storedPath) {
                $contract = PurchaseContract::lockForUpdate()->findOrFail($contractId);
                $this->assertContractEditable($contract);

                $result = $this->storePurchaseFile($file, "purchase/contracts/{$contractId}", [
                    'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png',
                ], [
                    'application/pdf', 'image/jpeg', 'image/png',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ], 20480);
                $storedPath = $result['path'];

                $record = PurchaseContractFile::create([
                    'contract_id' => $contractId,
                    'file_path'   => $result['path'],
                    'file_name'   => $result['original_name'],
                    'mime'        => $result['mime'],
                    'size'        => $result['size'],
                    'uploaded_by' => $user?->id,
                    'uploaded_at' => now(),
                ]);
                $this->log(self::ENTITY_CONTRACT, $contractId, null, 'upload_file', 'upload_file', $user, "上传附件: {$record->file_name} (" . round($record->size / 1024, 1) . " KB)");
                return $record;
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                Storage::disk('attachments')->delete($storedPath);
            }
            throw $e;
        }
    }

    public function listContractFiles(int $contractId): array
    {
        PurchaseContract::findOrFail($contractId);
        $rows = PurchaseContractFile::where('contract_id', $contractId)
            ->orderBy('uploaded_at', 'desc')->get();
        return $rows->map(function ($f) {
            return [
                'id'        => $f->id,
                'name'      => $f->file_name,
                'url'       => "/api/purchase-flow/contracts/{$f->contract_id}/files/{$f->id}/download",
                'size'      => $f->size,
                'size_human'=> $f->size >= 1048576 ? round($f->size / 1048576, 2) . ' MB' : round($f->size / 1024, 1) . ' KB',
                'mime'      => $f->mime,
                'uploaded_at' => $f->uploaded_at?->toDateTimeString(),
            ];
        })->toArray();
    }

    public function deleteContractFile(int $contractId, int $fileId, ?User $user = null): void
    {
        DB::transaction(function () use ($contractId, $fileId, $user) {
            $contract = PurchaseContract::lockForUpdate()->findOrFail($contractId);
            $this->assertContractEditable($contract);
            $f = PurchaseContractFile::where('contract_id', $contractId)->where('id', $fileId)->lockForUpdate()->firstOrFail();
            $label = $f->file_name;
            $f->delete();
            Storage::disk('attachments')->delete($f->file_path);
            Storage::disk('public')->delete($f->file_path);
            $this->log(self::ENTITY_CONTRACT, $contractId, null, 'delete_file', 'delete_file', $user, "删除附件: {$label}");
        });
    }

    /** 上传付款凭证 */
    public function uploadPaymentVoucher(int $paymentRequestId, \Illuminate\Http\UploadedFile $file, ?User $user = null, ?string $remark = null): PurchasePaymentVoucher
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($paymentRequestId, $file, $user, $remark, &$storedPath) {
                $pr = PurchasePaymentRequest::lockForUpdate()->findOrFail($paymentRequestId);
                if (!in_array($pr->status, [self::STATUS_PAYREQ_APPROVED, self::STATUS_PAYREQ_PAID], true)) {
                    throw new \RuntimeException('只有已审批或已付款的申请可以上传付款凭证');
                }
                PurchaseContract::findOrFail($pr->contract_id);

                $result = $this->storePurchaseFile($file, "purchase/vouchers/{$paymentRequestId}", [
                    'pdf', 'jpg', 'jpeg', 'png',
                ], ['application/pdf', 'image/jpeg', 'image/png'], 20480);
                $storedPath = $result['path'];

                $record = PurchasePaymentVoucher::create([
                    'payment_request_id' => $paymentRequestId,
                    'file_path'   => $result['path'],
                    'file_name'   => $result['original_name'],
                    'mime'        => $result['mime'],
                    'size'        => $result['size'],
                    'uploaded_by' => $user?->id,
                    'uploaded_at' => now(),
                    'remark'      => $remark,
                ]);
                $this->log(self::ENTITY_PAYMENT_REQ, $paymentRequestId, null, 'upload_voucher', 'upload_voucher', $user, "上传凭证: {$record->file_name}");
                return $record;
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                Storage::disk('attachments')->delete($storedPath);
            }
            throw $e;
        }
    }

    public function listPaymentVouchers(int $paymentRequestId): array
    {
        $paymentRequest = PurchasePaymentRequest::findOrFail($paymentRequestId);
        PurchaseContract::findOrFail($paymentRequest->contract_id);
        $rows = PurchasePaymentVoucher::where('payment_request_id', $paymentRequestId)
            ->orderBy('uploaded_at', 'desc')->get();
        return $rows->map(function ($f) {
            return [
                'id'        => $f->id,
                'name'      => $f->file_name,
                'url'       => "/api/purchase-flow/payment-requests/{$f->payment_request_id}/vouchers/{$f->id}/download",
                'size'      => $f->size,
                'size_human'=> $f->size >= 1048576 ? round($f->size / 1048576, 2) . ' MB' : round($f->size / 1024, 1) . ' KB',
                'mime'      => $f->mime,
                'remark'    => $f->remark,
                'uploaded_at' => $f->uploaded_at?->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * 设置发货预期 (按合同清单行拆分, item 可空 = 整单)
     */
    public function setShippingPlan(int $contractId, array $data, ?User $user = null): PurchaseShippingPlan
    {
        return DB::transaction(function () use ($contractId, $data, $user) {
            $contract = PurchaseContract::lockForUpdate()->findOrFail($contractId);
            if (!in_array($contract->status, [self::STATUS_CONTRACT_SIGNED, self::STATUS_CONTRACT_EFFECTIVE], true)) {
                throw new \RuntimeException("合同当前状态 {$contract->status} 不可设置发货计划");
            }
            $itemId = $data['contract_item_id'] ?? null;
            if ($itemId) {
                PurchaseContractItem::where('contract_id', $contractId)->where('id', $itemId)->firstOrFail();
            }
            $plan = PurchaseShippingPlan::create([
                'contract_id'      => $contractId,
                'contract_item_id' => $itemId,
                'expected_at'      => $data['expected_at'] ?? null,
                'carrier'          => $data['carrier'] ?? null,
                'tracking_no'      => $data['tracking_no'] ?? null,
                'shipped_at'       => $data['shipped_at'] ?? null,
                'status'           => $data['status'] ?? 'planned',
                'remark'           => $data['remark'] ?? null,
            ]);
            $this->log(self::ENTITY_CONTRACT, $contractId, null, 'shipping_plan', 'shipping_plan', $user, "发货预期: " . ($itemId ? "清单行 #{$itemId} " : "整单 ") . ($data['expected_at'] ?? ''));
            return $plan;
        });
    }

    /**
     * 添加快递单号 (合同 item 可空 = 整单)
     */
    public function addTracking(int $contractId, array $data, ?User $user = null): PurchaseShippingPlan
    {
        return DB::transaction(function () use ($contractId, $data, $user) {
            $itemId = $data['contract_item_id'] ?? null;
            $data['shipped_at'] = $data['shipped_at'] ?? today();
            $data['status'] = $data['status'] ?? 'shipped';
            return $this->setShippingPlan($contractId, $data, $user);
        });
    }

    public function listShipping(int $contractId): array
    {
        PurchaseContract::findOrFail($contractId);
        $rows = PurchaseShippingPlan::with('contractItem')
            ->where('contract_id', $contractId)
            ->orderBy('expected_at')
            ->orderBy('created_at')
            ->get();
        return $rows->map(function ($p) {
            return [
                'id'               => $p->id,
                'contract_item_id' => $p->contract_item_id,
                'item_label'       => $p->contractItem ? ($p->contractItem->material . (($p->contractItem->spec ?? '') ? ' / ' . $p->contractItem->spec : '')) : '整单',
                'expected_at'      => $p->expected_at?->toDateString(),
                'shipped_at'       => $p->shipped_at?->toDateString(),
                'carrier'          => $p->carrier,
                'tracking_no'      => $p->tracking_no,
                'status'           => $p->status,
                'remark'           => $p->remark,
                'created_at'       => $p->created_at?->toDateTimeString(),
            ];
        })->toArray();
    }

    // ==================== 兼容 V0.6.2.2 合同自动同步清单 (在 createContract 后调用) ====================

    /**
     * 创建合同后自动同步清单 (钩子)
     * 规则: 合同创建成功 → 自动从 PO.items 复制 (无 PO 跳过)
     */
    private function autoSyncContractItems(PurchaseContract $contract, ?User $user = null): void
    {
        try {
            $this->syncContractItems($contract->id, $user);
        } catch (\Throwable $e) {
            \Log::warning('autoSyncContractItems failed for contract #' . $contract->id . ': ' . $e->getMessage());
        }
    }

    /**
     * 取整条链路 (从任意实体出发, 找上下游)
     */
    public function trace(string $entityType, int $entityId): array
    {
        // 兼容前端 entityType 传值 (单/复数都可)
        $entityType = match ($entityType) {
            'requirements', 'requirement' => self::ENTITY_REQUIREMENT,
            'plans', 'plan' => self::ENTITY_PLAN,
            'orders', 'pos', 'order', 'po' => self::ENTITY_ORDER,
            'contracts', 'contract' => self::ENTITY_CONTRACT,
            'payment_requests', 'payment_request' => self::ENTITY_PAYMENT_REQ,
            'payments', 'payment' => self::ENTITY_PAYMENT,
            'shipments', 'shipment' => self::ENTITY_SHIPMENT,
            'inbounds', 'inbound' => 'inbound',
            default => $entityType,
        };

        // 找到根需求 (沿 plan/po/contract/payment_req/payment/shipment 反查)
        $rootReqId = null;
        switch ($entityType) {
            case self::ENTITY_REQUIREMENT:
                $rootReqId = PurchaseRequirement::whereKey($entityId)->value('id');
                break;
            case self::ENTITY_PLAN:
                $rootReqId = PurchasePlan::whereKey($entityId)->value('requirement_id');
                if (!$rootReqId) {
                    $rootReqId = PurchaseRequirement::where('merged_plan_id', $entityId)->value('id');
                }
                break;
            case self::ENTITY_ORDER:
                $po = PurchaseOrder::find($entityId);
                $rootReqId = $po?->source_requirement_id ?? PurchasePlan::whereKey($po?->plan_id)->value('requirement_id');
                break;
            case self::ENTITY_CONTRACT:
                $c = PurchaseContract::find($entityId);
                $rootReqId = $c?->plan?->requirement_id ?? PurchasePlan::whereKey($c?->plan_id)->value('requirement_id');
                break;
            case self::ENTITY_PAYMENT_REQ:
                $paymentRequest = PurchasePaymentRequest::find($entityId);
                $contract = $paymentRequest ? PurchaseContract::find($paymentRequest->contract_id) : null;
                $rootReqId = $contract?->plan?->requirement_id ?? PurchasePlan::whereKey($contract?->plan_id)->value('requirement_id');
                break;
            case self::ENTITY_PAYMENT:
                $payment = PurchasePayment::find($entityId);
                $paymentRequest = $payment ? PurchasePaymentRequest::find($payment->payment_request_id) : null;
                $contract = $paymentRequest ? PurchaseContract::find($paymentRequest->contract_id) : null;
                $rootReqId = $contract?->plan?->requirement_id ?? PurchasePlan::whereKey($contract?->plan_id)->value('requirement_id');
                break;
            case self::ENTITY_SHIPMENT:
                $shipment = PurchaseShipment::find($entityId);
                $contract = $shipment ? PurchaseContract::find($shipment->contract_id) : null;
                $rootReqId = $contract?->plan?->requirement_id ?? PurchasePlan::whereKey($contract?->plan_id)->value('requirement_id');
                break;
        }
        $rootReqId = $rootReqId ?: 0;

        // 找所有 plan (同时按 requirement_id 和 merged_plan_id)
        $mergedPlanId = PurchaseRequirement::where('id', $rootReqId)->value('merged_plan_id');
        $plans = PurchasePlan::where('requirement_id', $rootReqId)
            ->orWhere('id', $mergedPlanId)
            ->get();
        $planIds = $plans->pluck('id')->toArray();
        if ($mergedPlanId && !in_array($mergedPlanId, $planIds)) $planIds[] = $mergedPlanId;

        $pos = PurchaseOrder::whereIn('plan_id', $planIds)->orWhere('source_requirement_id', $rootReqId)->get();
        $poIds = $pos->pluck('id')->toArray();

        $contracts = PurchaseContract::whereIn('purchase_order_id', $poIds)->get();
        $contractIds = $contracts->pluck('id')->toArray();

        $payReqs = PurchasePaymentRequest::whereIn('contract_id', $contractIds)->get();
        $payReqIds = $payReqs->pluck('id')->toArray();

        $pays = PurchasePayment::whereIn('payment_request_id', $payReqIds)->get();
        $shipments = PurchaseShipment::whereIn('contract_id', $contractIds)->get();

        // 找日志 (按 entity_type 分组, 避免跨链混入)
        $allIds = array_merge(
            [['type' => self::ENTITY_REQUIREMENT, 'id' => $rootReqId]],
            array_map(fn($id) => ['type' => self::ENTITY_PLAN, 'id' => $id], $planIds),
            array_map(fn($id) => ['type' => self::ENTITY_ORDER, 'id' => $id], $poIds),
            array_map(fn($id) => ['type' => self::ENTITY_CONTRACT, 'id' => $id], $contractIds),
            array_map(fn($id) => ['type' => self::ENTITY_PAYMENT_REQ, 'id' => $id], $payReqIds),
        );
        $payIds = $pays->pluck('id')->toArray();
        $shipIds = $shipments->pluck('id')->toArray();
        $allIds = array_merge(
            $allIds,
            array_map(fn($id) => ['type' => self::ENTITY_PAYMENT, 'id' => $id], $payIds),
            array_map(fn($id) => ['type' => self::ENTITY_SHIPMENT, 'id' => $id], $shipIds),
        );

        $logs = collect();
        foreach ($allIds as $pair) {
            $r = \DB::table('purchase_status_logs')
                ->where('entity_type', $pair['type'])
                ->where('entity_id', $pair['id'])
                ->orderBy('created_at')
                ->get();
            $logs = $logs->merge($r);
        }
        $logs = $logs->sortBy('created_at')->values();

        return [
            'requirement'  => $rootReqId ? PurchaseRequirement::find($rootReqId) : null,
            'plans'        => $plans,
            'orders'       => $pos,
            'contracts'    => $contracts,
            'payment_reqs' => $payReqs,
            'payments'     => $pays,
            'shipments'    => $shipments,
            'logs'         => $logs,
        ];
    }


    public function syncRequirementApprovalRecord(PurchaseRequirement $requirement, ?User $user = null): void
    {
        if (!$user) {
            throw new \DomainException('申请人不能为空');
        }
        $exists = ApprovalRecord::where('type', 'operation')
            ->where('sub_type', 'purchase_requirement')
            ->where('payload->requirement_id', $requirement->id)
            ->exists();
        if ($exists) {
            return;
        }
        $flowService = app(ApprovalFlowService::class);
        $template = $flowService->resolveTemplate('purchase_requirement', 'operation');
        if (!$template) {
            throw new \DomainException('未找到采购需求的启用审批流程模板');
        }
        $flowData = $flowService->initFlow($template, $user, '提交采购需求审批');
        ApprovalRecord::create([
            'code'                => $this->nextCode('REQ-AP'),
            'type'                => 'operation',
            'sub_type'            => 'purchase_requirement',
            'title'               => "[采购需求] {$requirement->code} {$requirement->material} x {$requirement->quantity}{$requirement->unit}",
            'priority'            => match ($requirement->priority) {
                'urgent' => 'high',
                'high' => 'high',
                'low' => 'low',
                default => 'normal',
            },
            'status'              => ApprovalRecord::STATUS_PENDING,
            'amount'              => $requirement->budget,
            'applicant_id'        => $user->id,
            'current_approver_id' => $flowData['current_approver_id'],
            'payload'             => [
                'requirement_id' => $requirement->id,
                'requirement_code' => $requirement->code,
                'project_id' => $requirement->project_id,
                'inventory_item_id' => $requirement->inventory_item_id,
                'material' => $requirement->material,
                'spec' => $requirement->spec,
                'quantity' => (float) $requirement->quantity,
                'unit' => $requirement->unit,
                'need_date' => $requirement->need_date?->format('Y-m-d'),
                'remark' => $requirement->remark,
                '_approval_flow' => $flowData['definition'],
            ],
            'flow'         => $flowData['flow'],
        ]);
    }

    private function syncPaymentRequestApprovalRecord(PurchasePaymentRequest $request, User $user): void
    {
        $exists = ApprovalRecord::where('type', 'finance')
            ->where('sub_type', 'purchase_payment')
            ->where('payload->payment_request_id', $request->id)
            ->exists();
        if ($exists) {
            return;
        }

        $flowService = app(ApprovalFlowService::class);
        $template = $flowService->resolveTemplate('purchase_payment', 'finance');
        if (!$template) {
            throw new \DomainException('未找到采购付款申请的启用审批流程模板');
        }
        $flowData = $flowService->initFlow($template, $user, '提交付款审批');
        ApprovalRecord::create([
            'code' => $this->nextCode('PAY-AP'),
            'type' => 'finance',
            'sub_type' => 'purchase_payment',
            'title' => "[付款] {$request->code} ¥{$request->amount} 财务审批",
            'priority' => 'high',
            'status' => ApprovalRecord::STATUS_PENDING,
            'amount' => $request->amount,
            'applicant_id' => $user->id,
            'current_approver_id' => $flowData['current_approver_id'],
            'payload' => [
                'payment_request_id' => $request->id,
                'contract_id' => $request->contract_id,
                'supplier_id' => $request->supplier_id,
                '_approval_flow' => $flowData['definition'],
            ],
            'flow' => $flowData['flow'],
        ]);
    }

    private function cancelApproval(string $entityType, int $entityId, ?User $user, string $remark): void
    {
        $target = match ($entityType) {
            self::ENTITY_REQUIREMENT => ['operation', 'purchase_requirement', 'requirement_id'],
            self::ENTITY_PLAN => ['operation', 'purchase_plan', 'plan_id'],
            self::ENTITY_ORDER => ['operation', 'purchase_order', 'purchase_order_id'],
            self::ENTITY_PAYMENT_REQ => ['finance', 'purchase_payment', 'payment_request_id'],
            default => null,
        };
        if ($target === null) {
            return;
        }

        $approval = ApprovalRecord::where('type', $target[0])
            ->where('sub_type', $target[1])
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->whereJsonContains("payload->{$target[2]}", $entityId)
            ->lockForUpdate()
            ->first();
        if (!$approval) {
            return;
        }

        $flow = is_array($approval->flow) ? $approval->flow : [];
        $flow[] = [
            'operator_id' => $user?->id,
            'operator' => $user?->name ?? '系统',
            'action' => 'cancel',
            'time' => now()->toDateTimeString(),
            'comment' => $remark,
        ];
        $approval->forceFill([
            'status' => ApprovalRecord::STATUS_CANCELLED,
            'current_approver_id' => null,
            'comment' => $remark,
            'flow' => $flow,
        ])->save();
    }

    private function nextCode(string $prefix): string
    {
        return self::uniqueCode($prefix);
    }

    private function storePurchaseFile(\Illuminate\Http\UploadedFile $file, string $subdir, array $allowedExt, array $allowedMime, int $maxSize): array
    {
        $fakeReq = \Illuminate\Http\Request::create('/', 'POST', [], [], ['file' => $file]);
        return app(FileUploadService::class)->store($fakeReq, 'file', [
            'disk'         => 'attachments',
            'subdir'       => $subdir,
            'allowed_ext'  => $allowedExt,
            'allowed_mime' => $allowedMime,
            'max_size'     => $maxSize,
        ]);
    }

    private function assertContractEditable(PurchaseContract $contract): void
    {
        if (!in_array($contract->status, [self::STATUS_CONTRACT_DRAFT, self::STATUS_CONTRACT_SIGNING], true)) {
            throw new \RuntimeException("合同当前状态 {$contract->status} 不可修改清单");
        }
    }
}
