<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('employee_profiles')) {
            return;
        }

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('关联用户');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('employee_no', 50)->unique()->comment('工号');
            $table->date('hire_date')->comment('入职日期');
            $table->date('leave_date')->nullable()->comment('离职日期');
            $table->enum('contract_type', ['fixed', 'open', 'trial'])->default('open')->comment('合同类型');
            $table->date('contract_start')->nullable()->comment('合同开始日期');
            $table->date('contract_end')->nullable()->comment('合同结束日期');
            $table->decimal('base_salary', 10, 2)->default(0)->comment('基本工资');
            $table->decimal('salary_allowance', 10, 2)->default(0)->comment('工资津贴');
            $table->string('emergency_contact', 50)->nullable()->comment('紧急联系人');
            $table->string('emergency_phone', 20)->nullable()->comment('紧急联系电话');
            $table->string('bank_name', 100)->nullable()->comment('开户银行');
            $table->string('bank_account', 50)->nullable()->comment('银行卡号');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
