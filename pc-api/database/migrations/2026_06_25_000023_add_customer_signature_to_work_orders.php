<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.5.8 修复: 幂等 (避免重复列 42701)
        if (Schema::hasColumn('work_orders', 'customer_signature')) {
            return;
        }
        Schema::table('work_orders', function (Blueprint $table) {
            // V0.5.5.2 A4 — 客户签字 (base64 图片, 上门服务时使用)
            $table->text('customer_signature')->nullable()->after('result_notes');
            $table->timestamp('customer_signed_at')->nullable()->after('customer_signature');
            $table->string('customer_signature_ip', 45)->nullable()->after('customer_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['customer_signature', 'customer_signed_at', 'customer_signature_ip']);
        });
    }
};
