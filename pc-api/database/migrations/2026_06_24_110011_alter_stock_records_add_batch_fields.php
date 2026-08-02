<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.2 stock_records 扩展：
 *  - parent_request_id: 拆单场景（一个采购需求被多次分批入库）
 *  - batch_no: 批次号
 *  - is_partial: 是否部分入库（true=部分，false=全部）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_records')) {
            return;
        }

        Schema::table('stock_records', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_records', 'parent_request_id')) {
                $table->unsignedBigInteger('parent_request_id')->nullable()
                    ->after('related_id')->comment('父需求单ID（拆单用）');
            }
            if (!Schema::hasColumn('stock_records', 'batch_no')) {
                $table->string('batch_no', 50)->nullable()
                    ->after('parent_request_id')->comment('批次号');
            }
            if (!Schema::hasColumn('stock_records', 'is_partial')) {
                $table->boolean('is_partial')->default(false)
                    ->after('batch_no')->comment('是否分批入库');
            }
        });

        Schema::table('stock_records', function (Blueprint $table) {
            $table->index('parent_request_id');
            $table->index('batch_no');
        });
    }

    public function down(): void
    {
        // 不回滚
    }
};
