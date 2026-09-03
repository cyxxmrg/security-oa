<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\OpportunityStageFile;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(Request $request, Opportunity $opp, FileUploadService $uploader): JsonResponse
    {
        $request->validate([
            'stage' => 'required|string|in:' . implode(',', self::STAGES),
            'file'  => 'required|file|max:51200', // 50MB
            'notes' => 'nullable|string|max:2000',
        ]);

        /** @var User $user */
        $user = $request->user();
        $stage = $request->input('stage');
        $notes = $request->input('notes');

        $storedPath = null;
        try {
            $record = DB::transaction(function () use ($request, $opp, $uploader, $stage, $notes, $user, &$storedPath) {
                $lockedOpp = Opportunity::lockForUpdate()->findOrFail($opp->id);
                $dir = $lockedOpp->opp_no ?? 'opp_' . $lockedOpp->id;
                $result = $uploader->store($request, 'file', [
                    'disk'         => self::DISK,
                    'subdir'       => "{$dir}/{$stage}",
                    'allowed_ext'  => FileUploadService::DEFAULT_ALLOWED_EXT,
                    'allowed_mime' => FileUploadService::DEFAULT_ALLOWED_MIME,
                    'max_size'     => 51200,
                ]);
                $storedPath = $result['path'];

                return OpportunityStageFile::create([
                    'opportunity_id' => $lockedOpp->id,
                    'stage'          => $stage,
                    'original_name'  => $result['original_name'],
                    'stored_path'    => $result['path'],
                    'mime_type'      => $result['mime'],
                    'file_size'      => $result['size'],
                    'notes'          => $notes,
                    'uploaded_by'    => $user->id,
                ]);
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                Storage::disk(self::DISK)->delete($storedPath);
            }
            throw $e;
        }

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
            $file->original_name,
            ['Content-Type' => $file->mime_type ?: 'application/octet-stream']
        );
    }

    /**
     * 删除文件
     */
    public function destroy(Request $request, Opportunity $opp, OpportunityStageFile $file): JsonResponse
    {
        DB::transaction(function () use ($opp, $file) {
            Opportunity::lockForUpdate()->findOrFail($opp->id);
            $lockedFile = OpportunityStageFile::where('opportunity_id', $opp->id)
                ->lockForUpdate()
                ->findOrFail($file->id);
            $path = $lockedFile->stored_path;
            $lockedFile->delete();
            Storage::disk(self::DISK)->delete($path);
        });

        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }
}
