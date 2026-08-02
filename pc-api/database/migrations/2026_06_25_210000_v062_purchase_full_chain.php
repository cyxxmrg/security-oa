<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.6.2 采购协同 8 步闭环 — 数据库补全
 *
 * 1. purchase_requirements: source_type/source_id/name/budget  + review_audit
 * 2. purchase_plans:         no change (已够用)
 * 3. purchase_orders:        plan_id/source_requirement_id/contract_id
 * 4. purchase_contracts:     purchase_order_id/payment_plan/inventory_received_at
 * 5. purchase_payment_requests: payable_id/invoice_received/invoice_received_at
 * 6. purchase_payments:      no change (已含 contract_id + payment_request_id)
 * 7. purchase_shipments:     inbound_order_id/auto_create_inbound
 * 8. inbound: 用现有 stock_records (type='in', related_type='purchase_shipment', related_id=shipment_id)
 *
 * 新增:
 * - purchase_status_logs: 状态机审计流水
 * - purchase_flow_nodes:  8 步流程节点模板(可配置)
 *
 * 状态机:
 *   requirement: pending → approved → merged → fulfilled / rejected / cancelled
 *   plan:        draft   → submitted → approved → fulfilled / rejected
 *   order:       draft   → pending   → approved → fulfilled / rejected / cancelled
 *   contract:    draft   → signing   → signed   → effective / cancelled
 *   payment_req: pending → approved  → paid     / rejected
 *   payment:     processing → completed / failed
 *   shipment:    pending → shipped  → in_transit → arrived → received → inspected → inbounded
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- 1. purchase_requirements 加来源 + 审计 ----
        Schema::table('purchase_requirements', function (Blueprint $t) {
            $t->string('source_type', 30)->nullable()->after('project_id')->comment('来源: work_order/external_work/project/stock_alert/manual/customer_contract');
            $t->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $t->string('name', 200)->nullable()->after('code')->comment('需求名称(列表展示用)');
            $t->decimal('budget', 12, 2)->nullable()->after('quantity')->comment('预算金额');
            $t->string('spec_text', 500)->nullable()->after('spec')->comment('规格描述(冗余)');
            $t->timestamp('merged_at')->nullable()->after('reviewed_at');
            $t->unsignedBigInteger('merged_plan_id')->nullable();
            $t->index(['source_type', 'source_id']);
        });

        // ---- 3. purchase_orders 补全链路 ----
        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->unsignedBigInteger('plan_id')->nullable()->after('project_id')->comment('上游采购计划');
            $t->unsignedBigInteger('source_requirement_id')->nullable()->after('plan_id')->comment('来源需求');
            $t->string('path', 20)->nullable()->after('tender_id')->comment('路径: quote/bid/manual');
            $t->unsignedBigInteger('quote_id')->nullable()->after('path')->comment('external_quotes.id (quote 路径)');
            $t->unsignedBigInteger('contract_id')->nullable()->after('approved_at')->comment('下游合同 (冗余加速查询)');
            $t->index('plan_id');
            $t->index('source_requirement_id');
        });

        // ---- 4. purchase_contracts ----
        Schema::table('purchase_contracts', function (Blueprint $t) {
            $t->unsignedBigInteger('purchase_order_id')->nullable()->after('plan_id')->comment('上游 PO');
            $t->json('payment_plan')->nullable()->after('payment_terms')->comment('[{stage:"定金",percent:30,trigger:"签合同后"}]');
            $t->index('purchase_order_id');
        });

        // ---- 5. purchase_payment_requests ----
        Schema::table('purchase_payment_requests', function (Blueprint $t) {
            $t->unsignedBigInteger('payable_id')->nullable()->after('contract_id')->comment('关联应付');
            $t->boolean('invoice_received')->default(false)->after('approve_remark');
            $t->timestamp('invoice_received_at')->nullable();
            $t->string('invoice_no', 100)->nullable();
            $t->string('stage_label', 50)->nullable()->after('payment_type')->comment('定金/进度款/尾款/质保金');
        });

        // ---- 7. purchase_shipments ----
        Schema::table('purchase_shipments', function (Blueprint $t) {
            $t->unsignedBigInteger('stock_record_id')->nullable()->after('arrived_at')->comment('入库后的 stock_records.id (type=in)');
            $t->boolean('inbound_confirmed')->default(false)->after('status')->comment('采购员确认入库');
            $t->unsignedBigInteger('inbound_confirmed_by')->nullable();
            $t->timestamp('inbound_confirmed_at')->nullable();
        });

        // ---- 9. 状态机审计流水 ----
        Schema::create('purchase_status_logs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('entity_type', 50)->comment('requirement/plan/po/contract/payment_request/payment/shipment');
            $t->unsignedBigInteger('entity_id');
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->string('action', 50)->comment('submit/approve/reject/cancel/confirm_inbound/...');
            $t->unsignedBigInteger('operator_id')->nullable();
            $t->string('operator_name', 50)->nullable();
            $t->text('remark')->nullable();
            $t->json('payload')->nullable()->comment('附加数据 (如付款金额、入库单号)');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['entity_type', 'entity_id']);
            $t->index('created_at');
        });

        // ---- 10. 防止双入库 / 双付款 (唯一约束) ----
        \DB::statement("CREATE UNIQUE INDEX uniq_shipment_to_stock ON purchase_shipments(stock_record_id) WHERE stock_record_id IS NOT NULL");
        \DB::statement("CREATE UNIQUE INDEX uniq_payreq_to_payable ON purchase_payment_requests(payable_id) WHERE payable_id IS NOT NULL");
    }

    public function down(): void
    {
        \DB::statement("DROP INDEX IF EXISTS uniq_shipment_to_stock");
        \DB::statement("DROP INDEX IF EXISTS uniq_payreq_to_payable");
        Schema::dropIfExists('purchase_status_logs');
        Schema::table('purchase_shipments', fn(Blueprint $t) => $t->dropColumn(['stock_record_id', 'inbound_confirmed', 'inbound_confirmed_by', 'inbound_confirmed_at']));
        Schema::table('purchase_payment_requests', fn(Blueprint $t) => $t->dropColumn(['payable_id', 'invoice_received', 'invoice_received_at', 'invoice_no', 'stage_label']));
        Schema::table('purchase_contracts', fn(Blueprint $t) => $t->dropColumn(['purchase_order_id', 'payment_plan']));
        Schema::table('purchase_orders', fn(Blueprint $t) => $t->dropColumn(['plan_id', 'source_requirement_id', 'path', 'quote_id', 'contract_id']));
        Schema::table('purchase_requirements', fn(Blueprint $t) => $t->dropColumn(['source_type', 'source_id', 'name', 'budget', 'spec_text', 'merged_at', 'merged_plan_id']));
    }
};
