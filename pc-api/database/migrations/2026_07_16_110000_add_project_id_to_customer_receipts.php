<?php

/**
 * V1.2.16: 给 customer_receipts 加 project_id 字段
 * 用于支持收款单选择关联项目
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_receipts', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('customer_id');
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('customer_receipts', 'project_id')) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            }
        });
    }
};