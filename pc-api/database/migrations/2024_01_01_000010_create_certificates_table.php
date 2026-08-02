<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('certificates')) {
            return;
        }

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_profile_id')->comment('员工档案');
            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->onDelete('cascade');
            $table->string('certificate_name', 200)->comment('证书名称');
            $table->string('certificate_no', 100)->comment('证书编号');
            $table->date('issue_date')->comment('发证日期');
            $table->date('expire_date')->nullable()->comment('到期日期');
            $table->string('issuer', 200)->nullable()->comment('发证机构');
            $table->enum('status', ['valid', 'expired', 'revoking'])->default('valid')->comment('状态');
            $table->string('attachment', 255)->nullable()->comment('附件路径');
            $table->integer('remind_days')->default(30)->comment('提前提醒天数');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
