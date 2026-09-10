<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.4.5 (REVIEW P2-8 修复): 库存出入库幂等键
 *
 * request_id 由客户端/网关生成; 重复提交(网络重试/双击)时服务端返回历史结果,
 * 避免重复入账/扣减与重复财务联动。
 * PG 部分唯一索引: 仅对非空 request_id 生效。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stock_records', 'request_id')) {
            Schema::table('stock_records', function (Blueprint $table) {
                $table->string('request_id', 64)->nullable()->after('record_no')->comment('幂等键(客户端生成, 重复提交防重)');
            });
        }

        // PG 部分唯一索引 (非空 request_id 唯一) — 幂等创建
        $indexExists = collect(DB::select(
            "SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'stock_records_request_id_uq'"
        ))->isNotEmpty();
        if (!$indexExists) {
            DB::statement('CREATE UNIQUE INDEX stock_records_request_id_uq ON stock_records (request_id) WHERE request_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stock_records_request_id_uq');
        if (Schema::hasColumn('stock_records', 'request_id')) {
            Schema::table('stock_records', function (Blueprint $table) {
                $table->dropColumn('request_id');
            });
        }
    }
};
