<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 库存 — V1.2.7d 瘦身后只做 HTTP 路由
 *
 * 业务全部委托给 App\Services\InventoryService
 */
class InventoryController extends Controller
{
    public function __construct(private InventoryService $svc) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginateItems($request)]);
    }

    public function show(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->showItem($inventoryItem)]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            return response()->json(['code' => 0, 'data' => $this->svc->createItem($request)]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    public function update(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->updateItem($request, $inventoryItem)]);
    }

    public function destroy(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $this->svc->destroyItem($request, $inventoryItem);
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function batchDelete(Request $request): JsonResponse
    {
        $count = $this->svc->batchDelete($request);
        return response()->json(['code' => 0, 'data' => ['deleted_count' => $count]]);
    }

    public function batchUpdate(Request $request): JsonResponse
    {
        $count = $this->svc->batchUpdate($request);
        return response()->json(['code' => 0, 'data' => ['updated_count' => $count]]);
    }

    public function batchExport(Request $request): StreamedResponse
    {
        $payload = $this->svc->batchExport($request);
        $filename = $payload['filename'];
        $headers  = $payload['headers'];
        $rows     = $payload['rows'];

        return response()->streamDownload(function () use ($headers, $rows) {
            $h = fopen('php://output', 'w');
            // 写入 BOM 让 Excel 识别 UTF-8
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, $headers);
            foreach ($rows as $r) fputcsv($h, $r);
            fclose($h);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function stockRecords(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginateStockRecords($request)]);
    }

    /** V1.3.6: 库存流水记录 (原始明细, 不聚合) */
    public function stockFlow(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginateRawStockRecords($request)]);
    }

    public function stockRecordDetail(string $recordNo): JsonResponse
    {
        $data = $this->svc->stockRecordDetail($recordNo);
        if (!$data) return response()->json(['code' => 404, 'message' => '入库单不存在'], 404);
        return response()->json(['code' => 0, 'data' => $data]);
    }

    public function warehouses(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->warehouses()]);
    }

    public function stockIn(Request $request): JsonResponse
    {
        $result = $this->svc->stockIn($request);
        return response()->json(['code' => 0, 'data' => $result]);
    }

    public function stockOut(Request $request): JsonResponse
    {
        try {
            $result = $this->svc->stockOut($request);
            return response()->json(['code' => 0, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function lowStock(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->lowStock($request)]);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->stats($request)]);
    }

    public function treeWithCounts(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->treeWithCounts($request)]);
    }

    public function batchImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,xlsx,xls,txt',
        ]);
        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            return response()->json(['code' => 1001, 'message' => "仅支持 CSV/XLSX 文件 (收到: .{$ext})"], 422);
        }
        try {
            $result = $this->svc->batchImport($file->getRealPath(), $ext);
            return response()->json([
                'code' => 0,
                'data' => [
                    'created' => $result['created'],
                    'skipped' => $result['skipped'],
                    'errors'  => $result['errors'],
                    'summary' => [
                        'created_count' => count($result['created']),
                        'skipped_count' => count($result['skipped']),
                        'error_count'   => count($result['errors']),
                    ],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
    }

    public function exportTemplate(Request $request): StreamedResponse
    {
        $payload = $this->svc->exportTemplate();
        $filename = $payload['filename'];
        $headers  = $payload['headers'];
        $rows     = $payload['rows'];

        return response()->streamDownload(function () use ($headers, $rows) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, $headers);
            foreach ($rows as $r) fputcsv($h, $r);
            fclose($h);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function itemsByCategory(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->itemsByCategory($request)]);
    }

    public function warnings(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->warnings($request)]);
    }

    // ============================================================
    // === 仓库管理 CRUD (V1.2.14p) ===
    // ============================================================

    public function warehouseStore(Request $request): JsonResponse
    {
        $warehouse = $this->svc->storeWarehouse($request);
        return response()->json(['code' => 0, 'data' => $warehouse]);
    }

    public function warehouseUpdate(Request $request, int $id): JsonResponse
    {
        $warehouse = $this->svc->updateWarehouse($request, $id);
        return response()->json(['code' => 0, 'data' => $warehouse]);
    }

    public function warehouseDestroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->svc->destroyWarehouse($id);
            return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    // ============================================================
    // === 仓库调拨 (V1.2.14p) ===
    // ============================================================

    public function stockTransfer(Request $request): JsonResponse
    {
        try {
            $result = $this->svc->stockTransfer($request);
            return response()->json(['code' => 0, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }
}
