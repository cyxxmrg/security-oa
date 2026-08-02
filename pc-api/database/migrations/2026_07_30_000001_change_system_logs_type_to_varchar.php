<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修复 system_logs.type 的 ENUM CHECK 约束过严导致的 500 错误。
 *
 * 原定义: enum('login','logout','operation','error','login_failed') default 'operation'
 * 但业务代码实际写入了 'security' / 'system' / 'init' / 'update' 等约束外的值，
 * PostgreSQL 的 CHECK 约束会拒绝这些写入，触发 SQLSTATE[23514] 500。
 *
 * 将 type 改为 varchar(50)，彻底解除该约束，兼容任意日志类别。
 * 幂等: 已是 varchar/text 时跳过；仅对 enum(USER-DEFINED) 列执行变更。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('system_logs', 'type')) {
            return;
        }

        // 去掉过严的 CHECK 约束 (列出白名单值)。该约束独立于列类型存在,
        // 即便列已改为 varchar, 约束仍会拒绝 security/system/init/update 等值, 故必须显式删除。
        // IF EXISTS 保证全新部署 (varchar 无此约束) 重跑安全。
        DB::statement("ALTER TABLE system_logs DROP CONSTRAINT IF EXISTS system_logs_type_check;");

        $row = DB::selectOne(
            "SELECT data_type FROM information_schema.columns WHERE table_name = 'system_logs' AND column_name = 'type'"
        );
        $dataType = $row?->data_type ?? '';

        // 已是字符类型则跳过类型变更 (重跑安全)
        if (str_contains($dataType, 'character varying') || $dataType === 'text') {
            return;
        }

        // 1) 去掉绑定 enum 的默认值；2) 改列类型为 varchar(50)；3) 重新设默认
        DB::statement("ALTER TABLE system_logs ALTER COLUMN type DROP DEFAULT;");
        DB::statement("ALTER TABLE system_logs ALTER COLUMN type TYPE varchar(50) USING type::varchar;");
        DB::statement("ALTER TABLE system_logs ALTER COLUMN type SET DEFAULT 'operation';");
    }

    public function down(): void
    {
        // 回滚到 varchar 无约束状态风险高 (已有非白名单值)，此处不做逆向变更。
    }
};
