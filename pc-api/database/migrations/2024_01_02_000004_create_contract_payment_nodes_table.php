<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('contract_payment_nodes')) {
            return;
        }

        Schema::create('contract_payment_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id')->comment('合同ID');
            $table->foreign('contract_id')->references('id')->on('project_contracts')->onDelete('cascade');
            $table->string('name', 100)->comment('节点名称');
            $table->decimal('percentage', 5, 2)->comment('占比(%)');
            $table->decimal('amount', 12, 2)->comment('金额');
            $table->date('planned_date')->comment('计划付款日期');
            $table->date('actual_date')->nullable()->comment('实际付款日期');
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending')->comment('付款状态');
            $table->decimal('paid_amount', 12, 2)->default(0)->comment('已付金额');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_payment_nodes');
    }
};
