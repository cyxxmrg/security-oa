<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.7 巡检计划 (Inspection Plan)
 *
 * 设计原则：
 * - 1 个合同 (MaintenanceContract) → N 个巡检计划 (InspectionPlan)
 * - 1 个巡检计划 → N 个执行任务 (InspectionTask)，按排程周期自动生成
 * - 1 个执行任务 → 1 个现场打卡记录 (InspectionRecord)
 * - 1 个现场打卡 → N 个设备检查 (InspectionIssue)
 * - InspectionIssue 异常时自动转工单 (WorkOrder)
 */
return new class extends Migration {
    public function up(): void
    {
        // ===== 1. 巡检计划 (合同维度的排程模板) =====
        Schema::create('inspection_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_no', 32)->unique()->comment('计划编号 IP-20260626-001');
            $table->foreignId('contract_id')->constrained('maintenance_contracts')->onDelete('cascade')->comment('关联维保合同');
            $table->foreignId('customer_id')->constrained('customers')->comment('冗余客户');
            $table->string('name', 100)->comment('计划名称, 如 6月海康相机月度巡检');
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'quarterly', 'semiannual', 'yearly', 'custom'])->default('monthly')->comment('排程频率');
            $table->unsignedSmallInteger('cycle_day')->nullable()->comment('每月第几天执行 (1-31), frequency=monthly/quarterly 时用');
            $table->unsignedSmallInteger('cycle_weekday')->nullable()->comment('每周第几天 (1-7), frequency=weekly/biweekly 时用');
            $table->unsignedSmallInteger('custom_interval_days')->nullable()->comment('自定义间隔天数, frequency=custom 时用');
            $table->unsignedSmallInteger('duration_hours')->default(4)->comment('单次预计耗时(小时)');
            $table->unsignedSmallInteger('priority')->default(3)->comment('1=特急 2=紧急 3=普通 4=低');
            $table->string('assigned_to', 32)->nullable()->comment('默认执行人 user_id (JSON 数组, 支持多工程师)');
            $table->text('scope')->nullable()->comment('巡检范围描述, 如 6 号楼 3 楼所有监控');
            $table->text('checklist_template')->nullable()->comment('检查项模板 (JSON 数组, 每项 {name, type: text/number/select/photo, required, options, normal_value})');
            $table->date('start_date')->comment('排程起始日期');
            $table->date('end_date')->nullable()->comment('排程截止日期 (默认合同结束日期)');
            $table->unsignedSmallInteger('ahead_generate_days')->default(30)->comment('提前生成任务的天数');
            $table->enum('status', ['active', 'paused', 'expired', 'cancelled'])->default('active')->comment('active=启用 paused=暂停 expired=到期 cancelled=取消');
            $table->unsignedInteger('total_generated')->default(0)->comment('已生成任务数');
            $table->unsignedInteger('total_completed')->default(0)->comment('已完成任务数');
            $table->unsignedInteger('total_issues')->default(0)->comment('总异常数');
            $table->string('created_by', 32)->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'status']);
            $table->index('frequency');
            $table->index('created_at');
        });

        // ===== 1b. inspection_plans 补 next_generate_at 字段 (用于增量排程) =====
        Schema::table('inspection_plans', function (Blueprint $table) {
            $table->date('next_generate_at')->nullable()->after('status')->comment('下一次应触发增量生成的日期');
            $table->index('next_generate_at', 'idx_plans_next_generate_at');
        });

        // ===== 2. 巡检任务 (排程生成的具体执行单) =====
        Schema::create('inspection_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_no', 32)->unique()->comment('任务编号 IT-20260626-001');
            $table->foreignId('plan_id')->constrained('inspection_plans')->onDelete('cascade');
            $table->foreignId('contract_id')->constrained('maintenance_contracts')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers');
            $table->date('scheduled_date')->comment('计划执行日期');
            $table->unsignedSmallInteger('scheduled_hour')->default(9)->comment('计划执行时间 (小时 0-23)');
            $table->timestamp('scheduled_at')->comment('计划执行时间戳 (冗余, 便于排序)');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->comment('执行人 (排程生成时已分配)');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue', 'skipped', 'cancelled'])->default('pending')->comment('状态机: pending→in_progress→completed; 或→overdue; 或→skipped/cancelled');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable()->comment('实际耗时(分钟)');
            $table->unsignedSmallInteger('equipment_count')->default(0)->comment('检查设备总数');
            $table->unsignedSmallInteger('issue_count')->default(0)->comment('发现异常数');
            $table->boolean('overdue_notified')->default(false)->comment('是否已发逾期通知');
            $table->string('overdue_notified_at', 32)->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'scheduled_date']);
            $table->index(['assigned_to', 'status', 'scheduled_date'], 'idx_task_assignee');
            $table->index('status');
            $table->index('scheduled_date');
        });

        // ===== 3. 现场打卡 (工程师到达现场, 含 GPS + 照片) =====
        Schema::create('inspection_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_no', 32)->unique()->comment('记录编号 IR-20260626-001');
            $table->foreignId('task_id')->constrained('inspection_tasks')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('inspection_plans');
            $table->foreignId('user_id')->constrained('users')->comment('打卡人');
            $table->timestamp('checkin_at')->comment('现场打卡时间');
            $table->string('checkin_location', 200)->nullable()->comment('打卡地点文字');
            $table->decimal('checkin_lat', 10, 7)->nullable();
            $table->decimal('checkin_lng', 10, 7)->nullable();
            $table->json('checkin_photos')->nullable()->comment('现场照片 URLs');
            $table->timestamp('checkout_at')->nullable()->comment('完成离开时间');
            $table->string('checkout_location', 200)->nullable();
            $table->decimal('checkout_lat', 10, 7)->nullable();
            $table->decimal('checkout_lng', 10, 7)->nullable();
            $table->json('checklist_answers')->nullable()->comment('检查项答案 (JSON, key=checklist_name, value=actual_value)');
            $table->unsignedSmallInteger('normal_count')->default(0)->comment('正常项数');
            $table->unsignedSmallInteger('abnormal_count')->default(0)->comment('异常项数');
            $table->text('summary')->nullable()->comment('巡检小结');
            $table->unsignedTinyInteger('rating')->nullable()->comment('巡检质量自评 1-5');
            $table->enum('status', ['checked_in', 'checked_out'])->default('checked_in');
            $table->timestamps();

            $table->index(['task_id', 'status']);
            $table->index(['user_id', 'checkin_at'], 'idx_record_user_time');
        });

        // ===== 4. 巡检异常 (单设备问题点, 可转工单) =====
        Schema::create('inspection_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_no', 32)->unique()->comment('异常编号 II-20260626-001');
            $table->foreignId('record_id')->constrained('inspection_records')->onDelete('cascade');
            $table->foreignId('task_id')->constrained('inspection_tasks')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('inspection_plans')->onDelete('cascade');
            $table->foreignId('contract_id')->constrained('maintenance_contracts')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete()->comment('关联设备');
            $table->string('equipment_name', 100)->comment('设备名 (冗余)');
            $table->string('equipment_location', 200)->nullable()->comment('设备位置');
            $table->enum('issue_type', ['hardware', 'software', 'network', 'power', 'environment', 'other'])->default('hardware')->comment('异常类型');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->comment('严重程度');
            $table->string('title', 200)->comment('异常标题');
            $table->text('description')->comment('异常详细描述');
            $table->json('photos')->nullable()->comment('现场照片');
            $table->enum('status', ['open', 'work_order_created', 'resolved', 'ignored'])->default('open')->comment('open→work_order_created→resolved 或 ignored');
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete()->comment('自动生成的工单');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by', 32)->nullable();
            $table->text('resolution')->nullable()->comment('处理方案');
            $table->timestamps();

            $table->index(['task_id', 'status']);
            $table->index(['contract_id', 'status']);
            $table->index(['severity', 'status']);
            $table->index('inventory_item_id');
        });

        // ===== 5. 排程触发器 (用于 cron 增量生成) =====
        Schema::create('inspection_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('inspection_plans')->onDelete('cascade');
            $table->date('last_generated_date')->nullable()->comment('最后生成的执行日期');
            $table->date('next_scheduled_date')->comment('下一次应生成的执行日期');
            $table->unsignedInteger('generated_count')->default(0)->comment('本次生成数');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->unique('plan_id');
            $table->index('next_scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_schedules');
        Schema::dropIfExists('inspection_issues');
        Schema::dropIfExists('inspection_records');
        Schema::dropIfExists('inspection_tasks');
        Schema::dropIfExists('inspection_plans');
    }
};
