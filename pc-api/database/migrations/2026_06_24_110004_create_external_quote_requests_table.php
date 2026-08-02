<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.2 对外报价请求（甲方发起，邀请多家供应商报价）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_quote_requests')) {
            return;
        }

        Schema::create('external_quote_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable()->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->string('code', 30)->unique()->comment('请求编号');
            $table->string('title', 200)->comment('标题');
            $table->jsonb('required_items')->comment('需求清单 JSON');
            $table->jsonb('required_files')->nullable()->comment('需要供应商提供的资料');
            $table->timestamp('deadline')->nullable()->comment('报价截止时间');
            $table->enum('status', ['open', 'closed', 'awarded', 'cancelled'])
                ->default('open')->comment('状态');
            $table->uuid('public_token')->unique()->comment('公开访问 token (无登录报价)');
            $table->unsignedBigInteger('awarded_supplier_id')->nullable()->comment('中标供应商');
            $table->foreign('awarded_supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->unsignedBigInteger('awarded_quote_id')->nullable()->comment('中标 quote');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->text('description')->nullable()->comment('说明');
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_quote_requests');
    }
};
