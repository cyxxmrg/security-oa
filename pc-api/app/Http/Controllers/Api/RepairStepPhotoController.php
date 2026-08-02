<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RepairStepPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
                'file_url'     => asset('storage/' . $p->file_path),
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => 'required|in:work_order,repair_order',
            'target_id'   => 'required|integer',
            'step'        => 'required|in:diagnose,disassemble,replace,debug,power_on,test,package,other',
            'file'        => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,heic', // 10MB
            'description' => 'nullable|string|max:500',
        ]);

        // 校验 target 存在
        if ($data['target_type'] === 'work_order') {
            $wo = \App\Models\WorkOrder::find($data['target_id']);
            if (!$wo) return response()->json(['code' => 404, 'message' => '工单不存在'], 404);
            $code = $wo->code;
        } else {
            $ro = \App\Models\RepairOrder::find($data['target_id']);
            if (!$ro) return response()->json(['code' => 404, 'message' => '返修单不存在'], 404);
            $code = $ro->code;
        }

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $dir = "repair-photos/{$data['target_type']}/{$code}/" . date('Ymd');
        $path = $file->storeAs($dir, uniqid('p_') . '.' . $ext, 'public');

        $photo = RepairStepPhoto::create([
            'target_type' => $data['target_type'],
            'target_id'   => $data['target_id'],
            'step'        => $data['step'],
            'file_path'   => $path,
            'file_name'   => $file->getClientOriginalName(),
            'file_type'   => $file->getMimeType(),
            'file_size'   => $file->getSize(),
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
                'file_url'  => asset('storage/' . $photo->file_path),
            ],
            'message' => '照片已上传',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // V1.2.10 修复 IDOR: 校验 uploaded_by (owner 或 admin 可删)
        $photo = RepairStepPhoto::findOrFail($id);
        $user = $request->user();
        if ($photo->uploaded_by !== $user?->id && !$this->isAdmin($user)) {
            return response()->json(['code' => 403, 'message' => '只能删除自己上传的照片'], 403);
        }
        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
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
}
