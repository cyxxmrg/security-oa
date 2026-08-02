<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('restrict');
            $table->string('contract_no', 50)->unique()->comment('合同编号');
            $table->decimal('contract_amount', 12, 2)->comment('合同金额');
            $table->string('payment_method', 50)->default('installment')->comment('付款方式');
            $table->date('contract_start')->comment('合同开始日期');
            $table->date('contract_end')->comment('合同结束日期');
            $table->string('status', 50)->default('draft')->comment('合同状态');
            $table->string('attachment', 255)->nullable()->comment('附件路径');
            $table->timestamp('signed_at')->nullable()->comment('签署时间');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_contracts');
    }
};
