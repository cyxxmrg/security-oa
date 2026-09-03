<?php

namespace App\Services;

use App\Models\ExternalConstructionBid;
use App\Models\ExternalConstructionWork;
use App\Models\ProjectActualCost;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.3 施工发包服务
 *
 * 关键流程:
 *  - publishWork:  发布发包 (status=open)
 *  - submitBid:     投标 (外部供应商走 supplier.only)
 *  - shortlistBids: 入围
 *  - evaluateBid:   评标
 *  - awardWork:     中标 → 写 supplier_payables (type=construction) + project_actual_costs (category=outsource)
 *
 * 与 ExternalQuoteService 区别:
 *  - ECW 不走 PO,直接走 应付 + 实际成本
 *  - 复用 supplier 账号投标, 但用独立 bid 表
 */
class ExternalConstructionService
{
    /**
     * 生成发包编号 ECW-YYYY-NNNN
     */
    public function generateWorkCode(): string
    {
        $year   = now()->format('Y');
        $prefix = "ECW-{$year}-";
        $next = NumberSequenceService::next(
            "external-construction-work:{$year}",
            fn () => (int) ExternalConstructionWork::where('code', 'like', $prefix . '%')
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(code FROM 'ECW-[0-9]{4}-([0-9]+)') AS INTEGER)), 0) as seq")
                ->value('seq')
        );
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 生成投标编号 ECWB-YYYY-NNNN
     */
    public function generateBidCode(): string
    {
        $year   = now()->format('Y');
        $prefix = "ECWB-{$year}-";
        $next = NumberSequenceService::next(
            "external-construction-bid:{$year}",
            fn () => (int) ExternalConstructionBid::withTrashed()
                ->where('code', 'like', $prefix . '%')
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(code FROM 'ECWB-[0-9]{4}-([0-9]+)') AS INTEGER)), 0) as seq")
                ->value('seq')
        );
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 发布发包
     */
    public function publishWork(int $projectId, array $data, int $userId): ExternalConstructionWork
    {
        return DB::transaction(function () use ($projectId, $data, $userId) {
            return ExternalConstructionWork::create([
                'project_id'        => $projectId,
                'code'              => $this->generateWorkCode(),
                'title'             => $data['title'],
                'work_scope'        => $data['work_scope']    ?? $data['description'] ?? null,
                'estimated_budget'  => $data['estimated_budget'] ?? $data['budget'] ?? $data['budget_amount'] ?? 0,
                'bid_deadline'      => $data['deadline']       ?? $data['bid_deadline'] ?? null,
                'start_date'        => $data['start_date']     ?? null,
                'end_date'          => $data['end_date']       ?? null,
                'required_skills'   => $data['required_skills'] ?? $data['requirements'] ?? [],
                'attachments'       => $data['attachments']    ?? [],
                'status'            => $data['status']         ?? ExternalConstructionWork::STATUS_OPEN,
                'created_by'        => $userId,
            ]);
        });
    }

    public function updateWork(int $workId, array $data): ExternalConstructionWork
    {
        return DB::transaction(function () use ($workId, $data) {
            $work = ExternalConstructionWork::lockForUpdate()->findOrFail($workId);
            if (!in_array($work->status, [
                ExternalConstructionWork::STATUS_DRAFT,
                ExternalConstructionWork::STATUS_OPEN,
            ], true)) {
                throw new \RuntimeException('只有草稿或投标中的发包可编辑');
            }

            $payload = [];
            if (array_key_exists('title', $data)) {
                $payload['title'] = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $payload['work_scope'] = $data['description'];
            }
            if (array_key_exists('work_scope', $data)) {
                $payload['work_scope'] = $data['work_scope'];
            }
            if (array_key_exists('budget', $data)) {
                $payload['estimated_budget'] = $data['budget'];
            }
            if (array_key_exists('estimated_budget', $data)) {
                $payload['estimated_budget'] = $data['estimated_budget'];
            }
            if (array_key_exists('deadline', $data)) {
                $payload['bid_deadline'] = $data['deadline'];
            }
            if (array_key_exists('bid_deadline', $data)) {
                $payload['bid_deadline'] = $data['bid_deadline'];
            }
            if (array_key_exists('requirements', $data)) {
                $payload['required_skills'] = is_array($data['requirements'])
                    ? $data['requirements']
                    : ($data['requirements'] === null ? null : [$data['requirements']]);
            }
            if (array_key_exists('required_skills', $data)) {
                $payload['required_skills'] = $data['required_skills'];
            }

            if ($payload) {
                $work->update($payload);
            }

            return $work->fresh();
        });
    }

    /**
     * 投标
     */
    public function submitBid(
        int $workId,
        int $supplierId,
        int $bidderUserId,
        array $data
    ): ExternalConstructionBid {
        return DB::transaction(function () use ($workId, $supplierId, $bidderUserId, $data) {
            $work = ExternalConstructionWork::lockForUpdate()->findOrFail($workId);
            if ($work->status !== ExternalConstructionWork::STATUS_OPEN) {
                throw new \RuntimeException('该发包已截止/取消,不可投标');
            }
            if ($work->bid_deadline && now()->gt($work->bid_deadline)) {
                throw new \RuntimeException('已过投标截止时间');
            }

            // V0.4.4 简化: 接受所有 active 供应商
            $supplier = Supplier::findOrFail($supplierId);
            if (!in_array($supplier->type, ['outsource', 'material', 'service', 'labor'], true)) {
                throw new \RuntimeException('仅施工/物料/服务/劳务类供应商可参与投标');
            }
            if ($supplier->status !== 'active') {
                throw new \RuntimeException('该供应商账号未激活');
            }

            // 防重: 同一 supplier 同一 work 不可多次投标
            $exists = ExternalConstructionBid::where('work_id', $workId)
                ->where('supplier_id', $supplierId)
                ->exists();
            if ($exists) {
                throw new \RuntimeException('该供应商已投标,不可重复');
            }

            $bidAmount   = (float) ($data['bid_amount']   ?? 0);
            $bidDays     = (int) ($data['bid_days']      ?? $data['duration_days'] ?? 0);

            return ExternalConstructionBid::create([
                'work_id'             => $workId,
                'supplier_id'         => $supplierId,
                'bidder_user_id'      => $bidderUserId,
                'code'                => $this->generateBidCode(),
                'bid_amount'          => $bidAmount,
                'bid_days'            => $bidDays,
                'technical_proposal'  => $data['technical_proposal'] ?? $data['proposal'] ?? null,
                'construction_plan'   => $data['construction_plan']   ?? $data['work_plan'] ?? null,
                'team_info'           => $data['team_info']           ?? $data['team_size'] ?? null,
                'attachments'         => $data['attachments']        ?? [],
                'status'              => 'submitted',
            ]);
        });
    }

    /**
     * 短名单
     *
     * @param int[] $bidIds
     */
    public function shortlistBids(int $workId, array $bidIds, int $userId): int
    {
        return DB::transaction(function () use ($workId, $bidIds, $userId) {
            $work = ExternalConstructionWork::lockForUpdate()->findOrFail($workId);
            if (!in_array($work->status, [
                ExternalConstructionWork::STATUS_OPEN,
                ExternalConstructionWork::STATUS_SHORTLIST,
            ], true)) {
                throw new \RuntimeException('当前发包状态不可入围');
            }

            $count = ExternalConstructionBid::where('work_id', $workId)
                ->whereIn('id', $bidIds)
                ->update([
                    'status'      => ExternalConstructionBid::STATUS_SHORTLISTED,
                    'evaluator_id' => $userId,
                    'evaluated_at' => now(),
                ]);

            if ($count > 0 && $work->status === ExternalConstructionWork::STATUS_OPEN) {
                $work->update(['status' => ExternalConstructionWork::STATUS_SHORTLIST]);
            }

            return $count;
        });
    }

    /**
     * 评标
     */
    public function evaluateBid(int $bidId, float $score, ?string $comment, int $userId): ExternalConstructionBid
    {
        return DB::transaction(function () use ($bidId, $score, $comment, $userId) {
            $bid = ExternalConstructionBid::lockForUpdate()->findOrFail($bidId);
            if ($bid->status === ExternalConstructionBid::STATUS_ACCEPTED) {
                throw new \RuntimeException('已中标的投标不可再评标');
            }
            if ($score < 0 || $score > 100) {
                throw new \InvalidArgumentException('评分必须在 0-100');
            }

            $bid->update([
                'evaluation_score'   => $score,
                'evaluation_comment' => $comment,
                'status'        => ExternalConstructionBid::STATUS_EVALUATED,
                'evaluator_id'  => $userId,
                'evaluated_at'  => now(),
            ]);

            return $bid->fresh();
        });
    }

    /**
     * 中标 (核心流程)
     *
     * 1. bid.status = accepted
     * 2. work.status = awarded, work.awarded_supplier_id, work.awarded_bid_id
     * 3. supplier_payables (source_type=construction) [大哥拍板]
     * 4. project_actual_costs (category=outsource) [大哥拍板]
     * 5. 其他 bid.status = rejected
     */
    public function awardWork(int $workId, int $bidId, int $awardedBy): array
    {
        return DB::transaction(function () use ($workId, $bidId, $awardedBy) {
            $work = ExternalConstructionWork::lockForUpdate()->findOrFail($workId);
            $bid  = ExternalConstructionBid::where('work_id', $workId)->lockForUpdate()->findOrFail($bidId);

            if ($work->status === ExternalConstructionWork::STATUS_AWARDED) {
                throw new \RuntimeException('该发包已定标');
            }
            if ($bid->status === ExternalConstructionBid::STATUS_REJECTED) {
                throw new \RuntimeException('该投标已驳回,不可中标');
            }

            // 1) bid 中标
            $bid->update([
                'status'        => 'accepted',
                'evaluator_id'  => $awardedBy,
                'evaluated_at'  => now(),
            ]);

            // 2) work 标记
            $work->update([
                'status'              => 'awarded',
                'awarded_supplier_id' => $bid->supplier_id,
                'awarded_bid_id'      => $bid->id,
                'awarded_amount'      => $bid->bid_amount,
                'updated_at'          => now(),
            ]);

            // 3) 其他未中标的 bid 全部 rejected
            ExternalConstructionBid::where('work_id', $workId)
                ->where('id', '<>', $bid->id)
                ->where('status', '!=', ExternalConstructionBid::STATUS_REJECTED)
                ->update(['status' => ExternalConstructionBid::STATUS_REJECTED]);

            // 4) 创建 supplier_payable (type=construction)
            $payable = SupplierPayable::firstOrCreate([
                'source_type'  => 'construction',
                'source_id'    => $bid->id,
            ], [
                'supplier_id'  => $bid->supplier_id,
                'project_id'   => $work->project_id,
                'ref_no'       => $work->code,
                'amount'       => $bid->bid_amount,
                'paid_amount'  => 0,
                'status'       => SupplierPayable::STATUS_PENDING,
                'note'         => "由施工发包 {$work->code} / 投标 {$bid->code} 自动生成",
                'created_by'   => $awardedBy,
            ]);

            // 5) 创建 project_actual_cost (category=outsource) [大哥拍板]
            $supplierName = $bid->supplier->name ?? ('供应商#' . $bid->supplier_id);
            $actual = ProjectActualCost::updateOrCreate(
                [
                    'source_type' => 'construction_work',
                    'source_id'   => $work->id,
                    'category'    => 'outsource',
                ],
                [
                    'project_id'  => $work->project_id,
                    'amount'      => $bid->bid_amount,
                    'cost_date'   => now()->toDateString(),
                    'description' => "施工发包中标: {$work->title} ({$supplierName})",
                    'metadata'    => [
                        'work_code'   => $work->code,
                        'bid_code'    => $bid->code,
                        'supplier_id' => $bid->supplier_id,
                        'payable_id'  => $payable->id,
                    ],
                ]
            );

            return [
                'work'    => $work->fresh(['awardedSupplier', 'awardedBid']),
                'bid'     => $bid->fresh('supplier'),
                'payable' => $payable,
                'actual'  => $actual,
            ];
        });
    }

    /**
     * 关闭 / 取消发包
     */
    public function cancelWork(int $workId, ?string $reason = null): ExternalConstructionWork
    {
        return DB::transaction(function () use ($workId, $reason) {
            $work = ExternalConstructionWork::lockForUpdate()->findOrFail($workId);
            if ($work->status === ExternalConstructionWork::STATUS_AWARDED) {
                throw new \RuntimeException('已定标的发包不可取消');
            }
            $work->update([
                'status' => ExternalConstructionWork::STATUS_CANCELLED,
                'remark' => $reason
                    ? ($work->remark ? $work->remark . "\n[取消] " . $reason : "[取消] " . $reason)
                    : $work->remark,
            ]);
            return $work->fresh();
        });
    }

    /**
     * 发包列表
     */
    public function listWorks(int $projectId, array $filters = []): array
    {
        $q = ExternalConstructionWork::with([
            'project:id,name,project_no',
            'creator:id,name',
            'awardedSupplier:id,name,code',
        ])->withCount('bids');
        // V1.2.9u fix: 不传 project_id 时不应用 project_id 过滤, 列出全部
        if ($projectId > 0 && empty($filters['_all_projects'])) {
            $q->where('project_id', $projectId);
        }

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $q->where(function ($w) use ($kw) {
                $w->where('code', 'like', "%{$kw}%")
                  ->orWhere('title', 'like', "%{$kw}%");
            });
        }

        $total = (clone $q)->count();
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $size  = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $items = $q->orderByDesc('id')->skip(($page - 1) * $size)->take($size)->get();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * 投标列表
     */
    public function listBids(int $workId, array $filters = []): array
    {
        $q = ExternalConstructionBid::with(['supplier:id,name,code,rating', 'bidder:id,name', 'reviewer:id,name'])
            ->where('work_id', $workId);

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        $total = (clone $q)->count();
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $size  = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $items = $q->orderByDesc('evaluation_score')->orderByDesc('id')->skip(($page - 1) * $size)->take($size)->get();

        return ['items' => $items, 'total' => $total];
    }
}
