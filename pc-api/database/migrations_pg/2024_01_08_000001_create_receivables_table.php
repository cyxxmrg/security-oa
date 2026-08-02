<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->unsignedBigInteger('project_id')->nullable()->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->unsignedBigInteger('contract_id')->nullable()->comment('合同ID');
            $table->foreign('contract_id')->references('id')->on('project_contracts')->onDelete('set null');
            $table->decimal('amount', 12, 2)->comment('应收金额');
            $table->decimal('received_amount', 12, 2)->default(0)->comment('已收金额');
            $table->decimal('remaining_amount', 12, 2)->default(0)->comment('未收金额');
            $table->date('due_date')->comment('到期日');
            $table->date('received_date')->nullable()->comment('实际收款日');
            $table->integer('overdue_days')->default(0)->comment('逾期天数');
            $table->string('status', 50);
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
            $table->index('due_date');
            $table->index('project_id');
            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
