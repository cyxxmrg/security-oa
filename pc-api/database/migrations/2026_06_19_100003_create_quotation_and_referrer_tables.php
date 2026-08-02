<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('referrers')) {
            return;
        }

        Schema::create('referrers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('推荐人姓名');
            $table->string('phone', 20)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable()->comment('关联老客户');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(5.00)->comment('居间费比例 %');
            $table->decimal('total_commission', 12, 2)->default(0)->comment('累计居间费');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('customer_id');
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quote_no', 30)->unique()->comment('报价单号 QT-2026-001');
            $table->unsignedBigInteger('opportunity_id')->comment('关联商机');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->onDelete('cascade');
            $table->unsignedInteger('version')->default(1)->comment('版本号');
            $table->decimal('subtotal', 12, 2)->default(0)->comment('小计');
            $table->decimal('discount_rate', 5, 2)->default(0)->comment('折扣率 %');
            $table->decimal('discount_amount', 12, 2)->default(0)->comment('折扣金额');
            $table->decimal('tax_rate', 5, 2)->default(13.00)->comment('税率 %');
            $table->decimal('tax_amount', 12, 2)->default(0)->comment('税额');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('含税总价');
            $table->date('valid_until')->nullable()->comment('报价有效期');
            $table->string('status', 20)->default('draft')->comment('draft/submitted/negotiating/accepted/rejected/expired');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('审批人');
            $table->timestamp('sent_at')->nullable()->comment('发送客户时间');
            $table->timestamp('responded_at')->nullable()->comment('客户回复时间');
            $table->timestamps();

            $table->index('opportunity_id');
            $table->index('status');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_id')->comment('报价单ID');
            $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('cascade');
            $table->unsignedBigInteger('inventory_item_id')->nullable()->comment('产品库物品');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('set null');
            $table->string('name', 200)->comment('产品/服务名称');
            $table->string('specification', 200)->nullable()->comment('规格型号');
            $table->string('unit', 20)->default('件');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index('quotation_id');
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('referrers');
    }
};
