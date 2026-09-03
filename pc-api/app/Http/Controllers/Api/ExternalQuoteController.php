<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalQuote;
use App\Models\ExternalQuoteRequest;
use App\Services\ExternalQuoteService;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
    public function uploadRequiredFile(Request $request, int $id, FileUploadService $uploader): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,dwg,zip,rar', // 50MB
        ]);
        $result = $uploader->store($request, 'file', [
            'disk'         => 'attachments',
            'subdir'       => "external-quotes/{$id}/" . date('Ymd'),
            'allowed_ext'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'dwg', 'zip', 'rar'],
            'allowed_mime' => [
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg', 'image/png', 'application/acad', 'application/dwg', 'image/vnd.dwg',
                'application/zip', 'application/x-rar-compressed',
            ],
            'max_size'     => 51200,
        ]);

        try {
            [$req, $files] = DB::transaction(function () use ($id, $result) {
                $req = ExternalQuoteRequest::lockForUpdate()->findOrFail($id);
                if ($req->status !== ExternalQuoteRequest::STATUS_OPEN) {
                    throw new \RuntimeException('只有征集中状态可上传报价资料');
                }
                if ($req->deadline && $req->deadline->isPast()) {
                    throw new \RuntimeException('报价截止时间已过，不可上传资料');
                }

                $fileId = (string) Str::uuid();
                $files = $req->required_files ?? [];
                $files[] = [
                    'id'          => $fileId,
                    'name'        => $result['original_name'],
                    'path'        => $result['path'],
                    'url'         => route('external-quotes.files.download', [$req->id, $fileId]),
                    'size'        => $result['size'],
                    'mime'        => $result['mime'],
                    'uploaded_at' => now()->toIso8601String(),
                ];
                $req->update(['required_files' => array_values($files)]);

                return [$req->fresh(), $files];
            });
        } catch (\Throwable $e) {
            Storage::disk('attachments')->delete($result['path']);
            throw $e;
        }

        return response()->json(['code' => 0, 'message' => '已上传', 'data' => $files]);
    }

    public function deleteRequiredFile(Request $request, int $id): JsonResponse
    {
        $fileId = $request->input('file_id');
        if (!$fileId) {
            return response()->json(['code' => 1001, 'message' => '缺少 file_id'], 422);
        }
        [$removed, $kept] = DB::transaction(function () use ($id, $fileId) {
            $req = ExternalQuoteRequest::lockForUpdate()->findOrFail($id);
            $files = $req->required_files ?? [];
            $kept = [];
            $removed = null;
            foreach ($files as $file) {
                if (($file['id'] ?? null) === $fileId) {
                    $removed = $file;
                } else {
                    $kept[] = $file;
                }
            }
            if (!$removed) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('文件不存在');
            }
            $req->update(['required_files' => $kept ?: null]);

            return [$removed, $kept];
        });
        if (!empty($removed['path'])) {
            Storage::disk('attachments')->delete($removed['path']);
        }
        return response()->json(['code' => 0, 'message' => '已删除', 'data' => $kept]);
    }

    public function downloadRequiredFile(int $id, string $fileId): StreamedResponse|JsonResponse
    {
        $req = ExternalQuoteRequest::findOrFail($id);
        $file = collect($req->required_files ?? [])->firstWhere('id', $fileId);
        if (!$file || empty($file['path'])) {
            return response()->json(['code' => 404, 'message' => '文件不存在'], 404);
        }
        $disk = Storage::disk('attachments');
        if (!$disk->exists($file['path'])) {
            return response()->json(['code' => 404, 'message' => '文件已被删除'], 404);
        }
        return $disk->download($file['path'], $file['name'] ?? basename($file['path']), [
            'Content-Type' => $file['mime'] ?? 'application/octet-stream',
        ]);
    }

    // v0.5.8.10 通用附件上传 (新建请求时用, 不依赖 disk_folder)
    public function uploadAttachment(Request $request, FileUploadService $uploader): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,dwg,zip,rar',
        ]);
        $result = $uploader->store($request, 'file', [
            'disk'         => 'attachments',
            'subdir'       => 'external-quotes/_draft/' . date('Ymd'),
            'allowed_ext'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'dwg', 'zip', 'rar'],
            'allowed_mime' => [
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg', 'image/png', 'application/acad', 'application/dwg', 'image/vnd.dwg',
                'application/zip', 'application/x-rar-compressed',
            ],
            'max_size'     => 51200,
        ]);

        return response()->json(['code' => 0, 'message' => '已上传', 'data' => [
            'id'            => uniqid('f_'),
            'name'          => $result['original_name'],
            'original_name' => $result['original_name'],
            'path'          => $result['path'],
            'url'           => null,
            'size'          => $result['size'],
            'mime_type'     => $result['mime'],
            'uploaded_at'   => now()->toIso8601String(),
        ]]);
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
