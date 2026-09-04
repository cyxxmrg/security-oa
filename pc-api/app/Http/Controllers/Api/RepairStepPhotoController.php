<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RepairStepPhoto;
use App\Models\RepairOrder;
use App\Models\WorkOrder;
use App\Services\FileUploadService;
use App\Support\AuthScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * V0.5.7 块2 — 维修过程照片 (工单+返修共用, 7 步进度)
 *
 * 端点:
 *   GET    /api/step-photos?target_type=work_order&target_id=1
 *   POST   /api/step-photos  (multipart, 支持 work_order / repair_order)
 *   DELETE /api/step-photos/{id}
 */
class RepairStepPhotoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'target_type' => 'required|in:work_order,repair_order',
            'target_id'   => 'required|integer',
        ]);
        $this->assertTargetAccessible($request->target_type, (int) $request->target_id);
        $rows = RepairStepPhoto::where('target_type', $request->target_type)
            ->where('target_id', $request->target_id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'target_type'  => $p->target_type,
                'target_id'    => $p->target_id,
                'step'         => $p->step,
                'step_label'   => RepairStepPhoto::STEPS[$p->step] ?? $p->step,
                'file_name'    => $p->file_name,
                'file_url'     => route('repair.step-photos.download', $p->id),
                'file_size'    => $p->file_size,
                'description'  => $p->description,
                'uploaded_by'  => $p->uploaded_by,
                'uploaded_at'  => $p->uploaded_at?->toDateTimeString(),
            ]);

        // 按 step 分组
        $byStep = [];
        foreach ($rows as $r) {
            $byStep[$r['step']][] = $r;
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'items'  => $rows,
                'by_step' => $byStep,
                'steps'  => RepairStepPhoto::STEPS,
                'counts' => [
                    'total'    => $rows->count(),
                    'diagnose' => count($byStep['diagnose'] ?? []),
                    'replace'  => count($byStep['replace'] ?? []),
                    'test'     => count($byStep['test'] ?? []),
                ],
            ],
        ]);
    }

    public function store(Request $request, FileUploadService $uploader): JsonResponse
    {
        $data = $request->validate([
            'target_type' => 'required|in:work_order,repair_order',
            'target_id'   => 'required|integer',
            'step'        => 'required|in:diagnose,disassemble,replace,debug,power_on,test,package,other',
            'file'        => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,heic', // 10MB
            'description' => 'nullable|string|max:500',
        ]);

        // 校验 target 存在
        $target = $this->assertTargetAccessible($data['target_type'], (int) $data['target_id']);
        $code = $target->code;

        $file = $request->file('file');
        $dir = "repair-photos/{$data['target_type']}/{$code}/" . date('Ymd');
        $result = $uploader->store($request, 'file', [
            'disk'         => 'attachments',
            'subdir'       => $dir,
            'allowed_ext'  => ['jpg', 'jpeg', 'png', 'webp', 'heic'],
            'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic'],
            'max_size'     => 10240,
        ]);

        $photo = RepairStepPhoto::create([
            'target_type' => $data['target_type'],
            'target_id'   => $data['target_id'],
            'step'        => $data['step'],
            'file_path'   => $result['path'],
            'file_name'   => $result['original_name'],
            'file_type'   => $result['mime'],
            'file_size'   => $result['size'],
            'description' => $data['description'] ?? null,
            'uploaded_by' => $request->user()?->id,
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'code' => 0,
            'data' => [
                'id'        => $photo->id,
                'step'      => $photo->step,
                'file_name' => $photo->file_name,
                'file_url'  => route('repair.step-photos.download', $photo->id),
            ],
            'message' => '照片已上传',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // V1.2.10 修复 IDOR: 校验 uploaded_by (owner 或 admin 可删)
        $photo = RepairStepPhoto::findOrFail($id);
        $this->assertTargetAccessible($photo->target_type, (int) $photo->target_id);
        $user = $request->user();
        if ($photo->uploaded_by !== $user?->id && !$this->isAdmin($user)) {
            return response()->json(['code' => 403, 'message' => '只能删除自己上传的照片'], 403);
        }
        Storage::disk('attachments')->delete($photo->file_path);
        $photo->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    public function download(int $id): StreamedResponse|JsonResponse
    {
        $photo = RepairStepPhoto::findOrFail($id);
        $this->assertTargetAccessible($photo->target_type, (int) $photo->target_id);
        $disk = Storage::disk('attachments');
        if (!$disk->exists($photo->file_path)) {
            return response()->json(['code' => 1004, 'message' => '文件已丢失'], 404);
        }
        return $disk->download($photo->file_path, $photo->file_name, [
            'Content-Type' => $photo->file_type ?: 'application/octet-stream',
        ]);
    }

    /**
     * V1.2.10 — admin 角色检测 (容错: activeRoles 列缺失时 fallback)
     */
    private function isAdmin($user): bool
    {
        if (!$user) return false;
        try {
            return in_array('admin', $user->activeRoles()->pluck('name')->all(), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function assertTargetAccessible(string $targetType, int $targetId): WorkOrder|RepairOrder
    {
        $user = request()->user();
        abort_unless($user, 401, '登录状态已失效');

        $target = $targetType === 'work_order'
            ? WorkOrder::query()->findOrFail($targetId)
            : RepairOrder::query()->findOrFail($targetId);

        if (AuthScope::isUnrestricted($user)
            || ($user->is_system ?? false) === true
            || ($user->user_type ?? null) === 'system') {
            return $target;
        }

        $userId = (int) $user->id;
        $isOwner = (int) ($target->created_by ?? 0) === $userId
            || (int) ($target->received_by ?? 0) === $userId
            || (int) ($target->assigned_to ?? 0) === $userId;
        $hasProjectAccess = false;
        if (!empty($target->project_id)) {
            $hasProjectAccess = \App\Models\Project::query()
                ->whereKey($target->project_id)
                ->where(function ($query) use ($userId) {
                    $query->where('manager_id', $userId)
                        ->orWhereExists(function ($memberQuery) use ($userId) {
                            $memberQuery->selectRaw('1')
                                ->from('project_members')
                                ->whereColumn('project_members.project_id', 'projects.id')
                                ->where('project_members.user_id', $userId)
                                ->where('project_members.status', 'active');
                        });
                })
                ->exists();
        }

        abort_unless($isOwner || $hasProjectAccess, 403, '无权访问该工单的过程照片');
        return $target;
    }
}
