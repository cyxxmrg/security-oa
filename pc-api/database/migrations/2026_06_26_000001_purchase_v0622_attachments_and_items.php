<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.6.2.2 采购协同 — 合同/清单/附件/付款凭证/发货计划
 *
 * 新增 4 张表:
 * 1. purchase_contract_files      合同附件 (PDF, 存 storage/app/public/purchase/contracts/{id}/)
 * 2. purchase_contract_items      合同清单 (从 PO.line_items 自动同步, 单价允许编辑)
 * 3. purchase_payment_vouchers    付款凭证 (PNG/JPEG/PDF, 存 storage/app/public/purchase/vouchers/{id}/)
 * 4. purchase_shipping_plans      发货预期 (按 contract_item_id 拆分, 可空 = 整单)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- 1. 合同附件 ----
        Schema::create('purchase_contract_files', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('contract_id')->comment('purchase_contracts.id');
            $t->string('file_path', 500)->comment('相对 storage 路径: purchase/contracts/{id}/xxx.pdf');
            $t->string('file_name', 255)->comment('原始文件名');
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->default(0)->comment('字节');
            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->timestamp('uploaded_at')->useCurrent();
            $t->index('contract_id');
        });

        // ---- 2. 合同清单 ----
        Schema::create('purchase_contract_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('contract_id')->comment('purchase_contracts.id');
            $t->string('material', 200)->comment('物料名称');
            $t->string('spec', 200)->nullable()->comment('规格');
            $t->decimal('qty', 12, 2)->default(0)->comment('数量');
            $t->string('unit', 20)->default('件')->comment('单位');
            $t->decimal('unit_price', 12, 2)->default(0)->comment('单价 (可编辑)');
            $t->decimal('subtotal', 14, 2)->default(0)->comment('小计 = qty * unit_price');
            $t->string('remark', 500)->nullable();
            $t->timestamps();
            $t->index('contract_id');
        });

        // ---- 3. 付款凭证 ----
        Schema::create('purchase_payment_vouchers', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('payment_request_id')->comment('purchase_payment_requests.id');
            $t->string('file_path', 500)->comment('相对 storage 路径: purchase/vouchers/{id}/xxx.png');
            $t->string('file_name', 255)->comment('原始文件名');
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->timestamp('uploaded_at')->useCurrent();
            $t->string('remark', 500)->nullable();
            $t->index('payment_request_id');
        });

        // ---- 4. 发货计划/快递单号 (合表: expected_at / tracking_no 用同一张表区分) ----
        Schema::create('purchase_shipping_plans', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('contract_id')->comment('purchase_contracts.id');
            $t->unsignedBigInteger('contract_item_id')->nullable()->comment('purchase_contract_items.id, NULL=整单');
            $t->date('expected_at')->nullable()->comment('预计发货日期');
            $t->string('carrier', 100)->nullable()->comment('物流公司');
            $t->string('tracking_no', 100)->nullable()->comment('快递单号');
            $t->date('shipped_at')->nullable()->comment('实际发货日期');
            $t->string('status', 30)->default('planned')->comment('planned/shipped/in_transit/arrived/received');
            $t->string('remark', 500)->nullable();
            $t->timestamps();
            $t->index('contract_id');
            $t->index('contract_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_shipping_plans');
        Schema::dropIfExists('purchase_payment_vouchers');
        Schema::dropIfExists('purchase_contract_items');
        Schema::dropIfExists('purchase_contract_files');
    }
};
