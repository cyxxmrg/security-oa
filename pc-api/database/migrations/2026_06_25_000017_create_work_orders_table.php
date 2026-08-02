<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_orders')) {
            return;
        }

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();

            // 单号
            $table->string('code', 32)->unique()->comment('工单号 WO2026-001');

            // 关联
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('equipment_id')->nullable()->comment('关联 inventory 或 assets');

            // 联系信息
            $table->string('contact_name', 64)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('address', 255)->nullable();

            // 服务类型
            $table->string('service_type', 16)->default('on_site')->comment('上门/到店/远程');
            $table->string('priority', 16)->default('medium')->comment('low/medium/high/urgent');

            // 故障信息
            $table->text('fault_description')->nullable();
            $table->string('equipment_brand', 64)->nullable();
            $table->string('equipment_model', 64)->nullable();
            $table->string('serial_no', 64)->nullable();

            // 派单
            $table->unsignedBigInteger('assigned_to')->nullable()->index()->comment('工程师 user.id');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // 状态
            $table->string('status', 32)->default('pending')->index();

            // 费用
            $table->boolean('is_billable')->default(true);
            $table->decimal('service_fee', 10, 2)->default(0)->comment('服务费 (现场)');
            $table->decimal('parts_cost', 10, 2)->default(0)->comment('配件费 (现场)');
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->text('result_notes')->nullable();
            $table->string('customer_signature', 128)->nullable();

            // 转换为返修单后的关联
            $table->unsignedBigInteger('converted_repair_id')->nullable()->index();

            // 锁 (V0.5.5 V0.5.5 转换后/取消后不可编辑)
            $table->boolean('is_locked')->default(false);

            // audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority'], 'wo_status_pri_idx');
            $table->index(['assigned_to', 'status'], 'wo_assigned_status_idx');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
