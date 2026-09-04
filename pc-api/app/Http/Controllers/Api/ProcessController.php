<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{ProcessImage, ProcessInspection, ProcessInstance, ProcessSignature, ProcessTemplate, Project, ApprovalRecord};
use App\Services\FileUploadService;
use App\Services\ProcessAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 深化施工上报 V1.1 - 工序验收 + 影像档案
 * 5 个子资源: templates / instances / inspections / images / signatures
 *
 * 路由前缀: /api/process
 * 路由顺序: 字面量路由(templates/inspections/images/signatures/import/batch)
 *          必须在 {process} / {inspection} / {image} / {signature} 通配之前
 */
class ProcessController extends Controller
{
    // ================== 工序模板 (templates) ==================

    /** 列表: 支持 industry/category/keyword/active 过滤 */
    public function templates(Request $request): JsonResponse
    {
        $query = ProcessTemplate::query();
        if ($request->filled('industry')) $query->where('industry', $request->industry);
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('is_active')) $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('code', 'like', "%{$kw}%")
                  ->orWhere('description', 'like', "%{$kw}%");
            });
        }

        $list = $query->orderBy('industry')->orderBy('sort_order')->orderBy('id')
            ->paginate($request->per_page ?? 20);

        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'industry'                  => 'required|string|max:30',
            'category'                  => 'required|string|max:50',
            'code'                      => 'required|string|max:50',
            'name'                      => 'required|string|max:100',
            'description'               => 'nullable|string',
            'standard_duration_days'    => 'nullable|integer|min:0',
            'standard_man_hours'        => 'nullable|numeric|min:0',
            'required_qualifications'   => 'nullable|array',
            'required_qualifications.*' => 'string|max:50',
            'safety_requirements'       => 'nullable|string',
            'quality_checkpoints'       => 'nullable|array',
            'acceptance_criteria'       => 'nullable|array',
            'sort_order'                => 'nullable|integer',
            'is_active'                 => 'nullable|boolean',
        ]);
        $data['created_by'] = $request->user()?->id;
        $data['is_active']  = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $tpl = ProcessTemplate::create($data);
        return response()->json(['code' => 0, 'message' => '创建成功', 'data' => $tpl]);
    }

    public function showTemplate(ProcessTemplate $template): JsonResponse
    {
        $template->load('creator:id,name');
        $template->instance_count = $template->instances()->count();
        return response()->json(['code' => 0, 'data' => $template]);
    }

    public function updateTemplate(Request $request, ProcessTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'industry'                  => 'sometimes|string|max:30',
            'category'                  => 'sometimes|string|max:50',
            'code'                      => 'sometimes|string|max:50',
            'name'                      => 'sometimes|string|max:100',
            'description'               => 'nullable|string',
            'standard_duration_days'    => 'nullable|integer|min:0',
            'standard_man_hours'        => 'nullable|numeric|min:0',
            'required_qualifications'   => 'nullable|array',
            'safety_requirements'       => 'nullable|string',
            'quality_checkpoints'       => 'nullable|array',
            'acceptance_criteria'       => 'nullable|array',
            'sort_order'                => 'nullable|integer',
            'is_active'                 => 'nullable|boolean',
        ]);
        $template->update($data);
        return response()->json(['code' => 0, 'message' => '更新成功', 'data' => $template->fresh()]);
    }

    public function destroyTemplate(ProcessTemplate $template): JsonResponse
    {
        if ($template->instances()->exists()) {
            return response()->json(['code' => 1001, 'message' => '该模板已被项目使用,无法删除'], 422);
        }
        $template->delete();
        return response()->json(['code' => 0, 'message' => '删除成功']);
    }

    /** 行业枚举(供前端下拉) */
    public function industries(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => ProcessTemplate::industries()]);
    }

    /**
     * 从模板批量生成项目工序
     * POST /api/process/templates/{template}/apply
     * body: { project_id, planned_start_date }
     */
    public function applyTemplate(Request $request, ProcessTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'project_id'           => 'required|integer|exists:projects,id',
            'planned_start_date'   => 'required|date',
            'parent_id'            => 'nullable|integer',
        ]);
        $project = Project::findOrFail($data['project_id']);

        $instance = ProcessInstance::create([
            'project_id'            => $project->id,
            'template_id'           => $template->id,
            'parent_id'             => $data['parent_id'] ?? null,
            'code'                  => $project->code . '-' . $template->code . '-' . str_pad((string)random_int(1, 99), 2, '0', STR_PAD_LEFT),
            'name'                  => $template->name,
            'planned_start_date'    => $data['planned_start_date'],
            'planned_end_date'      => \Carbon\Carbon::parse($data['planned_start_date'])->addDays($template->standard_duration_days ?? 1),
            'planned_duration_days' => $template->standard_duration_days ?? 1,
            'status'                => ProcessInstance::STATUS_PENDING,
            'progress'              => 0,
        ]);
        return response()->json(['code' => 0, 'message' => '工序已创建', 'data' => $instance]);
    }

    // ================== 工序实例 (instances) ==================

    /** 项目下所有工序(树形按 parent_id) */
    public function instances(Request $request): JsonResponse
    {
        // v1.2.12p 补 project 关联 (前端列表/详情需要项目名称)
        $query = ProcessInstance::with(['project:id,name,project_no', 'foreman:id,name', 'template:id,code,name', 'acceptedByUser:id,name']);
        if ($request->filled('project_id')) $query->where('project_id', $request->project_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('foreman_id')) $query->where('foreman_id', $request->foreman_id);
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")->orWhere('code', 'like', "%{$kw}%");
            });
        }

        $list = $query->orderBy('project_id')->orderBy('sequence')->orderBy('id')
            ->paginate($request->per_page ?? 20);

        // 补一个 is_overdue 标志
        $list->getCollection()->transform(function ($i) {
            $i->is_overdue = $i->isOverdue();
            return $i;
        });

        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function storeInstance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'             => 'required|integer|exists:projects,id',
            'template_id'            => 'nullable|integer|exists:process_templates,id',
            'parent_id'              => 'nullable|integer',
            'name'                   => 'required|string|max:100',
            'code'                   => 'nullable|string|max:50',
            'sequence'               => 'nullable|integer',
            'planned_start_date'     => 'nullable|date',
            'planned_end_date'       => 'nullable|date',
            'planned_duration_days'  => 'nullable|integer|min:0',
            'foreman_id'             => 'nullable|integer',
            'workers'                => 'nullable|array',
            'location'               => 'nullable|string|max:200',
            'description'            => 'nullable|string',
        ]);

        $project = Project::findOrFail($data['project_id']);
        if (!empty($data['parent_id'])) {
            ProcessInstance::where('project_id', $project->id)->findOrFail($data['parent_id']);
        }

        if (empty($data['code'])) {
            $data['code'] = ($project->code ?? 'P') . '-' . strtoupper(Str::random(4)) . '-' . date('ymd');
        }
        if (empty($data['planned_end_date']) && !empty($data['planned_start_date']) && !empty($data['planned_duration_days'])) {
            $data['planned_end_date'] = \Carbon\Carbon::parse($data['planned_start_date'])->addDays($data['planned_duration_days']);
        }
        $data['status']   = ProcessInstance::STATUS_PENDING;
        $data['progress'] = 0;
        $data['sequence'] = $data['sequence'] ?? 0;

        $ins = ProcessInstance::create($data);
        return response()->json(['code' => 0, 'message' => '工序已创建', 'data' => $ins]);
    }

    public function showInstance(ProcessInstance $process): JsonResponse
    {
        $process->load([
            'project:id,name,project_no',
            'template:id,code,name,quality_checkpoints,acceptance_criteria',
            'foreman:id,name',
            'parent:id,name,code',
            'children:id,name,code,status,parent_id',
            'acceptedByUser:id,name',
            'images' => function ($q) { $q->orderBy('taken_at', 'desc'); },
            'signatures',
        ]);
        $process->is_overdue = $process->isOverdue();
        $process->inspection_stats = $process->inspections()
            ->selectRaw('inspection_type, result, count(*) as cnt')
            ->groupBy('inspection_type', 'result')
            ->get();
        return response()->json(['code' => 0, 'data' => $process]);
    }

    public function updateInstance(Request $request, ProcessInstance $process): JsonResponse
    {
        if ($process->status === ProcessInstance::STATUS_ACCEPTED) {
            return response()->json(['code' => 1001, 'message' => '已验收工序不可修改'], 422);
        }
        $data = $request->validate([
            'name'                   => 'sometimes|string|max:100',
            'sequence'               => 'nullable|integer',
            'planned_start_date'     => 'nullable|date',
            'planned_end_date'       => 'nullable|date',
            'actual_start_date'      => 'nullable|date',
            'actual_end_date'        => 'nullable|date',
            'planned_duration_days'  => 'nullable|integer|min:0',
            'actual_duration_days'   => 'nullable|integer|min:0',
            'foreman_id'             => 'nullable|integer',
            'workers'                => 'nullable|array',
            'location'               => 'nullable|string|max:200',
            'description'            => 'nullable|string',
        ]);
        $process->update($data);
        return response()->json(['code' => 0, 'message' => '更新成功', 'data' => $process->fresh()]);
    }

    public function destroyInstance(ProcessInstance $process): JsonResponse
    {
        if ($process->status === ProcessInstance::STATUS_ACCEPTED) {
            return response()->json(['code' => 1001, 'message' => '已验收工序不可删除'], 422);
        }
        $process->delete();
        return response()->json(['code' => 0, 'message' => '删除成功']);
    }

    /** 更新进度 + 状态联动 */
    public function updateProgress(Request $request, ProcessInstance $process): JsonResponse
    {
        if ($process->status === ProcessInstance::STATUS_ACCEPTED) {
            return response()->json(['code' => 1001, 'message' => '已验收工序不可修改进度'], 422);
        }
        $data = $request->validate([
            'progress'         => 'required|integer|min:0|max:100',
            'status'           => 'nullable|string|in:pending,in_progress,completed,blocked',
            'remark'           => 'nullable|string|max:500',
        ]);
        $process->progress = $data['progress'];

        // 状态自动联动
        if (isset($data['status'])) {
            $process->status = $data['status'];
        } else {
            if ($data['progress'] === 0) $process->status = ProcessInstance::STATUS_PENDING;
            elseif ($data['progress'] < 100) $process->status = ProcessInstance::STATUS_IN_PROGRESS;
            else $process->status = ProcessInstance::STATUS_COMPLETED;
        }

        // 自动记录实际日期
        if ($process->status === ProcessInstance::STATUS_IN_PROGRESS && !$process->actual_start_date) {
            $process->actual_start_date = today();
        }
        if ($process->status === ProcessInstance::STATUS_COMPLETED && !$process->actual_end_date) {
            $process->actual_end_date = today();
            $process->actual_duration_days = $process->planned_start_date
                ? (int) today()->diffInDays(\Carbon\Carbon::parse($process->planned_start_date)) + 1
                : 0;
        }

        $process->save();
        return response()->json(['code' => 0, 'message' => '进度已更新', 'data' => $process->fresh()]);
    }

    /** 提交验收审批 */
    public function submitAcceptance(Request $request, ProcessInstance $process): JsonResponse
    {
        $data = $request->validate([
            'inspection_id' => 'nullable|integer|exists:process_inspections,id',
            'comment'       => 'nullable|string|max:500',
        ]);
        $this->ensureInspectionBelongsToProcess($data['inspection_id'] ?? null, $process);

        try {
            $approval = app(ProcessAcceptanceService::class)->submit(
                $process,
                $request->user(),
                $data['inspection_id'] ?? null,
                $data['comment'] ?? null
            );
            return response()->json([
                'code' => 0,
                'message' => '验收审批已提交，请前往项目审批中心处理',
                'data' => ['approval_id' => $approval->id, 'approval_code' => $approval->code],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
    }

    /** 验收通过（兼容旧客户端，仅处理已有审批单） */
    public function acceptInstance(Request $request, ProcessInstance $process): JsonResponse
    {
        $data = $request->validate([
            'inspection_id'  => 'nullable|integer|exists:process_inspections,id',
            'comment'        => 'required|string|max:500',
        ]);
        $this->ensureInspectionBelongsToProcess($data['inspection_id'] ?? null, $process);
        try {
            $process = app(ProcessAcceptanceService::class)->approve(
                $process,
                $request->user(),
                $data['inspection_id'] ?? null,
                $data['comment']
            );
            return response()->json(['code' => 0, 'message' => '验收审批已处理', 'data' => $process]);
        } catch (\Throwable $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
    }

    /** 验收不通过 */
    public function rejectInstance(Request $request, ProcessInstance $process): JsonResponse
    {
        $data = $request->validate([
            'reason'         => 'required|string|max:500',
            'inspection_id'  => 'nullable|integer',
            'comment'        => 'nullable|string|max:500',
        ]);
        $this->ensureInspectionBelongsToProcess($data['inspection_id'] ?? null, $process);
        try {
            $process = app(ProcessAcceptanceService::class)->reject(
                $process,
                $request->user(),
                $data['inspection_id'] ?? null,
                $data['reason'] . (empty($data['comment']) ? '' : '：' . $data['comment'])
            );
            return response()->json(['code' => 0, 'message' => '验收已驳回', 'data' => $process]);
        } catch (\Throwable $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
    }

    // ================== 验收记录 (inspections) ==================

    /** 工序的所有验收记录 */
    public function inspections(Request $request): JsonResponse
    {
        $query = ProcessInspection::with([
            'inspector:id,name',
            'processInstance:id,name,code,project_id',
            'processInstance.project:id,name,project_no',
        ]);
        if ($request->filled('process_instance_id')) $query->where('process_instance_id', $request->process_instance_id);
        if ($request->filled('inspection_type')) $query->where('inspection_type', $request->inspection_type);
        if ($request->filled('result')) $query->where('result', $request->result);
        // V1.2.13: 项目详情验收 Tab 按项目过滤 (前端 useProjectDetail 传 project_id)
        if ($request->filled('project_id')) {
            $query->whereHas('processInstance', function ($q) use ($request) {
                $q->where('project_id', $request->project_id);
            });
        }

        $list = $query->orderBy('inspection_date', 'desc')->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function storeInspection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'process_instance_id'    => 'required|integer|exists:process_instances,id',
            'inspection_type'        => 'required|string|in:self,mutual,supervisor,owner',
            'inspector_id'           => 'nullable|integer',
            'inspector_name'         => 'nullable|string|max:50',
            'inspection_date'        => 'required|date',
            'result'                 => 'required|string|in:pending,pass,fail,partial',
            'score'                  => 'nullable|numeric|min:0|max:100',
            'checkpoint_results'     => 'nullable|array',
            'issues'                 => 'nullable|array',
            'suggestions'            => 'nullable|string',
            'next_inspection_date'   => 'nullable|date',
            'image_ids'              => 'nullable|array',
            'image_ids.*'            => 'integer',
            'remark'                 => 'nullable|string',
        ]);

        $process = ProcessInstance::findOrFail($data['process_instance_id']);
        $this->ensureImagesBelongToProcess($data['image_ids'] ?? [], $process);
        $data['inspector_id'] = $data['inspector_id'] ?? $request->user()->id;
        $ins = ProcessInspection::create($data);

        return response()->json(['code' => 0, 'message' => '验收记录已创建', 'data' => $ins]);
    }

    public function showInspection(ProcessInspection $inspection): JsonResponse
    {
        $inspection->load(['processInstance:id,name,code,project_id', 'inspector:id,name', 'images', 'signatures']);
        return response()->json(['code' => 0, 'data' => $inspection]);
    }

    public function updateInspection(Request $request, ProcessInspection $inspection): JsonResponse
    {
        $this->ensureInspectionMutable($inspection);
        $data = $request->validate([
            'inspection_type'        => 'sometimes|string',
            'result'                 => 'sometimes|string',
            'score'                  => 'nullable|numeric',
            'checkpoint_results'     => 'nullable|array',
            'issues'                 => 'nullable|array',
            'suggestions'            => 'nullable|string',
            'next_inspection_date'   => 'nullable|date',
            'image_ids'              => 'nullable|array',
            'remark'                 => 'nullable|string',
        ]);
        $process = $inspection->processInstance;
        if (!$process) {
            return response()->json(['code' => 404, 'message' => '工序不存在'], 404);
        }
        $this->ensureImagesBelongToProcess($data['image_ids'] ?? [], $process);
        $inspection->update($data);
        return response()->json(['code' => 0, 'message' => '更新成功', 'data' => $inspection->fresh()]);
    }

    public function destroyInspection(ProcessInspection $inspection): JsonResponse
    {
        $this->ensureInspectionMutable($inspection);
        $inspection->delete();
        return response()->json(['code' => 0, 'message' => '删除成功']);
    }

    // ================== 影像 (images) ==================

    /** 工序所有影像 */
    public function images(Request $request): JsonResponse
    {
        $query = ProcessImage::with(['takenByUser:id,name', 'inspection:id,inspection_type,result']);
        if ($request->filled('process_instance_id')) $query->where('process_instance_id', $request->process_instance_id);
        if ($request->filled('inspection_id')) $query->where('inspection_id', $request->inspection_id);
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('file_type')) $query->where('file_type', $request->file_type);

        $list = $query->orderBy('taken_at', 'desc')->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 30);
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /**
     * 上传影像(支持多文件)
     * POST /api/process/images/upload
     * form-data: files[] + process_instance_id + category + inspection_id?
     */
    public function uploadImages(Request $request, FileUploadService $uploader): JsonResponse
    {
        $data = $request->validate([
            'process_instance_id' => 'required|integer|exists:process_instances,id',
            'inspection_id'       => 'nullable|integer',
            'category'            => 'required|string|in:before,during,after,issue,acceptance',
            'files'               => 'required|array|min:1|max:20',
            'files.*'             => 'file|max:51200|mimes:jpg,jpeg,png,gif,webp,heic,heif,pdf,mp4,mov', // 50MB, 仅图像/视频/PDF
            'description'         => 'nullable|string|max:500',
            'tags'                => 'nullable|array',
            'location'            => 'nullable|string|max:200',
        ]);

        $process = ProcessInstance::findOrFail($data['process_instance_id']);
        $this->ensureInspectionBelongsToProcess($data['inspection_id'] ?? null, $process);

        $uploaded = [];
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $data, &$uploaded, &$storedPaths, $uploader) {
                foreach ($request->file('files') as $file) {
                // 逐文件构造 pseudo-Request 走统一服务 (P1 重构)
                $fakeReq = Request::create('/', 'POST', [], [], ['file' => $file]);
                $result = $uploader->store($fakeReq, 'file', [
                    'disk' => 'attachments',
                    'subdir' => "process/{$data['process_instance_id']}/" . date('Y/m'),
                    'allowed_ext'  => ['jpg','jpeg','png','gif','webp','heic','heif','pdf','mp4','mov'],
                    'allowed_mime' => ['image/jpeg','image/png','image/gif','image/webp','image/heic','image/heif',
                        'application/pdf','video/mp4','video/quicktime'],
                    'max_size' => 51200,
                ]);
                $storedPaths[] = $result['path'];

                $fileType = str_starts_with($result['mime'], 'video/') ? 'video' : 'image';

                $img = ProcessImage::create([
                    'process_instance_id' => $data['process_instance_id'],
                    'inspection_id'       => $data['inspection_id'] ?? null,
                    'category'            => $data['category'],
                    'file_type'           => $fileType,
                    'file_name'           => $result['original_name'],
                    'file_path'           => $result['path'],
                    'file_size'           => $result['size'],
                    'mime_type'           => $result['mime'],
                    'taken_at'            => now(),
                    'taken_by'            => $request->user()?->id,
                    'description'         => $data['description'] ?? null,
                    'tags'                => $data['tags'] ?? null,
                    'location'            => $data['location'] ?? null,
                ]);
                $uploaded[] = $img;
                }
            }
            );
        } catch (\Throwable $e) {
            $disk = Storage::disk('attachments');
            foreach ($storedPaths as $path) {
                $disk->delete($path);
            }
            throw $e;
        }

        return response()->json(['code' => 0, 'message' => '上传成功', 'data' => $uploaded]);
    }

    public function showImage(ProcessImage $image): JsonResponse
    {
        $image->load(['processInstance:id,name,code', 'inspection:id,inspection_type,result', 'takenByUser:id,name']);
        $image->url = route('process.images.download', ['image' => $image->getKey()]);
        return response()->json(['code' => 0, 'data' => $image]);
    }

    public function downloadImage(ProcessImage $image): StreamedResponse|JsonResponse
    {
        $path = trim((string) $image->file_path, '/');
        $disk = Storage::disk('attachments');

        if (!$disk->exists($path)) {
            $legacyDisk = Storage::disk('local');
            if (!$legacyDisk->exists($path)) {
                return response()->json(['code' => 404, 'message' => '影像文件不存在'], 404);
            }
            $disk = $legacyDisk;
        }

        return $disk->download($path, $image->file_name, [
            'Content-Type' => $image->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function updateImageMeta(Request $request, ProcessImage $image): JsonResponse
    {
        $data = $request->validate([
            'category'    => 'sometimes|string|in:before,during,after,issue,acceptance',
            'description' => 'nullable|string|max:500',
            'tags'        => 'nullable|array',
            'location'    => 'nullable|string|max:200',
        ]);
        $image->update($data);
        return response()->json(['code' => 0, 'message' => '更新成功', 'data' => $image->fresh()]);
    }

    public function destroyImage(ProcessImage $image): JsonResponse
    {
        // 软删除: 实际文件保留(防止误删关键档案),仅移除记录
        $image->delete();
        return response()->json(['code' => 0, 'message' => '影像已移除(物理文件保留)']);
    }

    private function ensureInspectionBelongsToProcess(?int $inspectionId, ProcessInstance $process): void
    {
        if ($inspectionId === null) {
            return;
        }

        if (!$process->inspections()->whereKey($inspectionId)->exists()) {
            throw ValidationException::withMessages([
                'inspection_id' => '验收记录不属于当前工序',
            ]);
        }
    }

    private function ensureImagesBelongToProcess(array $imageIds, ProcessInstance $process): void
    {
        $imageIds = array_values(array_unique(array_map('intval', $imageIds)));
        if ($imageIds === []) {
            return;
        }

        $matched = ProcessImage::where('process_instance_id', $process->id)
            ->whereIn('id', $imageIds)
            ->count();
        if ($matched !== count($imageIds)) {
            throw ValidationException::withMessages([
                'image_ids' => '影像记录不属于当前工序',
            ]);
        }
    }

    private function ensureInspectionMutable(ProcessInspection $inspection): void
    {
        $isReferenced = ApprovalRecord::where('type', 'project')
            ->where('sub_type', 'process_acceptance')
            ->whereJsonContains('payload->inspection_id', $inspection->id)
            ->whereIn('status', [ApprovalRecord::STATUS_PENDING, ApprovalRecord::STATUS_APPROVED])
            ->exists();
        if ($isReferenced) {
            throw ValidationException::withMessages([
                'inspection' => '该验收记录已进入审批流程，不能修改或删除',
            ]);
        }
    }

    // ================== 签字 (signatures) ==================

    public function signatures(Request $request): JsonResponse
    {
        $query = ProcessSignature::with(['signer:id,name', 'processInstance:id,name,code']);
        if ($request->filled('process_instance_id')) $query->where('process_instance_id', $request->process_instance_id);
        if ($request->filled('inspection_id')) $query->where('inspection_id', $request->inspection_id);
        if ($request->filled('signer_type')) $query->where('signer_type', $request->signer_type);

        $list = $query->orderBy('signed_at', 'desc')->paginate($request->per_page ?? 20);
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /**
     * 提交签字
     * POST /api/process/signatures
     * body: process_instance_id / signer_type / signer_name / signature_data(SVG或笔迹JSON)
     *       signer_phone(外部业主,需短信验证时必填)
     */
    public function storeSignature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'process_instance_id'    => 'required|integer|exists:process_instances,id',
            'inspection_id'          => 'nullable|integer',
            'signer_type'            => 'required|string|in:contractor,owner,supervisor,inspector',
            'signer_id'              => 'nullable|integer',
            'signer_name'            => 'required|string|max:50',
            'signer_phone'           => 'nullable|string|max:20',
            'signer_role'            => 'nullable|string|max:50',
            'signature_data'         => 'required|string', // SVG 或 base64 笔迹图
            'signature_image_path'   => 'nullable|string|max:500',
            'expires_at'             => 'nullable|date',
        ]);

        $process = ProcessInstance::findOrFail($data['process_instance_id']);
        $this->ensureInspectionBelongsToProcess($data['inspection_id'] ?? null, $process);

        // 内部用户自动填 signer_id
        if (empty($data['signer_id']) && in_array($data['signer_type'], [ProcessSignature::SIGNER_CONTRACTOR, ProcessSignature::SIGNER_SUPERVISOR, ProcessSignature::SIGNER_INSPECTOR], true)) {
            $data['signer_id'] = $request->user()?->id;
        }

        // 防篡改哈希
        $hashPayload = [
            'process_instance_id' => $data['process_instance_id'],
            'inspection_id'       => $data['inspection_id'] ?? null,
            'signer_type'         => $data['signer_type'],
            'signer_id'           => $data['signer_id'] ?? null,
            'signer_name'         => $data['signer_name'],
            'signer_role'         => $data['signer_role'] ?? null,
            'signature_data_hash' => hash('sha256', $data['signature_data']),
            'signed_at'           => now()->toIso8601String(),
        ];
        $data['hash']         = ProcessSignature::makeHash($hashPayload);
        $data['signed_at']    = now();
        $data['ip_address']   = $request->ip();
        $data['user_agent']   = Str::limit((string)$request->userAgent(), 200, '');

        // 外部业主需要短信验证: 先存记录 + 验证码,is_verified=false
        if ($data['signer_type'] === ProcessSignature::SIGNER_OWNER && !empty($data['signer_phone'])) {
            $data['verification_code'] = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $data['is_verified']       = false;
            // TODO: 发送短信 (下期接短信平台)
        } else {
            $data['is_verified'] = true;
        }

        $sig = ProcessSignature::create($data);
        return response()->json(['code' => 0, 'message' => '签字已记录', 'data' => $sig]);
    }

    /**
     * 短信验证签字(V1.5 业主门户可复用)
     * POST /api/process/signatures/{signature}/verify
     * body: verification_code
     */
    public function verifySignature(Request $request, ProcessSignature $signature): JsonResponse
    {
        $data = $request->validate([
            'verification_code' => 'required|string|size:6',
        ]);
        if ($signature->verification_code !== $data['verification_code']) {
            return response()->json(['code' => 1001, 'message' => '验证码不正确'], 422);
        }
        $signature->is_verified = true;
        $signature->save();
        return response()->json(['code' => 0, 'message' => '验证成功', 'data' => $signature->fresh()]);
    }

    public function destroySignature(ProcessSignature $signature): JsonResponse
    {
        $signature->delete();
        return response()->json(['code' => 0, 'message' => '签字记录已删除']);
    }
}
