<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('repair_methods')) {
            return;
        }

        Schema::create('repair_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repair_order_id')->index();

            // 维修方式 (4 选 1 + 退回)
            $table->string('method_type', 32)->comment('free_warranty/free_contract/paid_repair/paid_replace/returned');
            $table->string('method_category', 32)->nullable()
                ->comment('in_warranty/out_warranty/pay_on_site/contract');

            // 成本
            $table->decimal('estimated_cost', 10, 2)->default(0);
            $table->decimal('actual_cost', 10, 2)->default(0);
            $table->json('parts_replaced')->nullable()->comment('换件清单 [{name, qty, price}]');
            $table->decimal('hours_spent', 6, 2)->default(0);

            // 厂家 (如果送厂修)
            $table->unsignedBigInteger('vendor_id')->nullable();

            // 结算
            $table->string('payment_method', 32)->nullable()->comment('现金/转账/支付宝/微信');
            $table->string('payment_status', 16)->default('unpaid')->comment('unpaid/partial/paid/refunded');
            $table->timestamp('paid_at')->nullable();
            $table->string('invoice_no', 64)->nullable();

            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'method_type'], 'rm_order_method_idx');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_methods');
    }
};
