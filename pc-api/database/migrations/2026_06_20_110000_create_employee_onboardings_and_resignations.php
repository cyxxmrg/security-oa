<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('employee_onboardings')) {
            return;
        }

        // 员工入职档案
        Schema::create('employee_onboardings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('hire_date')->comment('入职日期');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('mentor_id')->nullable()->comment('导师(老员工)');
            $table->unsignedTinyInteger('probation_months')->default(3)->comment('试用期月数');
            $table->date('probation_end_date')->nullable()->comment('试用期结束日期');
            $table->date('contract_start')->nullable()->comment('合同开始');
            $table->date('contract_end')->nullable()->comment('合同结束');

            // 4 类证件 + 1 份合同, 用 disk_files 关联
            $table->string('id_card_no', 32)->nullable()->comment('身份证号');
            $table->unsignedBigInteger('id_card_file_id')->nullable();
            $table->string('driver_license_no', 32)->nullable()->comment('驾驶证号');
            $table->date('driver_license_expire')->nullable()->comment('驾驶证到期');
            $table->unsignedBigInteger('driver_license_file_id')->nullable();
            $table->string('education_level', 32)->nullable()->comment('学历(高中/大专/本科/硕士/博士)');
            $table->string('education_school', 200)->nullable()->comment('毕业院校');
            $table->string('education_major', 100)->nullable()->comment('专业');
            $table->unsignedBigInteger('education_file_id')->nullable();
            $table->unsignedBigInteger('contract_file_id')->nullable()->comment('劳动合同文件');

            $table->enum('status', ['active', 'archived'])->default('active');
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('onboarded_by')->nullable()->comment('办理人(HR)');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            $table->foreign('mentor_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('id_card_file_id')->references('id')->on('disk_files')->onDelete('set null');
            $table->foreign('driver_license_file_id')->references('id')->on('disk_files')->onDelete('set null');
            $table->foreign('education_file_id')->references('id')->on('disk_files')->onDelete('set null');
            $table->foreign('contract_file_id')->references('id')->on('disk_files')->onDelete('set null');
            $table->foreign('onboarded_by')->references('id')->on('users')->onDelete('set null');

            $table->unique('user_id');
            $table->index(['hire_date', 'status']);
            $table->index('contract_end');  // 合同到期预警
        });

        // 员工离职记录
        Schema::create('employee_resignations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('resign_date')->comment('离职日期');
            $table->date('notice_date')->nullable()->comment('离职申请日期');
            $table->date('last_work_day')->comment('最后工作日');
            $table->enum('resign_type', ['voluntary', 'involuntary', 'contract_end', 'retirement', 'other'])
                ->default('voluntary')
                ->comment('离职类型');

            $table->text('reason')->comment('离职原因');
            $table->unsignedBigInteger('handover_to_user_id')->nullable()->comment('工作交接给');
            $table->text('handover_note')->nullable()->comment('交接说明');

            // 资产归还 (json: [{name, returned, note}])
            $table->json('assets_checklist')->nullable()->comment('资产归还清单');
            $table->boolean('all_assets_returned')->default(false);

            // 工资结算
            $table->decimal('final_salary_amount', 10, 2)->nullable()->comment('最终工资');
            $table->decimal('leave_balance_payout', 10, 2)->nullable()->comment('未休年假折算');
            $table->decimal('severance_pay', 10, 2)->nullable()->comment('经济补偿金');
            $table->decimal('total_settlement', 10, 2)->nullable()->comment('合计结算');
            $table->date('paid_date')->nullable()->comment('发放日期');
            $table->string('paid_method', 32)->nullable()->comment('发放方式(银行转账/现金)');

            // 社保 + 离职证明
            $table->date('social_security_cutoff')->nullable()->comment('社保截止月');
            $table->unsignedBigInteger('resign_certificate_file_id')->nullable()->comment('离职证明文件');

            $table->enum('status', ['draft', 'pending', 'approved', 'completed', 'cancelled'])
                ->default('draft')
                ->comment('状态');
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable()->comment('审批人');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->comment('申请人(HR)');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('handover_to_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resign_certificate_file_id')->references('id')->on('disk_files')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['user_id', 'status']);
            $table->index('resign_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_resignations');
        Schema::dropIfExists('employee_onboardings');
    }
};
