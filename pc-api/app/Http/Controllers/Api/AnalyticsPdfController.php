<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsQueryService;
use App\Support\AuthScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * V1.2.7 E2: BI 报表 PDF 导出
 *
 * 端点: GET /api/analytics/export/pdf
 * 参数:
 *   - report  (必需)  revenue | funnel | projects | rfm | inventory | pnl | full
 *   - template (可选)  executive (默认, 1-2 页摘要) | full (8-15 页) | deep (3-5 页深挖)
 *
 * 模板:
 *  - executive: 6 大指标卡片 + 风险预警清单 (1-2 页)
 *  - full:      6 模块详细 + 所有图表 (8-15 页)
 *  - deep:      选定报告深挖 (3-5 页)
 */
class AnalyticsPdfController extends Controller
{
    public function __construct(private AnalyticsQueryService $svc) {}

    public function export(Request $request)
    {
        $report  = $request->input('report', 'full');
        $template = $request->input('template', 'executive');

        $validReports = ['revenue', 'funnel', 'projects', 'rfm', 'inventory', 'pnl', 'full'];
        if (!in_array($report, $validReports)) {
            return response()->json(['code' => 400, 'message' => 'report 取值: ' . implode(', ', $validReports)], 400);
        }

        $validTemplates = ['executive', 'full', 'deep'];
        if (!in_array($template, $validTemplates)) {
            $template = 'executive';
        }

        try {
            // 收集数据
            if (in_array($report, ['inventory', 'pnl', 'full'], true) && !AuthScope::isUnrestricted($request->user())) {
                return response()->json(['code' => 403, 'message' => '当前账号无权导出全局库存或财务报表'], 403);
            }
            $data = $this->collectData($report, $request);

            // 生成 HTML
            $html = view("analytics.pdf.{$template}", [
                'data'      => $data,
                'report'    => $report,
                'generated_at' => now()->toDateTimeString(),
                'generated_by' => $request->user()?->name ?? '系统',
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled'      => true,
                    'defaultFont'          => 'DejaVu Sans', // 中文字体
                ]);

            $filename = sprintf('OA报表_%s_%s.pdf', $report, date('Ymd_His'));
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Analytics PDF export failed', [
                'report' => $report, 'template' => $template,
                'msg'    => $e->getMessage(),
            ]);
            return response()->json([
                'code'    => 500,
                'message' => 'PDF 生成失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 收集各报表数据
     */
    private function collectData(string $report, Request $request): array
    {
        $data = ['filters' => []];
        $scope = AuthScope::isUnrestricted($request->user()) ? [] : [
            '_scope_department_id' => $request->user()?->department_id,
            '_scope_sales_ids' => $request->user()?->department_id
                ? \App\Models\User::where('department_id', $request->user()->department_id)->pluck('id')->all()
                : [$request->user()?->id],
            '_scope_manager_ids' => $request->user()?->department_id
                ? \App\Models\User::where('department_id', $request->user()->department_id)->pluck('id')->all()
                : [$request->user()?->id],
        ];
        if ($report === 'full' || $report === 'revenue') {
            $data['revenue'] = $this->svc->revenue($scope);
        }
        if ($report === 'full' || $report === 'funnel') {
            $data['funnel'] = $this->svc->salesFunnel($scope);
        }
        if ($report === 'full' || $report === 'projects') {
            $data['projects'] = $this->svc->projectHealth($scope + ['limit' => 30]);
        }
        if ($report === 'full' || $report === 'rfm') {
            $data['rfm'] = $this->svc->customerRfm($scope + ['limit' => 50]);
        }
        if ($report === 'full' || $report === 'inventory') {
            $data['inventory'] = $this->svc->inventoryAging();
        }
        if ($report === 'full' || $report === 'pnl') {
            $data['pnl'] = $this->svc->financePnl();
        }
        return $data;
    }
}
