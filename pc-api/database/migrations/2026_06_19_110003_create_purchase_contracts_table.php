<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_contracts')) {
            return;
        }

        Schema::create('purchase_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('合同编号 PC-YYYY-NNN');
            $table->foreignId('plan_id')->nullable()->comment('来源采购计划')->constrained('purchase_plans')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('title', 200)->comment('合同名称');
            $table->decimal('total_amount', 14, 2)->default(0)->comment('合同金额');
            $table->date('signed_at')->nullable()->comment('签订日期');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('payment_terms', 200)->nullable()->comment('付款条款');
            $table->string('delivery_address', 200)->nullable();
            $table->string('status', 20)->default('draft')->comment('draft/signed/shipping/completed/cancelled');
            $table->string('signer', 50)->nullable()->comment('我方签约人');
            $table->foreignId('signer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index(['status', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_contracts');
    }
};
