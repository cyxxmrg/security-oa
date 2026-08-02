<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_no', 50)->unique()->comment('合同编号');
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('restrict');
            $table->decimal('amount', 12, 2)->comment('合同金额');
            $table->date('start_date')->comment('开始日期');
            $table->date('end_date')->comment('结束日期');
            $table->string('inspection_frequency', 50)->default('quarterly')->comment('巡检频率');
            $table->text('scope')->nullable()->comment('维保范围');
            $table->string('status', 50)->default('active')->comment('合同状态');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_contracts');
    }
};
