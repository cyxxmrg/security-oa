<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // V1.2.13: 收费方式替代 is_billable 开关
            if (!Schema::hasColumn('work_orders', 'charge_type')) {
                $table->string('charge_type', 20)->default('paid')->after('is_billable')
                      ->comment('收费方式: warranty_free=保内免费, contract_free=合同内免费, paid=收费');
            }
            if (!Schema::hasColumn('work_orders', 'contract_id')) {
                $table->unsignedBigInteger('contract_id')->nullable()->after('charge_type')
                      ->comment('关联合同 (合同内免费时)');
            }
            if (!Schema::hasColumn('work_orders', 'min_charge')) {
                $table->unsignedInteger('min_charge')->nullable()->after('service_fee')
                      ->comment('最低收费标准 (收费时: 120/300/500/800)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['charge_type', 'contract_id', 'min_charge']);
        });
    }
};
