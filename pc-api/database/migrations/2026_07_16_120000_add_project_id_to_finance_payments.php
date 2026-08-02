<?php

/**
 * V1.2.16: 给 finance_payments 加 project_id 字段
 * 用于付款单选择关联项目
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('finance_payments', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('payable_id');
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            if (Schema::hasColumn('finance_payments', 'project_id')) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            }
        });
    }
};