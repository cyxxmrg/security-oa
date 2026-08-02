<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowUpRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * 跟进日历
 * GET /api/follow-ups/calendar?month=YYYY-MM[&user_id=&customer_id=]
 *
 * 数据源：follow_up_records（客户跟进表）
 * 状态分类：
 *   - done      已完成：created_at 在当月
 *   - scheduled 计划中：next_follow_up_date 在当月 且 created_at 不在当月
 *   - overdue   已逾期：next_follow_up_date < today
 *   同一 next_follow_up_date 在 calendar 中以单一 key 聚合，每条带 status
 */
class FollowUpCalendarController extends Controller
{
    // type enum -> 中文显示
    private const TYPE_LABELS = [
        'visit'  => '上门',
        'call'   => '电话',
        'online' => '微信', // 微信/邮件/短信/在线都映射为 微信/在线
        'other'  => '其他',
    ];

    // next_follow_up_date 是否为空 -> 沟通结果中文
    private const RESULT_DONE       = '已沟通';
    private const RESULT_PENDING    = '待跟进';
    private const RESULT_OVERDUE    = '客户忙'; // 占位语义，区别于"待跟进"

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month'       => 'required|date_format:Y-m',
            'user_id'     => 'nullable|integer',
            'customer_id' => 'nullable|integer',
        ]);

        try {
            $monthStart = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            throw ValidationException::withMessages(['month' => 'month 格式错误，需 YYYY-MM']);
        }
        $monthEnd     = $monthStart->copy()->endOfMonth();
        $today        = Carbon::today();
        $weekEnd      = $today->copy()->addDays(7);

        // ---- 1) 当月窗口内的全部记录 ----
        $query = FollowUpRecord::query()
            ->with(['customer:id,name', 'user:id,name']);

        if (!empty($data['user_id'])) {
            $query->where('user_id', (int) $data['user_id']);
        }
        if (!empty($data['customer_id'])) {
            $query->where('customer_id', (int) $data['customer_id']);
        }

        // 当月窗口：
        //   - created_at 落在当月（已完成）   OR
        //   - next_follow_up_date 落在当月（计划中 / 已逾期）
        $records = $query
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('created_at', [$monthStart, $monthEnd])
                  ->orWhereBetween('next_follow_up_date', [
                      $monthStart->toDateString(),
                      $monthEnd->toDateString(),
                  ]);
            })
            ->get();

        // ---- 2) 聚合 summary + calendar ----
        $summary = [
            'total_count' => $records->count(),
            'by_type'     => [],
            'by_result'   => [],
            'by_day'      => [],
        ];
        $calendar = [];   // 'YYYY-MM-DD' => [ {...} ]
        $todayList = [];  // today + next 7 days 的计划跟进

        foreach ($records as $r) {
            $typeLabel  = self::TYPE_LABELS[$r->type] ?? '其他';
            $createdAt  = $r->created_at ? Carbon::parse($r->created_at) : null;
            $nextAt     = $r->next_follow_up_date ? Carbon::parse($r->next_follow_up_date) : null;

            $inMonthCreate = $createdAt && $createdAt->betweenIncluded($monthStart, $monthEnd);
            $inMonthNext   = $nextAt && $nextAt->betweenIncluded($monthStart->startOfDay(), $monthEnd->endOfDay());

            // 状态判定（优先级：overdue > done > scheduled）
            //  - overdue   : next_follow_up_date < today
            //  - done      : created_at 在当月 且 没有预约下次（本次已完结）
            //  - scheduled : next_follow_up_date 在当月 且非 overdue
            if ($nextAt && $nextAt->lt($today)) {
                $status = 'overdue';
            } elseif ($inMonthCreate && empty($r->next_follow_up_date)) {
                $status = 'done';
            } else {
                $status = 'scheduled';
            }

            // by_type / by_result
            $summary['by_type'][$typeLabel]   = ($summary['by_type'][$typeLabel] ?? 0) + 1;
            $resultLabel = $this->resultLabel($status, $r);
            $summary['by_result'][$resultLabel] = ($summary['by_result'][$resultLabel] ?? 0) + 1;

            // by_day / calendar：优先用 next_follow_up_date（计划的归属日），
            //  scheduled/overdue 用 next；done（无 next）用 created_at
            $dayKey = null;
            if ($status !== 'done' && $nextAt) {
                $dayKey = $nextAt->toDateString();
            } elseif ($createdAt) {
                $dayKey = $createdAt->toDateString();
            } elseif ($nextAt) {
                $dayKey = $nextAt->toDateString();
            }
            if ($dayKey) {
                $summary['by_day'][$dayKey] = ($summary['by_day'][$dayKey] ?? 0) + 1;

                $calendar[$dayKey][] = [
                    'id'            => $r->id,
                    'customer_id'   => $r->customer_id,
                    'customer_name' => $r->customer->name ?? '',
                    'type'          => $typeLabel,
                    'result'        => $resultLabel,
                    'content'       => $r->content,
                    'owner_name'    => $r->user->name ?? '',
                    'status'        => $status,
                ];
            }

            // today_list：next_follow_up_date 落在 [today, today+7d]
            if ($nextAt && $nextAt->betweenIncluded($today, $weekEnd)) {
                $todayList[] = [
                    'id'             => $r->id,
                    'type'           => $nextAt->lt($today) ? 'overdue' : 'scheduled',
                    'customer_id'    => $r->customer_id,
                    'customer_name'  => $r->customer->name ?? '',
                    'content'        => $r->content,
                    'owner_name'     => $r->user->name ?? '',
                    'scheduled_at'   => $nextAt->format('Y-m-d H:i'),
                    'next_follow_at' => $nextAt->format('Y-m-d H:i'),
                ];
            }
        }

        // 排序：by_day / calendar 按日期升序
        ksort($summary['by_day']);
        ksort($calendar);

        // today_list 排序：先 overdue，再按日期
        usort($todayList, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'overdue' ? -1 : 1;
            }
            return strcmp($a['next_follow_at'], $b['next_follow_at']);
        });

        return response()->json([
            'code' => 0,
            'data' => [
                'month'     => $data['month'],
                'summary'   => $summary,
                'today_list' => $todayList,
                'calendar'  => $calendar,
            ],
        ]);
    }

    private function resultLabel(string $status, FollowUpRecord $r): string
    {
        if ($status === 'overdue') {
            return self::RESULT_OVERDUE;
        }
        // 没有 next_follow_up_date -> 已完结/已沟通；否则待跟进
        return empty($r->next_follow_up_date) ? self::RESULT_DONE : self::RESULT_PENDING;
    }
}
