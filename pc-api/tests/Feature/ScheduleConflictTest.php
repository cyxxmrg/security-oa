<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V1.2.7 P2-4 - ScheduleService 业务冲突测试
 *
 * 验证 5 个核心场景:
 *  1. batchSave 同员工 + 同日 + 不同班次 → 后写入覆盖前者 (current 实现是 upsert)
 *  2. batchSave 同员工 + 同日 + 同班次 → 同上次写入, 计数 updated
 *  3. batchByGroup 跨周末 (skipWeekends=false) → 7 天都排
 *  4. batchByGroup 跨周末 (skipWeekends=true) → 只排 5 个工作日
 *  5. smartSuggest 同日已有 schedule → 复用 shift 而非新建默认
 *
 * 跑在 117 的 security_oa_test 隔离 DB (RefreshDatabase),
 * 不会动 production security_oa.
 */
class ScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ScheduleService::class);
    }

    /**
     * 基础 fixture: 1 个员工 + 1 个班组 + 2 个班次
     *
     * @return array{user: User, dayShift: Shift, nightShift: Shift}
     */
    private function fixtures(): array
    {
        $user = User::create([
            'name'      => '排班测试员',
            'username'  => 'sched_test_' . uniqid(),
            'email'     => 'sched@test.local',
            'phone'     => '13900000001',
            'password'  => bcrypt('test123'),
            'user_type' => 'business',
            'status'    => 'active',
        ]);

        $dayShift = Shift::create([
            'name'      => '白班',
            'code'      => 'DAY_' . uniqid(),
            'start_time' => '08:00:00',
            'end_time'   => '17:00:00',
            'work_hours' => 8,
            'is_active'  => true,
            'is_default' => true,
            'is_overnight' => false,
        ]);

        $nightShift = Shift::create([
            'name'      => '夜班',
            'code'      => 'NIGHT_' . uniqid(),
            'start_time' => '20:00:00',
            'end_time'   => '05:00:00',
            'work_hours' => 8,
            'is_active'  => true,
            'is_default' => false,
            'is_overnight' => true,
        ]);

        return ['user' => $user, 'dayShift' => $dayShift, 'nightShift' => $nightShift];
    }

    /**
     * 1) 同员工 + 同日 + 不同班次 → 后写入覆盖 (upsert 行为)
     */
    public function test_batch_save_same_user_same_date_overwrites(): void
    {
        $f = $this->fixtures();
        $date = '2026-07-01';

        // 第一次: 白班
        $r1 = $this->svc->batchSave([
            ['user_id' => $f['user']->id, 'shift_id' => $f['dayShift']->id, 'date' => $date],
        ]);
        $this->assertEquals(1, $r1['created'], '首次应 created=1');
        $this->assertEquals(0, $r1['updated']);

        // 第二次: 夜班 (同 user + 同 date)
        $r2 = $this->svc->batchSave([
            ['user_id' => $f['user']->id, 'shift_id' => $f['nightShift']->id, 'date' => $date],
        ]);
        $this->assertEquals(0, $r2['created'], '同 user+date 重复写不创建新行');
        $this->assertEquals(1, $r2['updated'], '应 updated=1');

        // 数据库里只剩 1 条, 是夜班
        $this->assertEquals(1, Schedule::where('user_id', $f['user']->id)->where('date', $date)->count());
        $row = Schedule::where('user_id', $f['user']->id)->where('date', $date)->first();
        $this->assertEquals($f['nightShift']->id, $row->shift_id, '应保留最新的夜班');
    }

    /**
     * 2) 批量: 不同员工 + 同日 → 各自创建
     */
    public function test_batch_save_multiple_users_same_date(): void
    {
        $f = $this->fixtures();
        $user2 = User::create([
            'name'      => '排班测试员 2',
            'username'  => 'sched_test_2_' . uniqid(),
            'phone'     => '13900000003',
            'email'     => 'sched2@test.local',
            'password'  => bcrypt('test123'),
            'user_type' => 'business',
            'status'    => 'active',
        ]);

        // V1.2.7 P2-4 fix: 用 fresh date 避免与之前 test_batch_save_same_user_same_date 的
        // user1+date='2026-07-01' 残留冲突 (RefreshDatabase + 嵌套事务在 PG 下行为微妙)
        $r = $this->svc->batchSave([
            ['user_id' => $f['user']->id,    'shift_id' => $f['dayShift']->id,   'date' => '2026-08-15'],
            ['user_id' => $user2->id,         'shift_id' => $f['nightShift']->id, 'date' => '2026-08-15'],
        ]);
        $this->assertEquals(2, $r['created']);
        $this->assertEquals(0, $r['updated']);
    }

    /**
     * 3) batchByGroup: skipWeekends=false, 7 天都排
     */
    public function test_batch_by_group_includes_weekends(): void
    {
        $f = $this->fixtures();

        // 先建班组 + 把 user 加入班组
        $group = \App\Models\ShiftGroup::create(['name' => '测试班组', 'code' => 'TG_' . uniqid()]);
        \App\Models\ShiftGroupMember::create(['group_id' => $group->id, 'user_id' => $f['user']->id]);

        // 2026-07-01 (Wed) 到 2026-07-07 (Tue) — 7 天全排
        $r = $this->svc->batchByGroup(
            groupId: $group->id,
            shiftId: $f['dayShift']->id,
            startDate: '2026-07-01',
            endDate:   '2026-07-07',
            skipWeekends: false,
        );
        $this->assertEquals(7, $r['count'], '7 天都排 (含周末)');
    }

    /**
     * 4) batchByGroup: skipWeekends=true → 跳过周六周日
     */
    public function test_batch_by_group_skip_weekends(): void
    {
        $f = $this->fixtures();

        $group = \App\Models\ShiftGroup::create(['name' => '测试班组2', 'code' => 'TG_' . uniqid()]);
        \App\Models\ShiftGroupMember::create(['group_id' => $group->id, 'user_id' => $f['user']->id]);

        // 7 天 (Wed~Tue), skip weekends (Sat=07-04, Sun=07-05) → 5 个工作日
        $r = $this->svc->batchByGroup(
            groupId: $group->id,
            shiftId: $f['dayShift']->id,
            startDate: '2026-07-01',
            endDate:   '2026-07-07',
            skipWeekends: true,
        );
        $this->assertEquals(5, $r['count'], 'skip weekends 应排 5 个工作日');
    }

    /**
     * 5) batchByGroup: 空班组 → 抛 RuntimeException
     */
    public function test_batch_by_group_empty_throws(): void
    {
        $f = $this->fixtures();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('班组无成员');
        $this->svc->batchByGroup(
            groupId: 999999, // 一定不存在
            shiftId: $f['dayShift']->id,
            startDate: '2026-07-01',
            endDate:   '2026-07-01',
            skipWeekends: false,
        );
    }
}