<?php

/**
 * V1.2.16: 给 finance_payments 加 transfer_group_id + is_internal_transfer 字段
 * 用于支持「内部转账」二级菜单: 转账时同一次转账两条记录共享同一 group_id,
 * 详情页/列表按 group_id 聚合显示成一行.
 *
 * 历史数据迁移: method='内部转账' 的配对记录 (一正一负, 同 remark, 同 payment_date)
 * 自动生成 group_id 并填上 is_internal_transfer=true.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('finance_payments', 'transfer_group_id')) {
                $table->string('transfer_group_id', 64)->nullable()->after('voucher_no');
                $table->index('transfer_group_id');
            }
            if (!Schema::hasColumn('finance_payments', 'is_internal_transfer')) {
                $table->boolean('is_internal_transfer')->default(false)->after('transfer_group_id');
            }
        });

        // 历史数据迁移: 把 method='内部转账' 且 payable_id/receivable_id 都为空的配对记录 (一正一负) 标 is_internal_transfer + 填 group_id
        // 配对规则: 同 remark + 同 payment_date, 且 amount 一正一负
        DB::statement("
            UPDATE finance_payments SET is_internal_transfer = true
            WHERE method = '内部转账'
              AND receivable_id IS NULL
              AND payable_id IS NULL
              AND is_internal_transfer = false
        ");

        // 按 (remark, payment_date) 分组, 给每组的正负两条分配相同的 group_id
        $pairs = DB::select("
            SELECT id, remark, payment_date
            FROM finance_payments
            WHERE is_internal_transfer = true
              AND transfer_group_id IS NULL
            ORDER BY remark, payment_date, id
        ");
        $grouped = [];
        $seq = 0;
        foreach ($pairs as $p) {
            $key = $p->remark . '|' . $p->payment_date;
            if (!isset($grouped[$key])) {
                $seq++;
                $grouped[$key] = 'TRF-' . date('Ymd', strtotime($p->payment_date)) . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            }
            DB::table('finance_payments')
                ->where('id', $p->id)
                ->update(['transfer_group_id' => $grouped[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            if (Schema::hasColumn('finance_payments', 'is_internal_transfer')) {
                $table->dropColumn('is_internal_transfer');
            }
            if (Schema::hasColumn('finance_payments', 'transfer_group_id')) {
                $table->dropIndex(['transfer_group_id']);
                $table->dropColumn('transfer_group_id');
            }
        });
    }
};