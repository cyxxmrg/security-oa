<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('finance_invoices')) {
            return;
        }

        Schema::create('finance_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 50)->unique()->comment('发票号码');
            $table->enum('invoice_type', ['special', 'ordinary', 'electronic'])
                ->default('ordinary')
                ->comment('发票类型：专票/普票/电子发票');
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->unsignedBigInteger('project_id')->nullable()->comment('关联项目');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->unsignedBigInteger('receivable_id')->nullable()->comment('关联应收单');
            $table->foreign('receivable_id')->references('id')->on('receivables')->onDelete('set null');
            $table->decimal('amount', 12, 2)->comment('开票金额(不含税)');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('税率(%)');
            $table->decimal('tax_amount', 12, 2)->default(0)->comment('税额');
            $table->decimal('total_amount', 12, 2)->comment('价税合计');
            $table->date('issue_date')->comment('开票日期');
            $table->enum('status', ['draft', 'issued', 'cancelled'])
                ->default('draft')
                ->comment('发票状态');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('project_id');
            $table->index('receivable_id');
            $table->index('status');
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_invoices');
    }
};
