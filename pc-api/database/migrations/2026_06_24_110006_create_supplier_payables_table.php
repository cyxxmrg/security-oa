<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.2 供应商应付台账 (supplier_payables)
 *
 * 与现有 payables 表(V0.3.18)区别:
 *  - supplier_payables 走 source_type/source_id 通用多源(quote/contract/manual)
 *  - balance 列使用 PG 生成列 (GENERATED ALWAYS AS amount - paid_amount STORED)
 *  - status 用 pending/partial/paid/overdue
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_payables')) {
            return;
        }

        Schema::create('supplier_payables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->unsignedBigInteger('project_id')->nullable()->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->string('source_type', 50)->default('manual')->comment('来源: quote/contract/manual');
            $table->unsignedBigInteger('source_id')->nullable()->comment('来源记录ID');
            $table->string('ref_no', 50)->nullable()->comment('业务单号');
            $table->decimal('amount', 14, 2)->default(0)->comment('应付金额');
            $table->decimal('paid_amount', 14, 2)->default(0)->comment('已付金额');
            $table->date('due_date')->nullable()->comment('到期日');
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])
                ->default('pending')->comment('状态');
            $table->text('note')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('project_id');
            $table->index('status');
            $table->index('due_date');
            $table->index(['source_type', 'source_id']);
        });

        // PG 生成列: balance = amount - paid_amount
        DB::statement("
            ALTER TABLE supplier_payables
            ADD COLUMN balance NUMERIC(14,2)
            GENERATED ALWAYS AS (amount - paid_amount) STORED
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payables');
    }
};
