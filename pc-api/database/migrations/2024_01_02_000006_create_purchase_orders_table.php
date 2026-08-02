<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable()->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->string('po_no', 50)->unique()->comment('采购单号');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('总金额');
            $table->enum('status', ['draft', 'submitted', 'approved', 'purchasing', 'completed', 'cancelled'])->default('draft')->comment('采购状态');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('审批人');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable()->comment('审批时间');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('project_id');
            $table->index('supplier_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
