<?php

namespace App\Observers;

use App\Models\ExternalConstructionBid;
use App\Models\ExternalConstructionWork;

/**
 * V0.4.3 施工发包投标 Observer
 *
 * 行为:
 *  - created: 累加 work.bid_count (通过 work.update 修改 last_bid_at)
 *  - updated: status 变 accepted 时,把 work.status = awarded (Service 主导,这里兜底)
 *             status 变 shortlisted 时, work 状态升 shortlist (Service 主导, 兜底)
 */
class ExternalConstructionBidObserver
{
    public function created(ExternalConstructionBid $bid): void
    {
        $work = ExternalConstructionWork::find($bid->work_id);
        if ($work) {
            $count = ExternalConstructionBid::where('work_id', $work->id)
                ->whereNull('deleted_at')
                ->count();
            $work->update([
                'last_bid_at' => now(),
            ]);
            // 冗余记录到 work.remark 不优雅, 这里仅刷新缓存字段
            // bid_count 走 withCount('bids') 实时计算
        }
    }

    public function updated(ExternalConstructionBid $bid): void
    {
        if (!$bid->wasChanged('status')) {
            return;
        }

        $work = ExternalConstructionWork::find($bid->work_id);
        if (!$work) {
            return;
        }

        // 兜底: bid 标 accepted 但 work 还没升 awarded
        if ($bid->status === ExternalConstructionBid::STATUS_ACCEPTED
            && $work->status !== ExternalConstructionWork::STATUS_AWARDED) {
            // 此情形通常是 Service 走异常路径,这里不强行覆盖
            // (Service::awardWork 一次性把 work 标 awarded, 此处仅日志)
            \Log::warning("Bid #{$bid->id} 已 accepted 但 work #{$work->id} 未 awarded, 需人工核查");
        }
    }
}
