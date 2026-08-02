<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.2 供应商对报价请求的应答（External Quote）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_quotes')) {
            return;
        }

        Schema::create('external_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->comment('报价请求ID');
            $table->foreign('request_id')->references('id')->on('external_quote_requests')->onDelete('cascade');
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->string('code', 30)->unique()->comment('报价单号');
            $table->jsonb('items')->comment('报价明细 JSON');
            $table->decimal('total_amount', 14, 2)->default(0)->comment('总金额');
            $table->date('valid_until')->nullable()->comment('报价有效期');
            $table->unsignedInteger('lead_time_days')->default(0)->comment('交付周期(天)');
            $table->enum('payment_terms', ['cash', '30days', '60days', '90days'])
                ->default('30days')->comment('账期');
            $table->jsonb('attachments')->nullable()->comment('附件 JSON');
            $table->text('note')->nullable()->comment('备注');
            $table->unsignedBigInteger('submitted_by')->nullable()->comment('提交人 user_id（供应商账号）');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable()->comment('提交时间');
            $table->enum('status', ['submitted', 'shortlisted', 'awarded', 'rejected'])
                ->default('submitted')->comment('状态');
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('审核人');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->timestamps();

            $table->index('request_id');
            $table->index('supplier_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_quotes');
    }
};
