<?php

use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('oa:seed-admin {--password=}', function () {
    $password = $this->option('password') ?: TemporaryPassword::generate();
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{12,64}$/', $password)) {
        throw new \InvalidArgumentException('密码必须为 12-64 位且同时包含字母和数字');
    }
    $admin = \App\Models\User::firstOrCreate(
        ['username' => 'admin'],
        ['name' => '张建国', 'email' => 'admin@security-oa.com', 'phone' => '13800138000', 'password' => bcrypt($password), 'status' => 'active', 'must_change_password' => true]
    );
    $admin->assignRole('admin');

    $manager = \App\Models\User::firstOrCreate(
        ['username' => 'manager'],
        ['name' => '李明辉', 'email' => 'manager@security-oa.com', 'phone' => '13900139001', 'password' => bcrypt($password), 'status' => 'active', 'must_change_password' => true]
    );
    $manager->assignRole('manager');

    $user = \App\Models\User::firstOrCreate(
        ['username' => 'user'],
        ['name' => '王小红', 'email' => 'user@security-oa.com', 'phone' => '13700137002', 'password' => bcrypt($password), 'status' => 'active', 'must_change_password' => true]
    );
    $user->assignRole('user');

    $created = collect([$admin, $manager, $user])->filter->wasRecentlyCreated->pluck('username');
    if ($created->isNotEmpty()) {
        $this->warn("新建账号 {$created->join(', ')} 的一次性密码: {$password}");
    } else {
        $this->info('账号已存在，未修改密码');
    }
})->purpose('创建默认管理员账号');

Artisan::command('oa:seed-finance {--password=}', function () {
    $password = $this->option('password') ?: TemporaryPassword::generate();
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{12,64}$/', $password)) {
        throw new \InvalidArgumentException('密码必须为 12-64 位且同时包含字母和数字');
    }
    // v0.3.14 D2: 财务角色 + 默认账号
    $finance = \App\Models\User::firstOrCreate(
        ['username' => 'finance'],
        ['name' => '周会计', 'email' => 'finance@security-oa.com', 'phone' => '13700137003', 'password' => bcrypt($password), 'status' => 'active', 'must_change_password' => true]
    );
    $finance->assignRole('finance');

    $salesManager = \App\Models\User::firstOrCreate(
        ['username' => 'sales_mgr'],
        ['name' => '销售经理·陈', 'email' => 'sales_mgr@security-oa.com', 'phone' => '13700137004', 'password' => bcrypt($password), 'status' => 'active', 'must_change_password' => true]
    );
    $salesManager->assignRole('sales_manager');

    $created = collect([$finance, $salesManager])->filter->wasRecentlyCreated->pluck('username');
    if ($created->isNotEmpty()) {
        $this->warn("新建账号 {$created->join(', ')} 的一次性密码: {$password}");
    } else {
        $this->info('账号已存在，未修改密码');
    }
})->purpose('创建财务和销售经理账号 (v0.3.14 D2)');

Artisan::command('oa:seed-roles', function () {
    // v0.3.14 D2: 创建细粒度角色 (idempotent)
    $roles = [
        ['name' => 'admin',         'guard_name' => 'web'],
        ['name' => 'manager',       'guard_name' => 'web'],
        ['name' => 'sales_manager', 'guard_name' => 'web'],
        ['name' => 'finance',       'guard_name' => 'web'],
        ['name' => 'user',          'guard_name' => 'web'],
    ];
    foreach ($roles as $r) {
        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => $r['name'], 'guard_name' => $r['guard_name']],
        );
    }
    $this->info('已创建 5 个角色: admin / manager / sales_manager / finance / user');
})->purpose('创建细粒度角色 (v0.3.14 D2)');

Artisan::command('oa:seed-demo', function () {
    $this->call('oa:seed-admin');

    // 创建部门
    $techDept = \App\Models\Department::firstOrCreate(['name' => '技术部'], ['sort_order' => 1]);
    $salesDept = \App\Models\Department::firstOrCreate(['name' => '销售部'], ['sort_order' => 2]);
    $serviceDept = \App\Models\Department::firstOrCreate(['name' => '售后部'], ['sort_order' => 3]);
    $financeDept = \App\Models\Department::firstOrCreate(['name' => '财务部'], ['sort_order' => 4]);

    // 创建技能标签
    $skills = ['监控安装', '门禁调试', '网络配置', '报警系统', '云平台部署', '弱电施工', '综合布线', 'CAD设计'];
    foreach ($skills as $skill) {
        \App\Models\SkillTag::firstOrCreate(['name' => $skill]);
    }

    // 创建示例客户
    $customers = ['阳光小学', '中心医院', '科技园区A区', '万达工厂', '龙城商场'];
    foreach ($customers as $name) {
        \App\Models\Customer::firstOrCreate(['name' => $name], [
            'category' => 'normal', 'province' => '广东', 'city' => '深圳', 'district' => '南山区',
            'address' => '南山区科技路' . rand(1, 100) . '号', 'status' => 'active',
        ]);
    }

    $this->info('演示数据已创建');
})->purpose('创建演示数据');

// ============================================================
// === 定时任务 (v0.3.11 块四) — Laravel 11+ 需在 bootstrap/app.php withSchedule 注册 ===
// ============================================================

Artisan::command('oa:expire-quotations', function () {
    // 每日 01:00 把过期的报价单自动改 expired
    $count = \App\Models\Quotation::whereIn('status', ['draft', 'submitted', 'negotiating'])
        ->whereNotNull('valid_until')
        ->where('valid_until', '<', now()->toDateString())
        ->update(['status' => 'expired']);
    $this->info("已过期报价单: {$count} 个");
})->purpose('每日过期报价单自动改 expired');

Schedule::command('oa:expire-quotations')->dailyAt('01:00')->withoutOverlapping();

// ============================================================
// === 定时任务 (v0.3.14 B2) — 推荐人结算逾期 7 天提醒 ===
// ============================================================

Artisan::command('oa:remind-overdue-settlements {--days=7 : 多少天未审核视为逾期} {--dry-run : 仅统计不发送}', function () {
    $days = (int) $this->option('days');
    $dryRun = (bool) $this->option('dry-run');
    $cutoff = now()->subDays($days);

    // 1) 扫所有 pending 状态的结算单
    $overdue = \App\Models\ReferralSettlement::with(['referrer', 'opportunity'])
        ->where('status', 'pending')
        ->get()
        ->filter(function ($s) use ($cutoff) {
            // PG 字段 created_at 是 timestamp 无默认值，可能为 null
            // fallback: 用 id 推算时间戳（按 1 id = 100s 估算，最差情况几小时误差）
            $ts = $s->created_at
                ?? \Carbon\Carbon::createFromTimestamp(1700000000 + $s->id * 100);
            return $ts && $ts->lt($cutoff);
        });

    $this->info("待提醒结算单: {$overdue->count()} 个 (阈值: {$days} 天)");

    if ($overdue->isEmpty()) {
        return 0;
    }

    // 2) 找所有接收方（manager / finance / admin 角色）
    $receivers = \App\Models\User::role(['manager', 'finance', 'admin'])->get();
    if ($receivers->isEmpty()) {
        $this->warn('  无 manager/finance/admin 角色用户，跳过');
        return 0;
    }
    $this->info("  接收方: {$receivers->count()} 人");

    // 3) 24h 频控 + 发送
    $totalSent = 0;
    $totalSkipped = 0;
    foreach ($overdue as $s) {
        $referrerName = $s->referrer?->name ?? "推荐人#{$s->referrer_id}";
        $oppName = $s->opportunity?->name ?? "商机#{$s->opportunity_id}";
        // 优先用 created_at，回退到 id 推算
        $ts = $s->created_at
            ?? \Carbon\Carbon::createFromTimestamp(1700000000 + $s->id * 100);
        // Carbon: 显式使用 absolute (新版默认 true，旧版默认 false)
        $overdueDays = $ts->isPast()
            ? (int) abs($ts->diffInDays(now()))
            : 0;
        $title = "结算单逾期 {$overdueDays} 天待处理";
        $content = "推荐人「{$referrerName}」的居间费结算单（商机：{$oppName}，金额 ¥{$s->amount}）已挂起 {$overdueDays} 天，请尽快审核。";
        $level = $overdueDays >= 14 ? 'danger' : 'warning';
        $payload = [
            'settlement_id'  => $s->id,
            'opportunity_id' => $s->opportunity_id,
            'referrer_id'    => $s->referrer_id,
            'amount'         => (string) $s->amount,
            'overdue_days'   => $overdueDays,
            'action_url'     => "/sales/settlements?id={$s->id}",
        ];

        foreach ($receivers as $u) {
            $exists = \Illuminate\Notifications\DatabaseNotification::where('notifiable_id', $u->id)
                ->where('notifiable_type', \App\Models\User::class)
                ->where('type', 'settlement_overdue')
                ->where('data->settlement_id', $s->id)
                ->where('created_at', '>=', now()->subDay())
                ->exists();
            if ($exists) {
                $totalSkipped++;
                continue;
            }
            if ($dryRun) {
                $this->line("  [DRY] → {$u->name}: settlement #{$s->id} 金额 {$s->amount}");
                continue;
            }
            \Illuminate\Notifications\DatabaseNotification::create([
                'type'            => 'settlement_overdue',
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id'   => $u->id,
                'data'            => $payload,
                'title'           => $title,
                'content'         => $content,
                'level'           => $level,
                'sender_id'       => null,
                'read_at'         => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $totalSent++;
        }
    }

    $this->info(sprintf('已发送 %d 条提醒，跳过 %d 条 (24h 频控)', $totalSent, $totalSkipped));
    return 0;
})->purpose('推荐人结算单逾期 N 天提醒（数据库频道站内信）');

Schedule::command('oa:remind-overdue-settlements --days=7')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->name('settlement-overdue-reminder');

// ============================================================
// === V0.4.1 定时任务 — 项目预算 T+1 兜底同步（每日 02:00） ===
// ============================================================
Schedule::command('project:sync-actual-costs')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// ============================================================
// === V0.4.3 定时任务 — 施工日志漏报扫描（每日 22:00） ===
// ============================================================
Schedule::command('construction:scan-overdue-logs')
    ->dailyAt('22:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('construction-overdue-scan');

// ============================================================
// === V0.4.5 定时任务 — 质保期到期扫描（每日 22:00） ===
// ============================================================
Schedule::command('warranty:scan-expiry --within-days=30')
    ->dailyAt('22:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('warranty-expiry-scan');

// ============================================================
// === V0.5.3 定时任务 — 临时角色清理 + 到期提醒（每日 00:30） ===
// ============================================================
Schedule::command('oa:clean-expired-roles')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('clean-expired-roles');

// ============================================================
// === V0.8 定时任务 — 健康探针（每 5 分钟一次） ===
// ============================================================
Schedule::command('oa:health-check')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('health-probe');

// ============================================================
// === V1.2.7 E1 定时任务 — BI 物化视图刷新 (每日 02:30) ===
// ============================================================
// CONCURRENTLY 不锁表, 但要保证前一天已成功 (避免数据断层)
// 失败时 SlowRequestAlertJob 告警 (command 内部)
Schedule::command('analytics:refresh')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('analytics-mv-refresh');

Artisan::command('oa:purge-expired-personal-data {--dry-run}', function () {
    $cutoff = now()->subDays(max(1, (int) config('compliance.former_employee_retention_days')));
    $userIds = DB::table('employee_resignations')
        ->where('status', 'completed')
        ->where('last_work_day', '<=', $cutoff->toDateString())
        ->distinct()
        ->pluck('user_id');

    $auditCutoff = now()->subDays(max(1, (int) config('compliance.audit_log_retention_days')));
    $systemCutoff = now()->subDays(max(1, (int) config('compliance.system_log_retention_days')));
    $onboardingFileIds = DB::table('employee_onboardings')
        ->whereIn('user_id', $userIds)
        ->get(['id_card_file_id', 'driver_license_file_id', 'education_file_id', 'contract_file_id'])
        ->flatMap(fn ($row) => (array) $row)
        ->filter()
        ->unique()
        ->values();
    $resignationFileIds = DB::table('employee_resignations')
        ->whereIn('user_id', $userIds)
        ->whereNotNull('resign_certificate_file_id')
        ->pluck('resign_certificate_file_id');
    $personalFileIds = $onboardingFileIds
        ->merge($resignationFileIds)
        ->unique()
        ->values();
    $personalFiles = DB::table('disk_files')->whereIn('id', $personalFileIds)->get(['id', 'path']);
    $this->info("待匿名化离职员工: {$userIds->count()}");
    $this->info("待删除个人证件文件: {$personalFiles->count()}");
    if ($this->option('dry-run')) {
        return 0;
    }

    DB::transaction(function () use ($userIds, $personalFileIds, $personalFiles, $auditCutoff, $systemCutoff) {
        foreach ($personalFiles as $file) {
            $disk = Storage::disk('attachments');
            if ($disk->exists($file->path) && !$disk->delete($file->path)) {
                throw new \RuntimeException("个人证件文件删除失败: disk_file #{$file->id}");
            }
        }

        foreach ($userIds as $userId) {
            DB::table('users')->where('id', $userId)->update([
                'name' => "已离职员工#{$userId}",
                'username' => "deleted_user_{$userId}",
                'email' => null,
                'phone' => "DELETED-{$userId}",
                'id_card' => null,
                'avatar' => null,
                'gender' => 'other',
                'department_id' => null,
                'position_id' => null,
                'password' => Hash::make(Str::random(64)),
                'status' => 'inactive',
                'last_login_at' => null,
                'last_login_ip' => null,
                'remember_token' => null,
                'updated_at' => now(),
            ]);
            DB::table('employee_profiles')->where('user_id', $userId)->update([
                'employee_no' => "DELETED-{$userId}",
                'contract_start' => null, 'contract_end' => null,
                'base_salary' => 0, 'salary_allowance' => 0,
                'bank_name' => null, 'bank_account' => null,
                'emergency_contact' => null, 'emergency_phone' => null, 'notes' => null,
                'updated_at' => now(),
            ]);
            DB::table('employee_onboardings')->where('user_id', $userId)->update([
                'id_card_no' => null, 'driver_license_no' => null,
                'id_card_file_id' => null, 'driver_license_file_id' => null,
                'education_file_id' => null, 'contract_file_id' => null,
                'driver_license_expire' => null, 'education_level' => null,
                'education_school' => null, 'education_major' => null, 'remark' => null,
                'updated_at' => now(),
            ]);
            DB::table('employee_resignations')->where('user_id', $userId)->update([
                'reason' => '已按保留策略匿名化',
                'handover_note' => null, 'assets_checklist' => null,
                'final_salary_amount' => null, 'leave_balance_payout' => null,
                'severance_pay' => null, 'total_settlement' => null,
                'paid_method' => null, 'resign_certificate_file_id' => null,
                'remark' => null,
                'updated_at' => now(),
            ]);
            DB::table('personal_access_tokens')
                ->where('tokenable_type', \App\Models\User::class)
                ->where('tokenable_id', $userId)
                ->delete();
        }
        DB::table('disk_files')->whereIn('id', $personalFileIds)->delete();
        DB::table('audit_logs')->where('created_at', '<', $auditCutoff)->delete();
        DB::table('system_logs')->where('created_at', '<', $systemCutoff)->delete();
    });

    $this->info('个人数据匿名化及日志保留清理完成');
    return 0;
})->purpose('按合规保留期限匿名化离职人员并清理过期日志');

Schedule::command('oa:purge-expired-personal-data')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('compliance-data-retention');
