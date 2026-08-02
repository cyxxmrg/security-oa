<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v0.3.7.3 扩展 stock_records.type 枚举
 * 旧值: in, out, transfer, check
 * 新增: inbound, return, outbound, sale, scrap
 * 目的: 与 InventoryController::stockIn/stockOut 的业务规则保持一致
 */
return new class extends Migration
{
    public function up(): void
    {
        // PG 的 stock_records.type 实际是 varchar(20) 不是 enum，无需扩展
        // 原 MySQL ENUM 写法在 PG 不可用，down 留空
    }

    public function down(): void
    {
        // no-op
    }
};
