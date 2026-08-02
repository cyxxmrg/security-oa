<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.5 质保期管理 - 质保单主表
 *
 * - 一份质保单绑定项目 + 客户 + 设备
 * - start_date/end_date/period_months 三者冗余，便于日报统计
 * - status 状态机：active → expiring(临近过期) → expired / renewed / terminated
 * - renewed_from_id 记录是从哪份旧质保单续约的
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warranties')) {
            return;
        }

        Schema::create('warranties', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');

            $table->unsignedBigInteger('device_id')->nullable();
            $table->foreign('device_id')->references('id')->on('customer_devices')->onDelete('set null');

            $table->string('warranty_no', 50)->unique();

            $table->string('warranty_type', 20)->default('basic');
            // basic 基础质保 / extended 延保

            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('period_months')->default(12);

            $table->string('status', 20)->default('active');
            // active 在保 / expiring 即将过期 / expired 已过期 / renewed 已续约 / terminated 已终止

            $table->decimal('amount', 12, 2)->default(0);
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('renewed_from_id')->nullable();
            $table->foreign('renewed_from_id')->references('id')->on('warranties')->onDelete('set null');

            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['end_date', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranties');
    }
};
