<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.2 users 表扩展：
 *  - type: 'staff' (内部员工) / 'supplier' (供应商账号) / 'customer' (客户账号)
 *  - supplier_id: 当 type='supplier' 时关联 suppliers.id
 *  - allowed_modules: jsonb, supplier 账号能访问的模块白名单
 *                    例: ["supplier:portal", "external-quote:submit"]
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'type')) {
                $table->string('type', 20)->default('staff')->after('id')->comment('staff/supplier/customer');
            }
            if (!Schema::hasColumn('users', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('type')->comment('关联供应商ID(type=supplier)');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            }
            if (!Schema::hasColumn('users', 'allowed_modules')) {
                $table->jsonb('allowed_modules')->nullable()->after('supplier_id')->comment('模块白名单');
            }
        });
    }

    public function down(): void
    {
        // 不回滚
    }
};
