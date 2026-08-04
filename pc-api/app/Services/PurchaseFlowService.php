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
use App\Models\Project;
use Illuminate\Support\Facades\DB;
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
                'creator'     => $user?->name ?? ($data['creator'] ?? null),
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
            $req = PurchaseRequirement::findOrFail($reqId);
            if ($req->status !== self::STATUS_REQ_PENDING) {
                throw new \RuntimeException("需求当前状态 {$req->status} 不可审批");
            }
            $req->update([
                'status'        => self::STATUS_REQ_APPROVED,
                'reviewed_by'   => $user?->id,
                'reviewed_at'   => now(),
                'review_remark' => $remark,
            ]);
            $this->log(self::ENTITY_REQUIREMENT, $req->id, self::STATUS_REQ_PENDING, self::STATUS_REQ_APPROVED, 'approve', $user, $remark);
            $this->syncRequirementApprovalStatus($req, $user, ApprovalRecord::STATUS_APPROVED, $remark);
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
            $plan = PurchasePlan::create([
                'requirement_id' => $requirementIds[0] ?? null,
                'project_id'     => $data['project_id'] ?? null,
                'title'          => $data['title'],
                'total_amount'   => $data['total_amount'] ?? 0,
                'plan_date'      => $data['plan_date'] ?? today(),
                'priority'       => $data['priority'] ?? 'medium',
                'status'         => self::STATUS_PLAN_DRAFT,
                'submitter_id'   => $user?->id,
                'remark'         => $data['remark'] ?? null,
            ]);
            // 关联多个需求 (用 merge_plan_id)
            if (!empty($requirementIds)) {
                foreach ($requirementIds as $rid) {
                    PurchaseRequirement::where('id', $rid)->update([
                        'status'        => self::STATUS_REQ_MERGED,
                        'merged_plan_id'=> $plan->id,
                        'merged_at'     => now(),
                    ]);
                }
            }
            $this->log(self::ENTITY_PLAN, $plan->id, null, self::STATUS_PLAN_DRAFT, 'create', $user, "聚合 " . count($requirementIds) . " 个需求");
            return $plan;
        });
    }

    public function submitPlan(int $planId, ?User $user = null): PurchasePlan
    {
        return DB::transaction(function () use ($planId, $user) {
            $plan = PurchasePlan::findOrFail($planId);
            $plan->update([
                'status'       => self::STATUS_PLAN_SUBMITTED,
                'submitter_id' => $user?->id ?? $plan->submitter_id,
                'submitted_at' => now(),
            ]);
            $this->log(self::ENTITY_PLAN, $plan->id, self::STATUS_PLAN_DRAFT, self::STATUS_PLAN_SUBMITTED, 'submit', $user);
            return $plan->fresh();
        });
    }

    public function approvePlan(int $planId, ?User $user = null, string $remark = ''): PurchasePlan
    {
        return DB::transaction(function () use ($planId, $user, $remark) {
            $plan = PurchasePlan::findOrFail($planId);
            $plan->update([
                'status'         => self::STATUS_PLAN_APPROVED,
                'approver_id'    => $user?->id,
                'approved_at'    => now(),
                'approve_remark' => $remark,
            ]);
            $this->log(self::ENTITY_PLAN, $plan->id, self::STATUS_PLAN_SUBMITTED, self::STATUS_PLAN_APPROVED, 'approve', $user, $remark);
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
            $plan = PurchasePlan::findOrFail($planId);
            $po = PurchaseOrder::create([
                'plan_id'              => $plan->id,
                'source_requirement_id'=> $plan->requirement_id,
                'project_id'           => $plan->project_id,
                'supplier_id'          => $data['supplier_id'],
                'po_no'                => $data['po_no'] ?? null,
                'code'                 => $data['code'] ?? null,
                'title'                => $data['title'] ?? $plan->title,
                'total_amount'         => $data['total_amount'],
                'tender_id'            => $data['tender_id'] ?? null,
                'path'                 => $path,
                'quote_id'             => $data['quote_id'] ?? null,
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
            $po = PurchaseOrder::findOrFail($orderId);
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
                    ApprovalRecord::create([
                        'code'         => $this->nextCode('PO-AP'),
                        'type'         => 'operation',
                        'sub_type'     => 'purchase_order',
                        'title'        => "[PO] {$po->po_no} 采购单审批 (¥{$po->total_amount})",
                        'priority'     => 'normal',
                        'status'       => ApprovalRecord::STATUS_PENDING,
                        'amount'       => $po->total_amount,
                        'applicant_id' => $user?->id ?? $po->created_by ?? 1,
                        'current_approver_id' => 1,
                        'payload'      => $payload,
                        'flow'         => [[
                            'operator' => $user?->name ?? '—',
                            'action'   => 'submit',
                            'time'     => now()->toDateTimeString(),
                        ]],
                    ]);
                }
            } catch (\Throwable $e) {
                // 审批中心创建失败不影响 PO 提交
                \Log::warning('PO submit -> approval failed: ' . $e->getMessage());
            }
            return $po->fresh();
        });
    }

    public function approveOrder(int $orderId, ?User $user = null, string $remark = ''): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $user, $remark) {
            $po = PurchaseOrder::findOrFail($orderId);
            $po->update([
                'status'      => self::STATUS_ORDER_APPROVED,
                'approved_by' => $user?->id,
                'approved_at' => now(),
            ]);
            $this->log(self::ENTITY_ORDER, $po->id, self::STATUS_ORDER_PENDING, self::STATUS_ORDER_APPROVED, 'approve', $user, $remark);

            // 同步应付账款 (auto create)
            $payable = Payable::firstOrCreate(
                ['po_id' => $po->id, 'supplier_id' => $po->supplier_id],
                [
                    'project_id'  => $po->project_id,
                    'amount'      => $po->total_amount,
                    'paid_amount' => 0,
                    'remaining_amount' => $po->total_amount,
                    'due_date'    => today()->addDays(30),
                    'payment_term'=> '月结30天',
                    'status'      => 'pending',
                    'ref_no'      => 'AP-' . date('Ymd') . '-' . str_pad($po->id, 4, '0', STR_PAD_LEFT),
                    'description' => "采购单 {$po->po_no} 应付",
                    'tender_id'   => $po->tender_id,
                ]
            );
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
            $po = PurchaseOrder::findOrFail($orderId);
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
            $c = PurchaseContract::findOrFail($contractId);
            $c->update([
                'status'   => self::STATUS_CONTRACT_SIGNED,
                'signer'   => $user?->name ?? $c->signer,
                'signer_id'=> $user?->id ?? $c->signer_id,
                'signed_at'=> $c->signed_at ?? today(),
            ]);
            $this->log(self::ENTITY_CONTRACT, $c->id, self::STATUS_CONTRACT_DRAFT, self::STATUS_CONTRACT_SIGNED, 'sign', $user);
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
            $c = PurchaseContract::findOrFail($contractId);
            $amount = (float) $data['amount'];
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
                'applicant_id' => $user?->id,
                'reason'       => $data['reason'] ?? null,
                'payable_id'   => $c->purchaseOrder?->id ? Payable::where('po_id', $c->purchase_order_id)->value('id') : null,
            ]);
            $this->log(self::ENTITY_PAYMENT_REQ, $req->id, null, self::STATUS_PAYREQ_PENDING, 'submit', $user, $req->stage_label ? "[{$req->stage_label}] 付款申请" : '付款申请');
            return $req;
        });
    }

    public function approvePaymentRequest(int $reqId, ?User $user = null, string $remark = ''): PurchasePaymentRequest
    {
        return DB::transaction(function () use ($reqId, $user, $remark) {
            $req = PurchasePaymentRequest::findOrFail($reqId);
            if ($req->status !== self::STATUS_PAYREQ_PENDING) {
                throw new \RuntimeException('只有待审批的付款申请可以审批');
            }
            $req->update([
                'status'         => self::STATUS_PAYREQ_APPROVED,
                'approver_id'    => $user?->id,
                'approved_at'    => now(),
                'approve_remark' => $remark,
            ]);
            $this->log(self::ENTITY_PAYMENT_REQ, $req->id, self::STATUS_PAYREQ_PENDING, self::STATUS_PAYREQ_APPROVED, 'approve', $user, $remark);

            // 同步到审批中心
            try {
                $exists = ApprovalRecord::where('type', 'finance')
                    ->where('sub_type', 'purchase_payment')
                    ->where('status', 'pending')
                    ->whereJsonContains('payload->payment_request_id', $req->id)
                    ->exists();
                if (!$exists) {
                    $flowService = app(\App\Services\ApprovalFlowService::class);
                    $template = $flowService->resolveTemplate('purchase_payment');
                    $applicant = User::find($req->applicant_id ?? $user?->id ?? 1);
                    $flowData = $template
                        ? $flowService->initFlow($template, $applicant, '提交付款审批')
                        : ['current_approver_id' => 1, 'flow' => [[
                            'operator' => $user?->name ?? '—',
                            'action'   => 'submit',
                            'time'     => now()->toDateTimeString(),
                            'comment'  => '提交付款审批',
                        ]]];

                    ApprovalRecord::create([
                        'code'                => $this->nextCode('PAY-AP'),
                        'type'                => 'finance',
                        'sub_type'            => 'purchase_payment',
                        'title'               => "[付款] {$req->code} ¥{$req->amount} 财务审批",
                        'priority'            => 'high',
                        'status'              => ApprovalRecord::STATUS_PENDING,
                        'amount'              => $req->amount,
                        'applicant_id'        => $req->applicant_id ?? $user?->id ?? 1,
                        'current_approver_id' => $flowData['current_approver_id'],
                        'payload'             => [
                            'payment_request_id' => $req->id,
                            'contract_id'        => $req->contract_id,
                            'supplier_id'        => $req->supplier_id,
                        ],
                        'flow'                => $flowData['flow'],
                        'flow'         => [[
                            'operator' => $user?->name ?? '—',
                            'action'   => 'submit',
                            'time'     => now()->toDateTimeString(),
                        ]],
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning('Payment request -> approval failed: ' . $e->getMessage());
            }
            return $req->fresh();
        });
    }

    /**
     * 阶段 6: 财务付款 — 写实付 + 更新 payable
     */
    public function executePayment(int $reqId, array $data, ?User $user = null): PurchasePayment
    {
        return DB::transaction(function () use ($reqId, $data, $user) {
            $req = PurchasePaymentRequest::with('payments')->lockForUpdate()->findOrFail($reqId);
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
                $payable = Payable::lockForUpdate()->find($req->payable_id);
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

            if ($amount >= $remainingRequestAmount - 0.0001) {
                $req->update(['status' => self::STATUS_PAYREQ_PAID]);
            }
            $this->log(self::ENTITY_PAYMENT, $pay->id, null, self::STATUS_PAY_COMPLETED, 'execute', $user, "实付 ¥{$pay->amount}");
            $this->log(self::ENTITY_PAYMENT_REQ, $req->id, self::STATUS_PAYREQ_APPROVED, self::STATUS_PAYREQ_PAID, 'paid', $user);

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
            $c = PurchaseContract::findOrFail($contractId);
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
            $sh = PurchaseShipment::findOrFail($shipId);
            $old = $sh->status;
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
            if ($sh->stock_record_id) {
                return StockRecord::findOrFail($sh->stock_record_id);
            }

            $existing = StockRecord::where('related_type', 'purchase_shipment')
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
                    'operator_id'       => $user?->id ?? 1,
                    'remark'            => "采购到货 {$sh->code} / {$contractItem->material}",
                ]);
                $firstRecord ??= $lastRecord;
            }
            $sh->update(['stock_record_id' => $firstRecord->id, 'status' => self::STATUS_SHIP_INSPECTED]);
            $this->log(self::ENTITY_SHIPMENT, $sh->id, self::STATUS_SHIP_ARRIVED, self::STATUS_SHIP_INSPECTED, 'auto_inbound', $user, "自动生成入库流水 {$firstRecord->record_no}");
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

            $contract = $sh->contract;
            if ($contract && $contract->plan_id) {
                $reqIds = \DB::table('purchase_requirements')->where('merged_plan_id', $contract->plan_id)->pluck('id');
                PurchaseRequirement::whereIn('id', $reqIds)->update(['status' => self::STATUS_REQ_FULFILLED]);
                PurchasePlan::where('id', $contract->plan_id)->update(['status' => self::STATUS_PLAN_FULFILLED]);
                PurchaseOrder::where('id', $contract->purchase_order_id)->update(['status' => self::STATUS_ORDER_FULFILLED]);
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
                self::ENTITY_REQUIREMENT => PurchaseRequirement::findOrFail($entityId),
                self::ENTITY_PLAN         => PurchasePlan::findOrFail($entityId),
                self::ENTITY_ORDER        => PurchaseOrder::findOrFail($entityId),
                self::ENTITY_CONTRACT     => PurchaseContract::findOrFail($entityId),
                self::ENTITY_PAYMENT_REQ  => PurchasePaymentRequest::findOrFail($entityId),
                self::ENTITY_SHIPMENT     => PurchaseShipment::findOrFail($entityId),
                default => throw new \InvalidArgumentException("不支持的 entity_type: $entityType"),
            };

            $from = $model->status;
            $cancellable = ['pending', 'submitted', 'draft', 'shipped', 'in_transit'];
            if (!in_array($from, $cancellable, true)) {
                throw new \RuntimeException("当前状态 {$from} 不可撤回, 仅 pending/submitted/draft/shipped/in_transit 可撤回");
            }

            $model->update(['status' => 'cancelled']);
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
            $contract = PurchaseContract::with('purchaseOrder.items')->findOrFail($contractId);
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
            $contract = PurchaseContract::findOrFail($contractId);
            $qty = (float)($data['qty'] ?? 0);
            $unitPrice = (float)($data['unit_price'] ?? 0);
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
            $item = PurchaseContractItem::where('contract_id', $contractId)->where('id', $itemId)->firstOrFail();
            $qty = isset($data['qty']) ? (float)$data['qty'] : (float)$item->qty;
            $unitPrice = isset($data['unit_price']) ? (float)$data['unit_price'] : (float)$item->unit_price;
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
            $item = PurchaseContractItem::where('contract_id', $contractId)->where('id', $itemId)->firstOrFail();
            $label = $item->material;
            $item->delete();
            $this->log(self::ENTITY_CONTRACT, $contractId, null, 'remove_item', 'remove_item', $user, "删除清单: {$label}");
        });
    }

    /**
     * 上传合同文件 (PDF)
     * 落盘到私有存储，仅通过鉴权下载接口访问。
     */
    public function uploadContractFile(int $contractId, \Illuminate\Http\UploadedFile $file, ?User $user = null): PurchaseContractFile
    {
        // 合规 (audit-2026-06-28 C3): 合同文件存 public disk，必须强制 MIME 白名单 (P1 重构: 走 FileUploadService)
        $uploader = app(FileUploadService::class);
        $fakeReq = \Illuminate\Http\Request::create('/', 'POST', [], [], ['file' => $file]);
        $result = $uploader->store($fakeReq, 'file', [
            'disk'         => 'local',
            'subdir'       => "private/purchase/contracts/{$contractId}",
            'allowed_ext'  => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
            'allowed_mime' => ['application/pdf', 'image/jpeg', 'image/png',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'max_size'     => 20480,
        ]);
        return DB::transaction(function () use ($contractId, $result, $user) {
            $contract = PurchaseContract::findOrFail($contractId);
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
    }

    public function listContractFiles(int $contractId): array
    {
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
            $f = PurchaseContractFile::where('contract_id', $contractId)->where('id', $fileId)->firstOrFail();
            \App\Support\PrivateFileStorage::delete($f->file_path);
            $label = $f->file_name;
            $f->delete();
            $this->log(self::ENTITY_CONTRACT, $contractId, null, 'delete_file', 'delete_file', $user, "删除附件: {$label}");
        });
    }

    /**
     * 上传付款凭证 (PNG/JPEG/PDF)
     * 落盘到私有存储，仅通过鉴权下载接口访问。
     */
    public function uploadPaymentVoucher(int $paymentRequestId, \Illuminate\Http\UploadedFile $file, ?User $user = null, ?string $remark = null): PurchasePaymentVoucher
    {
        // 合规 (audit-2026-06-28 C4): 付款凭证存 public disk (资金凭证)，必须强制 MIME 白名单 (P1 重构: 走 FileUploadService)
        $uploader = app(FileUploadService::class);
        $fakeReq = \Illuminate\Http\Request::create('/', 'POST', [], [], ['file' => $file]);
        $result = $uploader->store($fakeReq, 'file', [
            'disk'         => 'local',
            'subdir'       => "private/purchase/vouchers/{$paymentRequestId}",
            'allowed_ext'  => ['pdf', 'jpg', 'jpeg', 'png'],
            'allowed_mime' => ['application/pdf', 'image/jpeg', 'image/png'],
            'max_size'     => 10240,
        ]);
        return DB::transaction(function () use ($paymentRequestId, $result, $user, $remark) {
            $pr = PurchasePaymentRequest::findOrFail($paymentRequestId);
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
    }

    public function listPaymentVouchers(int $paymentRequestId): array
    {
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
            $contract = PurchaseContract::findOrFail($contractId);
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
            $contract = PurchaseContract::findOrFail($contractId);
            $itemId = $data['contract_item_id'] ?? null;
            $data['shipped_at'] = $data['shipped_at'] ?? today();
            $data['status'] = $data['status'] ?? 'shipped';
            return $this->setShippingPlan($contractId, $data, $user);
        });
    }

    public function listShipping(int $contractId): array
    {
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
                $rootReqId = $entityId;
                break;
            case self::ENTITY_PLAN:
                $rootReqId = PurchasePlan::where('id', $entityId)->value('requirement_id');
                if (!$rootReqId) {
                    // 通过 merged_plan_id 反查
                    $rootReqId = PurchaseRequirement::where('merged_plan_id', $entityId)->value('id');
                }
                break;
            case self::ENTITY_ORDER:
                $po = PurchaseOrder::find($entityId);
                $rootReqId = $po?->source_requirement_id ?? PurchasePlan::where('id', $po?->plan_id)->value('requirement_id');
                break;
            case self::ENTITY_CONTRACT:
                $c = PurchaseContract::find($entityId);
                $rootReqId = $c?->plan?->requirement_id ?? PurchasePlan::where('id', $c?->plan_id)->value('requirement_id');
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
            [['type' => self::ENTITY_PAYMENT, 'id' => 0]],  // 占位
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


    private function syncRequirementApprovalRecord(PurchaseRequirement $requirement, ?User $user = null): void
    {
        try {
            $exists = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_requirement')
                ->where('payload->requirement_id', $requirement->id)
                ->exists();
            if ($exists) {
                return;
            }
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
                'applicant_id'        => $user?->id ?? 1,
                'current_approver_id' => $this->resolveApproverId('purchase_requirement', $user),
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
                ],
                'flow'         => [[
                    'operator' => $user?->name ?? '系统',
                    'action'   => 'submit',
                    'time'     => now()->toDateTimeString(),
                    'comment'  => '提交采购需求审批',
                ]],
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Purchase flow requirement -> approval failed: ' . $e->getMessage());
        }
    }

    private function syncRequirementApprovalStatus(PurchaseRequirement $requirement, ?User $user, string $status, string $remark = ''): void
    {
        try {
            $approval = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_requirement')
                ->where('payload->requirement_id', $requirement->id)
                ->first();
            if (!$approval) {
                return;
            }
            $flow = is_array($approval->flow) ? $approval->flow : [];
            $flow[] = [
                'operator' => $user?->name ?? '系统',
                'action'   => $status === ApprovalRecord::STATUS_APPROVED ? 'approve' : 'reject',
                'time'     => now()->toDateTimeString(),
                'comment'  => $remark,
            ];
            $approval->update([
                'status' => $status,
                'flow' => $flow,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Purchase flow requirement approval status sync failed: ' . $e->getMessage());
        }
    }

    private function resolveApproverId(string $subType, ?User $user = null): ?int
    {
        try {
            $flowService = app(\App\Services\ApprovalFlowService::class);
            $template = $flowService->resolveTemplate($subType);
            if ($template) {
                $flowData = $flowService->initFlow($template, $user ?? User::find(1), '提交审批');
                return $flowData['current_approver_id'];
            }
        } catch (\Throwable $e) {
            \Log::warning('resolveApproverId failed', ['subType' => $subType, 'msg' => $e->getMessage()]);
        }
        return 1;
    }

    private function nextCode(string $prefix): string
    {
        return self::uniqueCode($prefix);
    }
}
