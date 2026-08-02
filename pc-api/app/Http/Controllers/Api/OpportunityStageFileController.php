<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\OpportunityStageFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpportunityStageFileController extends Controller
{
    /** 8 段白名单 */
    private const STAGES = [
        'inquiry', 'qualification', 'site_survey', 'proposal',
        'negotiating', 'quoted', 'won', 'lost',
    ];

    private const DISK = 'opportunity-files';

    /**
     * 列出指定阶段的所有文件
     */
    public function index(Request $request, Opportunity $opp): JsonResponse
    {
        $stage = $request->query('stage');
        if (!$stage || !in_array($stage, self::STAGES, true)) {
            return response()->json(['code' => 1, 'message' => 'stage 参数无效'], 422);
        }

        $files = OpportunityStageFile::with('uploader:id,name')
            ->where('opportunity_id', $opp->id)
            ->where('stage', $stage)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($f) {
                return [
                    'id'             => $f->id,
                    'original_name'  => $f->original_name,
                    'mime_type'      => $f->mime_type,
                    'file_size'      => $f->file_size,
                    'formatted_size' => $f->formatted_size,
                    'notes'          => $f->notes,
                    'url'            => $f->url,           // 前端下载用
                    'exists'         => $f->fileExists(),
                    'uploaded_by'    => $f->uploader?->name ?? '-',
                    'created_at'     => $f->created_at?->toDateTimeString(),
                ];
            });

        return response()->json(['code' => 0, 'data' => $files]);
    }

    /**
     * 上传文件到指定阶段
     */
    public function store(Request $request, Opportunity $opp): JsonResponse
    {
        $request->validate([
            'stage' => 'required|string|in:' . implode(',', self::STAGES),
            'file'  => 'required|file|max:51200', // 50MB
            'notes' => 'nullable|string|max:2000',
        ]);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $stage = $request->input('stage');
        $notes = $request->input('notes');

        // 按机会编号/项目名组织文件夹: opportunity-files/{opp_no}/{stage}/
        $dir = $opp->opp_no ?? 'opp_' . $opp->id;
        $stageDir = "{$dir}/{$stage}";

        // 原文件名保留 + 时间戳防重
        $ext = $file->getClientOriginalExtension();
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        // 清理特殊字符
        $safeBase = preg_replace('/[^\w\-\x{4e00}-\x{9fff}]/u', '_', $baseName);
        $storedName = $safeBase . '_' . time() . '.' . $ext;
        $relPath = $stageDir . '/' . $storedName;

        // 存储
        Storage::disk(self::DISK)->put($relPath, file_get_contents($file->getRealPath()));

        $record = OpportunityStageFile::create([
            'opportunity_id' => $opp->id,
            'stage'          => $stage,
            'original_name'  => $file->getClientOriginalName(),
            'stored_path'    => $relPath,
            'mime_type'      => $file->getMimeType(),
            'file_size'      => $file->getSize(),
            'notes'          => $notes,
            'uploaded_by'    => $user->id,
        ]);

        return response()->json([
            'code' => 0,
            'data' => [
                'id'             => $record->id,
                'original_name'  => $record->original_name,
                'mime_type'      => $record->mime_type,
                'file_size'      => $record->file_size,
                'formatted_size' => $record->formatted_size,
                'notes'          => $record->notes,
                'url'            => $record->url,
                'exists'         => $record->fileExists(),
                'uploaded_by'    => $user->name,
                'created_at'     => $record->created_at?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * 下载文件
     */
    public function download(Opportunity $opp, OpportunityStageFile $file): StreamedResponse|JsonResponse
    {
        abort_unless($file->opportunity_id === $opp->id, 404);

        if (!$file->fileExists()) {
            return response()->json(['code' => 1, 'message' => '文件已被删除'], 404);
        }

        return Storage::disk(self::DISK)->download(
            $file->stored_path,
            $file->original_name
        );
    }

    /**
     * 删除文件
     */
    public function destroy(Request $request, Opportunity $opp, OpportunityStageFile $file): JsonResponse
    {
        abort_unless($file->opportunity_id === $opp->id, 404);

        // 删磁盘
        if ($file->fileExists()) {
            Storage::disk(self::DISK)->delete($file->stored_path);
        }
        // 删记录
        $file->delete();

        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }
}