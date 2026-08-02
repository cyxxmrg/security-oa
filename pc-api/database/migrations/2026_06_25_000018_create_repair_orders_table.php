<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('repair_orders')) {
            return;
        }

        Schema::create('repair_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique()->comment('返修单号 RN2026-001');

            // 来源
            $table->string('source_type', 16)->default('customer')->index()
                ->comment('customer=客户送修, work_order=工单转单, internal=内部');
            $table->unsignedBigInteger('source_id')->nullable()->index()->comment('work_orders.id (source_type=work_order 时)');
            $table->string('source_code', 32)->nullable()->comment('工单号 WO2026-001');

            // 关联
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('equipment_id')->nullable();

            // 联系信息
            $table->string('contact_name', 64)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('address', 255)->nullable();

            // 设备信息
            $table->string('equipment_brand', 64)->nullable();
            $table->string('equipment_model', 64)->nullable();
            $table->string('serial_no', 64)->nullable();

            // 故障
            $table->string('fault_type', 32)->nullable()->comment('硬件/软件/外观/性能/其他');
            $table->text('fault_description')->nullable();
            $table->string('severity', 16)->default('medium')->comment('low/medium/high');

            // 接件
            $table->unsignedBigInteger('received_by')->nullable()->index();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('expected_finish_at')->nullable();

            // 状态
            $table->string('status', 32)->default('received')->index();

            // 维修方式 (冗余字段, 来源 repair_methods 主表, 加快列表查询)
            $table->string('method_type', 32)->nullable()
                ->comment('free_warranty/free_contract/paid_repair/paid_replace/returned');

            // 成本
            $table->decimal('parts_cost', 10, 2)->default(0);
            $table->decimal('labor_cost', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);

            // 质保关联
            $table->boolean('is_warranty')->default(false);
            $table->date('warranty_until')->nullable();

            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'method_type'], 'ro_status_method_idx');
            $table->index(['received_at']);
            $table->index('expected_finish_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_orders');
    }
};
