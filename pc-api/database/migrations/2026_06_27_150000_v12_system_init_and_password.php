<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2 admin 账号重做 — 4 件事:
 *   1. users 加 must_change_password 列 (system 账号首次登录强制改密)
 *   2. 软归档 admin 账号 (username 改 _archived_admin_, 清空 password, status 改 inactive)
 *      — 不物理删除: 保留 audit_logs / approval_records 等 FK 引用
 *   3. 新建 system 账号 (密码来自环境变量或安全随机生成)
 *   4. system_settings 加 system_initialized 标志 (wizard 完成状态)
 *
 * 注意: 必须用 Hash::make() 算密码, 不能写死 bcrypt hash (不同 Laravel 版本盐不同)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        // 1. 加 must_change_password 列
        if (!Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('must_change_password')->default(false)->after('user_type')
                    ->comment('true=首次登录必须改密');
            });
        }

        // 2. 软归档 admin 账号
        // 检查: 如果有 admin 用户, 改名 + 清密码 + inactive
        $adminExists = DB::table('users')->where('username', 'admin')->exists();
        if ($adminExists) {
            DB::table('users')->where('username', 'admin')->update([
                'username'  => '_archived_admin_',
                'name'      => '【已归档】原 admin',
                'email'     => '_archived_admin_@local.invalid',
                'phone'     => '00000000000',
                'password'  => '',  // 清空, 任何密码都不能登
                'status'    => 'inactive',
                'is_system' => false,
                'user_type' => 'business',
                'updated_at'=> now(),
            ]);
        }

        // 3. 新建 system 账号
        // 密码从 env 读取，未配置时安全随机生成。
        // V1.2.7 P0-2: 兜底改为随机密码 (env 未设时不会暴露 default 凭据)
        $systemPwd = env('SYSTEM_INIT_PASSWORD', \App\Support\TemporaryPassword::generate());
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{12,64}$/', $systemPwd)) {
            throw new \RuntimeException('SYSTEM_INIT_PASSWORD 必须为 12-64 位且同时包含字母和数字');
        }
        $systemExists = DB::table('users')->where('username', 'system')->exists();
        if (!$systemExists) {
            $deptId = DB::table('departments')->where('name', '总经办')->value('id');
            DB::table('users')->insert([
                'name'                  => '系统管理员',
                'username'              => 'system',
                'email'                 => 'system@local',
                'phone'                 => '13800000000',
                'password'              => Hash::make($systemPwd),
                'department_id'         => $deptId,
                'status'                => 'active',
                'gender'                => 'male',
                'is_system'             => true,
                'user_type'             => 'system',
                'must_change_password'  => true,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
            // P0-2: 如果使用了 env 显式配置密码, 提醒改密; 否则随机密码 (已在日志尾)
            if (env('SYSTEM_INIT_PASSWORD', false)) {
                \Illuminate\Support\Facades\Log::info('P0-2: system 账号使用 env 配置的密码, 部署后请修改');
            }
        }

        // 4. system_settings 加 system_initialized 标志
        // V1.2.7 P2-4 fix: 加 hasColumn 守卫, fresh migrate 时如果 group_name 不存在会失败
        // (虽然 patch migration 2026_06_28_120000 会先加这列, 但兜底更安全)
        if (Schema::hasTable('system_settings') && Schema::hasColumn('system_settings', 'group_name')) {
            $exists = DB::table('system_settings')->where('key', 'system_initialized')->exists();
            if (!$exists) {
                DB::table('system_settings')->insert([
                    'key'         => 'system_initialized',
                    'value'       => json_encode('false'),
                    'group_name'  => 'general',
                    'description' => 'V1.2: 初始化向导是否完成 (system 账号首次登录后由 wizard 标记)',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        // 还原 admin 账号 (如果有的话)
        if (!DB::table('users')->where('username', 'admin')->exists()) {
            $archived = DB::table('users')->where('username', '_archived_admin_')->first();
            if ($archived) {
                DB::table('users')->where('id', $archived->id)->update([
                    'username'  => 'admin',
                    'name'      => $archived->name,
                    'email'     => $archived->email,
                    'phone'     => $archived->phone,
                    'status'    => 'active',
                    'is_system' => true,
                    'user_type' => 'system',
                    'updated_at'=> now(),
                ]);
            }
        }
        // 删 system 账号
        DB::table('users')->where('username', 'system')->delete();
        // 删 must_change_password 列
        if (Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('must_change_password');
            });
        }
        // 删 system_initialized 设置
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->where('key', 'system_initialized')->delete();
        }
    }
};
