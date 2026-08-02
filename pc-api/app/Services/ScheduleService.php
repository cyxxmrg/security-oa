<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftGroupMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * V1.2.7 P1: 排班业务服务
 *
 * 把 ScheduleController 里复杂的业务逻辑（批量保存 / 班组批量 / 智能推荐 / 下一班次 / 统计）
 * 从 Controller 抽到 Service，Controller 只做参数编排和响应格式化。
 */
class ScheduleService
{
    /**
     * 批量保存排班 (覆盖已存在的 user+date)
     *
     * @param  array  $assignments  每条: {user_id, group_id?, date, shift_id, status?, note?}
     * @return array  [created => int, updated => int]
     */
    public function batchSave(array $assignments): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($assignments, &$created, &$updated) {
            foreach ($assignments as $a) {
                $rec = Schedule::where('user_id', $a['user_id'])
                    ->where('date', $a['date'])
                    ->first();

                $payload = [
                    'group_id'   => $a['group_id'] ?? null,
                    'shift_id'   => $a['shift_id'],
                    'status'     => $a['status']   ?? 'scheduled',
                    'note'       => $a['note']     ?? null,
                    'created_by' => Auth::id(),
                ];

                if ($rec) {
                    $rec->update($payload);
                    $updated++;
                } else {
                    $payload['user_id'] = $a['user_id'];
                    $payload['date']    = $a['date'];
                    Schedule::create($payload);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * 班组批量排班 (整组设同一班次)
     *
     * @return array [count => int] 实际排班数 (group_member × days)
     * @throws \RuntimeException 班组无成员
     */
    public function batchByGroup(int $groupId, int $shiftId, string $startDate, string $endDate, bool $skipWeekends = false): array
    {
        $userIds = ShiftGroupMember::where('group_id', $groupId)->pluck('user_id')->all();
        if (empty($userIds)) {
            throw new \RuntimeException('班组无成员');
        }

        $cursor = Carbon::parse($startDate);
        $end    = Carbon::parse($endDate);
        $assignments = [];

        while ($cursor->lte($end)) {
            $isWeekend = in_array($cursor->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true);
            if (!$skipWeekends || !$isWeekend) {
                foreach ($userIds as $uid) {
                    $assignments[] = [
                        'user_id'  => $uid,
                        'group_id' => $groupId,
                        'date'     => $cursor->format('Y-m-d'),
                        'shift_id' => $shiftId,
                    ];
                }
            }
            $cursor->addDay();
        }

        $count = count($assignments);
        DB::transaction(function () use ($assignments) {
            foreach ($assignments as $a) {
                Schedule::updateOrCreate(
                    ['user_id' => $a['user_id'], 'date' => $a['date']],
                    ['group_id' => $a['group_id'], 'shift_id' => $a['shift_id'], 'created_by' => Auth::id()],
                );
            }
        });

        return ['count' => $count];
    }

    /**
     * 智能排班建议 — 从过去 30 天打卡时间推断最匹配班次
     *
     * @return array<int, array{user_id, suggested_shift_id, suggested_shift_name, suggested_shift_color}>
     * @throws \RuntimeException 没有可用班次
     */
    public function smartSuggest(?int $userId, string $startDate): array
    {
        $shifts = Shift::where('is_active', true)->orderBy('start_time')->get();
        if ($shifts->isEmpty()) {
            throw new \RuntimeException('请先配置班次');
        }

        $userIds = $userId !== null ? [$userId] : User::pluck('id')->all();
        $since   = Carbon::parse($startDate)->subDays(30)->format('Y-m-d');

        $history = AttendanceRecord::whereIn('user_id', $userIds)
            ->where('date', '>=', $since)
            ->whereNotNull('clock_in')
            ->select('user_id', 'date', 'clock_in')
            ->get()
            ->groupBy('user_id');

        $suggestions = [];
        foreach ($userIds as $uid) {
            $records = $history[$uid] ?? collect();
            if ($records->isEmpty()) {
                $best = $shifts->firstWhere('code', 'day') ?? $shifts->first();
            } else {
                $totalMin = 0;
                $n = 0;
                foreach ($records as $r) {
                    $t = Carbon::parse($r->clock_in);
                    $totalMin += $t->hour * 60 + $t->minute;
                    $n++;
                }
                $avgMin = $n > 0 ? $totalMin / $n : 540; // 默认 9:00
                $best = $this->findClosestShift($shifts, $avgMin);
            }
            $suggestions[] = [
                'user_id'                => $uid,
                'suggested_shift_id'     => $best->id ?? null,
                'suggested_shift_name'   => $best->name ?? null,
                'suggested_shift_color'  => $best->color ?? null,
            ];
        }

        return $suggestions;
    }

    /**
     * 找与给定分钟数最接近的班次 (按 start_time)
     */
    private function findClosestShift($shifts, int $targetMin): ?Shift
    {
        $best = null;
        $bestDiff = PHP_INT_MAX;
        foreach ($shifts as $s) {
            [$h, $m] = explode(':', substr($s->start_time, 0, 5));
            $shiftMin = (int) $h * 60 + (int) $m;
            $diff = abs($shiftMin - $targetMin);
            if ($diff < $bestDiff) {
                $best = $s;
                $bestDiff = $diff;
            }
        }
        return $best;
    }

    /**
     * 当前用户的下一班次提醒
     *
     * @return array|null  [date, shift_name, shift_color, start_time, end_time, minutes_until_start] 或 null
     */
    public function nextReminder(?int $userId = null): ?array
    {
        $userId = $userId ?? Auth::id();

        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = today()->addDays($i)->format('Y-m-d');
        }

        $next = Schedule::with('shift')
            ->where('user_id', $userId)
            ->whereIn('date', $dates)
            ->orderBy('date')
            ->orderBy('id')
            ->first();

        if (!$next) {
            return null;
        }

        $shiftStart = $next->date->format('Y-m-d') . ' ' . $next->shift->start_time;
        $minutesUntil = Carbon::now()->diffInMinutes(Carbon::parse($shiftStart), false);

        return [
            'date'                => $next->date->format('Y-m-d'),
            'shift_name'          => $next->shift->name,
            'shift_color'         => $next->shift->color,
            'start_time'          => $next->shift->start_time,
            'end_time'            => $next->shift->end_time,
            'minutes_until_start' => $minutesUntil,
        ];
    }

    /**
     * 排班统计: 某月各班次使用次数 + 各员工班次数
     */
    public function monthlyStats(string $month): array
    {
        $start = $month . '-01';
        $end   = date('Y-m-t', strtotime($start));

        $byShift = DB::table('schedules')
            ->join('shifts', 'schedules.shift_id', '=', 'shifts.id')
            ->whereBetween('schedules.date', [$start, $end])
            ->groupBy('shifts.id', 'shifts.name', 'shifts.color')
            ->select('shifts.id', 'shifts.name', 'shifts.color', DB::raw('count(*) as cnt'))
            ->get();

        $byUser = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->whereBetween('schedules.date', [$start, $end])
            ->groupBy('users.id', 'users.name', 'users.username')
            ->select('users.id', 'users.name', 'users.username', DB::raw('count(*) as cnt'))
            ->orderByDesc('cnt')
            ->get();

        return [
            'month'    => $month,
            'by_shift' => $byShift,
            'by_user'  => $byUser,
            'total'    => array_sum($byShift->pluck('cnt')->all()),
        ];
    }
}
