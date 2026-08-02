<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->unsignedBigInteger('project_id')->nullable()->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->decimal('amount', 12, 2)->comment('应付金额');
            $table->decimal('paid_amount', 12, 2)->default(0)->comment('已付金额');
            $table->decimal('remaining_amount', 12, 2)->default(0)->comment('未付金额');
            $table->date('due_date')->comment('付款到期日');
            $table->date('paid_date')->nullable()->comment('实际付款日');
            $table->string('payment_term', 50)->nullable()->comment('账期');
            $table->string('status', 50);
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('status');
            $table->index('due_date');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payables');
    }
};
