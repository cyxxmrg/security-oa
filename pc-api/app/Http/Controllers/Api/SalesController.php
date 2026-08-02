<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Referrer;
use App\Models\ProjectPool;
use App\Models\ReferralSettlement;
use App\Models\SalesFollowUp;
use App\Models\SalesFollowUpAttachment;
use App\Services\SalesService;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 销售前链路 P1 — V1.2.7d 瘦身后只做 HTTP 路由
 *
 * 业务全部委托给 App\Services\SalesService
 * 仅保留：
 *   1) 参数接收 (validate)
 *   2) 调用 service
 *   3) 翻译业务异常 → JSON 错误码
 */
class SalesController extends Controller
{
    public function __construct(private SalesService $svc) {}

    // ============================================================
    // === 商机 Opportunity ===
    // ============================================================

    public function oppsIndex(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginateOpps($request, $request->user())]);
    }

    /**
     * 看板视图轻量API — 只用 QueryBuilder 返回看板所需字段，避免 Eloquent 全量序列化开销
     */
    public function oppsKanban(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->kanbanOpps($request, $request->user())]);
    }

    public function oppsShow(Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->showOpp($opp)]);
    }

    public function oppsStageOptions(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => SalesService::oppStageOptions()]);
    }

    public function oppsFunnel(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->oppFunnel($request)]);
    }

    public function oppsLostReasons(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => SalesService::oppLostReasons()]);
    }

    public function oppsStore(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->createOpp($request)]);
    }

    public function oppsUpdate(Request $request, Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->updateOpp($request, $opp)]);
    }

    public function oppsDestroy(Opportunity $opp): JsonResponse
    {
        try {
            $this->svc->destroyOpp($opp);
            return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function oppsUpdateStage(Request $request, Opportunity $opp): JsonResponse
    {
        $request->validate(['stage' => 'required|string']);
        try {
            return response()->json(['code' => 0, 'data' => $this->svc->updateOppStage($opp, $request->stage)]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function oppsMarkWon(Request $request, Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->markOppWon($request, $opp)]);
    }

    public function oppsMarkLost(Request $request, Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->markOppLost($request, $opp)]);
    }

    public function oppsRevive(Request $request, Opportunity $opp): JsonResponse
    {
        try {
            return response()->json(['code' => 0, 'data' => $this->svc->reviveOpp($opp)]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function oppsWin(Request $request, Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->winOpp($opp)]);
    }

    public function oppsLose(Request $request, Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->loseOpp($opp)]);
    }

    public function oppsHold(Request $request, Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->holdOpp($request, $opp)]);
    }

    public function oppsMoveToProjectPool(Request $request, Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->moveOppToProjectPool($request, $opp)]);
    }

    public function oppsAssign(Request $request, Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->assignOpp($request, $opp)]);
    }

    // ============================================================
    // === 报价单 Quotation ===
    // ============================================================

    public function quotesIndex(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginateQuotes($request)]);
    }

    public function quotesShow(Quotation $quote): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->showQuote($quote)]);
    }

    public function quotesStatusOptions(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => SalesService::quoteStatusOptions()]);
    }

    public function quotesStore(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->createQuote($request)]);
    }

    public function quotesUpdate(Request $request, Quotation $quote): JsonResponse
    {
        try {
            return response()->json(['code' => 0, 'data' => $this->svc->updateQuote($request, $quote)]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function quotesDestroy(Quotation $quote): JsonResponse
    {
        try {
            $this->svc->destroyQuote($quote);
            return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function quotesStoreItems(Request $request, Quotation $quote): JsonResponse
    {
        try {
            return response()->json(['code' => 0, 'data' => $this->svc->storeQuoteItems($request, $quote)]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function quotesUpdateStatus(Request $request, Quotation $quote): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->updateQuoteStatus($request, $quote)]);
    }

    public function quotesNewVersion(Quotation $quote): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->newQuoteVersion($quote)]);
    }

    public function referrersIndex(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginateReferrers($request)]);
    }

    public function referrersShow(Referrer $referrer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->showReferrer($referrer)]);
    }

    public function referrersStore(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->createReferrer($request)]);
    }

    public function referrersUpdate(Request $request, Referrer $referrer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->updateReferrer($request, $referrer)]);
    }

    public function referrersDestroy(Referrer $referrer): JsonResponse
    {
        $this->svc->destroyReferrer($referrer);
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function poolIndex(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginatePool($request)]);
    }

    public function poolShow(ProjectPool $pool): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->showPool($pool)]);
    }

    public function poolUpdate(Request $request, ProjectPool $pool): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->updatePool($request, $pool)]);
    }

    public function poolConvertToProject(Request $request, ProjectPool $pool): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->convertPoolToProject($request, $pool)]);
    }

    public function followUpsIndex(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginateFollowUps($request)]);
    }

    public function followUpsShow(SalesFollowUp $followUp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->showFollowUp($followUp)]);
    }

    public function followUpsStore(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->createFollowUp($request)]);
    }

    public function followUpsUpdate(Request $request, SalesFollowUp $followUp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->updateFollowUp($request, $followUp)]);
    }

    public function followUpsDestroy(SalesFollowUp $followUp): JsonResponse
    {
        $this->svc->destroyFollowUp($followUp);
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function followUpsUploadAttachment(Request $request, SalesFollowUp $followUp, FileUploadService $uploader): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->uploadFollowUpAttachment($request, $followUp, $uploader)]);
    }

    public function followUpsDownloadAttachment(SalesFollowUpAttachment $att)
    {
        return $this->svc->downloadFollowUpAttachment($att);
    }

    public function followUpsDeleteAttachment(SalesFollowUpAttachment $att): JsonResponse
    {
        $this->svc->destroyFollowUpAttachment($att);
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function oppsQuotationsIndex(Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->oppQuotations($opp)]);
    }

    public function oppsQuotationsStore(Request $request, Opportunity $opp): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->createOppQuotation($request, $opp)]);
    }

    public function quotationsShow(Quotation $quotation): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->showQuote($quotation)]);
    }

    public function quotationsUpdate(Request $request, Quotation $quotation): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->updateQuote($request, $quotation)]);
    }

    public function quotationsDestroy(Quotation $quotation): JsonResponse
    {
        $this->svc->destroyQuote($quotation);
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function quotationsAccept(Quotation $quotation): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->acceptQuote($quotation)]);
    }

    public function quotationsReject(Request $request, Quotation $quotation): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->rejectQuote($request, $quotation)]);
    }

    public function quotationsRevise(Request $request, Quotation $quotation): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->reviseQuote($request, $quotation)]);
    }

    public function referralSettlementsIndex(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginateReferralSettlements($request)]);
    }

    public function referralSettlementsShow(ReferralSettlement $settlement): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->showReferralSettlement($settlement)]);
    }

    public function referralSettlementsApprove(Request $request, ReferralSettlement $settlement): JsonResponse
    {
        try {
            return response()->json(['code' => 0, 'data' => $this->svc->approveReferralSettlement($request, $settlement)]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function referralSettlementsPay(Request $request, ReferralSettlement $settlement): JsonResponse
    {
        try {
            return response()->json(['code' => 0, 'data' => $this->svc->payReferralSettlement($request, $settlement)]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function referralSettlementsStats(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->referralSettlementsStats($request)]);
    }

    /**
     * 商机 → 创建项目 (V1.2.12g)
     * 自动标记成交 + 创建项目池记录 + 创建项目壳 + 返回 project_id
     */
    public function oppsConvertToProject(Request $request, Opportunity $opp): JsonResponse
    {
        if ($opp->stage !== 'won') {
            $opp->update(['stage' => 'won', 'probability' => 100, 'last_contact_at' => now()]);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($opp) {
            // 1) 创建项目池记录（让 /project/pool 能看到）
            $pool = \App\Models\ProjectPool::create(
                [
                    'pool_no'        => 'POOL-' . date('YmdHis') . random_int(100, 999),
                    'opportunity_id' => $opp->id,
                    'customer_id'    => $opp->customer_id,
                    'name'           => $opp->name ?: '项目',
                    'status'         => 'pending',
                ]
            );

            // 2) 创建项目壳
            $project = Project::create([
                'name'        => $opp->name ?: ($opp->customer?->name ?: '') . '项目',
                'customer_id' => $opp->customer_id,
                'type'        => $opp->type ?: 'comprehensive',
                'manager_id'  => $opp->sales_id,
                'description' => $opp->notes,
                'stage'       => 'mobilization',
                'status'      => 'pending',
                'progress'    => 0,
                'priority'    => 'normal',
                'start_date'  => $opp->expected_sign_date ?: now(),
                'end_date'    => $opp->expected_sign_date ? now()->addMonths(3) : now()->addMonths(3),
                // V1.2.12k: 商机预估金额同步到项目预算 (设备费)
                'budget_device' => $opp->estimated_amount ?? 0,
            ]);

            // 3) 池记录关联创建的 project
            $pool->update([
                'related_project_id' => $project->id,
                'status'             => 'active',
                'contract_amount'    => $opp->estimated_amount,
                'signed_at'          => now(),
            ]);

            $opp->update(['pool_id' => $pool->id, 'stage' => 'won']);

            return response()->json([
                'code' => 0,
                'data' => [
                    'project_id' => $project->id,
                    'name'       => $project->name,
                    'project_no' => $project->project_no,
                    'pool_id'    => $pool->id,
                ],
            ]);
        });
    }
}
