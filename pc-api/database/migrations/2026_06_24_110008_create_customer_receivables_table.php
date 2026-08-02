<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.2 客户应收台账 (customer_receivables)
 *
 * 与现有 receivables 表(V0.3.18)区别:
 *  - receivable_type: contract/progress/retention/warranty
 *  - balance 走 PG 生成列
 *  - source_type/source_id 通用多源
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_receivables')) {
            return;
        }

        Schema::create('customer_receivables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->unsignedBigInteger('project_id')->nullable()->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->string('source_type', 50)->default('manual')->comment('来源: contract/progress/manual');
            $table->unsignedBigInteger('source_id')->nullable()->comment('来源记录ID');
            $table->string('ref_no', 50)->nullable()->comment('业务单号');
            $table->enum('receivable_type', ['contract', 'progress', 'retention', 'warranty'])
                ->default('contract')->comment('应收类型');
            $table->decimal('amount', 14, 2)->default(0)->comment('应收金额');
            $table->decimal('received_amount', 14, 2)->default(0)->comment('已收金额');
            $table->date('due_date')->nullable()->comment('到期日');
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])
                ->default('pending')->comment('状态');
            $table->text('note')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('project_id');
            $table->index('status');
            $table->index('receivable_type');
            $table->index('due_date');
            $table->index(['source_type', 'source_id']);
        });

        DB::statement("
            ALTER TABLE customer_receivables
            ADD COLUMN balance NUMERIC(14,2)
            GENERATED ALWAYS AS (amount - received_amount) STORED
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_receivables');
    }
};
