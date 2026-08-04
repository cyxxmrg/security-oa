<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalQuote;
use App\Models\ExternalQuoteRequest;
use App\Services\ExternalQuoteService;
use App\Support\PrivateFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.2 对外报价控制器
 *
 * 路由 /api/external-quotes
 *  1. GET    /external-quotes/requests          列表
 *  2. POST   /external-quotes/requests          新建
 *  3. GET    /external-quotes/requests/{id}     详情（含 quotes）
 *  4. POST   /external-quotes/requests/{id}/close   关闭
 *  5. POST   /external-quotes/requests/{id}/cancel  取消
 *  6. GET    /external-quotes/requests/{id}/quotes  报价列表
 *  7. POST   /external-quotes/{quoteId}/shortlist  入围
 *  8. POST   /external-quotes/{quoteId}/reject     驳回
 *  9. POST   /external-quotes/{quoteId}/award      中标
 */
class ExternalQuoteController extends Controller
{
    public function __construct(private ExternalQuoteService $service) {}

    /** 1. 列表 */
    public function indexRequests(Request $request): JsonResponse
    {
        $filters = $request->only(['keyword', 'status', 'project_id', 'page', 'per_page']);
        $result  = $this->service->listRequests($filters);
        return response()->json([
            'code' => 0,
            'data' => [
                'items' => $result['items'],
                'total' => $result['total'],
            ],
        ]);
    }

    /** 2. 新建 */
    public function storeRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'     => ['nullable', 'integer', 'exists:projects,id'],
            'title'          => ['required', 'string', 'max:200'],
            'required_items' => ['required', 'array', 'min:1'],
            'required_files' => ['nullable', 'array'],
            'deadline'       => ['nullable', 'date'],
            'description'    => ['nullable', 'string', 'max:2000'],
        ]);
        $req = $this->service->createRequest($validated, $request->user()->id);
        return response()->json(['code' => 0, 'data' => $req], 201);
    }

    /** 3. 详情 */
    public function showRequest(int $id): JsonResponse
    {
        $req = ExternalQuoteRequest::with([
            'project:id,name,project_no',
            'creator:id,name',
            'awardedSupplier:id,name,code',
            'quotes.supplier:id,name,code',
        ])->findOrFail($id);
        return response()->json(['code' => 0, 'data' => $req]);
    }

    /** 4. 关闭 */
    public function closeRequest(int $id): JsonResponse
    {
        $req = $this->service->closeRequest($id);
        return response()->json(['code' => 0, 'data' => $req]);
    }

    /** 5. 取消 */
    public function cancelRequest(int $id): JsonResponse
    {
        $req = $this->service->cancelRequest($id);
        return response()->json(['code' => 0, 'data' => $req]);
    }

    // v0.5.8.10 对外报价附件 (required_files: 招标文件/图纸/技术规格 等)
    public function uploadRequiredFile(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,dwg,zip,rar', // 50MB
        ]);
        $req = \App\Models\ExternalQuoteRequest::findOrFail($id);
        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $dir  = "private/external-quotes/{$req->id}/" . date('Ymd');
        $path = $file->storeAs($dir, uniqid('reqfile_') . ($ext ? ".{$ext}" : ''), 'local');
        $fileId = uniqid('f_');
        $url = "/api/external-quotes/requests/{$req->id}/files/{$fileId}/download";

        $files = $req->required_files ?? [];
        $files[] = [
            'id'        => $fileId,
            'name'      => $file->getClientOriginalName(),
            'path'      => $path,
            'url'       => $url,
            'size'      => $file->getSize(),
            'mime'      => $file->getMimeType(),
            'uploaded_at' => now()->toIso8601String(),
        ];
        $req->required_files = $files;
        $req->save();

        return response()->json(['code' => 0, 'message' => '已上传', 'data' => $files]);
    }

    public function deleteRequiredFile(Request $request, int $id): JsonResponse
    {
        $fileId = $request->input('file_id');
        if (!$fileId) {
            return response()->json(['code' => 1001, 'message' => '缺少 file_id'], 422);
        }
        $req = \App\Models\ExternalQuoteRequest::findOrFail($id);
        $files = $req->required_files ?? [];
        $kept = [];
        $removed = null;
        foreach ($files as $f) {
            if (($f['id'] ?? null) === $fileId) {
                $removed = $f;
            } else {
                $kept[] = $f;
            }
        }
        if ($removed && !empty($removed['path'])) {
            PrivateFileStorage::delete($removed['path']);
        }
        $req->required_files = $kept ?: null;
        $req->save();
        return response()->json(['code' => 0, 'message' => '已删除', 'data' => $kept]);
    }

    // v0.5.8.10 通用附件上传 (新建请求时用, 不依赖 disk_folder)
    public function uploadAttachment(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,dwg,zip,rar',
        ]);
        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $dir  = 'private/external-quotes/_draft/' . date('Ymd');
        $path = $file->storeAs($dir, uniqid('att_') . ($ext ? ".{$ext}" : ''), 'local');

        return response()->json(['code' => 0, 'message' => '已上传', 'data' => [
            'id'            => uniqid('f_'),
            'name'          => $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'url'           => '/api/external-quotes/files/download?path=' . rawurlencode($path),
            'size'          => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'uploaded_at'   => now()->toIso8601String(),
        ]]);
    }

    public function downloadRequiredFile(int $id, string $fileId)
    {
        $request = ExternalQuoteRequest::findOrFail($id);
        $file = collect($request->required_files ?? [])->firstWhere('id', $fileId);
        abort_unless($file && !empty($file['path']), 404);

        return PrivateFileStorage::download($file['path'], $file['name'] ?? basename($file['path']), [
            'Content-Type' => $file['mime'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadDraftFile(Request $request)
    {
        $path = (string) $request->query('path', '');
        abort_unless(str_starts_with($path, 'private/external-quotes/_draft/'), 404);
        return PrivateFileStorage::download($path, basename($path), ['X-Content-Type-Options' => 'nosniff']);
    }

    /** 6. 该请求下的所有报价 */
    public function listQuotes(int $id, Request $request): JsonResponse
    {
        $query = ExternalQuote::where('request_id', $id)
            ->with(['supplier:id,name,code', 'submitter:id,name']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        $list = $query->orderByDesc('total_amount')->get();
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /** 7. 入围 */
    public function shortlistQuote(Request $request, int $quoteId): JsonResponse
    {
        $quote = $this->service->shortlistQuote($quoteId, $request->user()->id);
        return response()->json(['code' => 0, 'data' => $quote]);
    }

    /** 8. 驳回 */
    public function rejectQuote(Request $request, int $quoteId): JsonResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $quote = $this->service->rejectQuote($quoteId, $request->user()->id, $validated['reason'] ?? null);
        return response()->json(['code' => 0, 'data' => $quote]);
    }

    /** 9. 中标 */
    public function awardQuote(Request $request, int $quoteId): JsonResponse
    {
        $result = $this->service->awardQuote($quoteId, $request->user()->id);
        return response()->json(['code' => 0, 'data' => $result]);
    }
}
