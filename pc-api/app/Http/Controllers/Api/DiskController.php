<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiskFile;
use App\Models\DiskFolder;
use App\Models\DiskSetting;
use App\Models\User;
use App\Services\DiskPermissionService;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiskController extends Controller
{
    /**
     * 获取服务器磁盘列表（用于管理端选择存储目录）
     *
     * GET /api/disk/disk-list
     */
    public function diskList(): JsonResponse
    {
        $disks = [];
        // Linux: 解析 df 输出
        $output = [];
        exec('df -B1 --output=source,target,size,used,avail,pcent 2>/dev/null', $output);
        foreach ($output as $i => $line) {
            if ($i === 0) continue; // 跳过表头
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 6) continue;
            // 只保留物理分区 /dev/ 和 tmpfs（排除 squashfs/cgroup 等）
            if (!str_starts_with($parts[0], '/dev/') && $parts[0] !== 'tmpfs') continue;
            $disks[] = [
                'device'  => $parts[0],
                'mount'   => $parts[1],
                'total'   => (int) $parts[2],
                'used'    => (int) $parts[3],
                'avail'   => (int) $parts[4],
                'pcent'   => $parts[5],
                'total_fmt'  => $this->formatBytes((int) $parts[2]),
                'avail_fmt'  => $this->formatBytes((int) $parts[4]),
            ];
        }
        // 按可用空间降序
        usort($disks, fn($a, $b) => $b['avail'] - $a['avail']);
        return response()->json(['code' => 0, 'data' => $disks]);
    }

    /**
     * 获取当前网盘设置
     *
     * GET /api/disk/settings
     */
    public function getSettings(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => [
            'initialized' => DiskSetting::get('initialized', false),
            'storage_path' => DiskSetting::get('storage_path', storage_path('app/attachments')),
            'auto_detect' => DiskSetting::get('auto_detect', true),
        ]]);
    }

    /**
     * 保存网盘设置
     *
     * PUT /api/disk/settings  body: {storage_path?, auto_detect?}
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'storage_path' => 'nullable|string|max:500',
            'auto_detect'  => 'nullable|boolean',
        ]);

        if (!empty($data['storage_path'])) {
            $path = $data['storage_path'];
            // 验证目录是否存在或可创建
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            if (!is_dir($path) || !is_writable($path)) {
                return response()->json(['code' => 1001, 'message' => '目录不可写: ' . $path], 422);
            }
            DiskSetting::set('storage_path', rtrim($path, '/'));
        }
        if (isset($data['auto_detect'])) {
            DiskSetting::set('auto_detect', (bool) $data['auto_detect']);
        }

        return response()->json(['code' => 0, 'message' => '设置已保存']);
    }

    /**
     * 初始化网盘：创建 3 个根文件夹
     *
     * POST /api/disk/init  body: {storage_path?, auto_detect?}
     *
     * 自动创建 project_root / work_root / share_root
     * 并根据设置初始化存储目录
     */
    public function initDisk(Request $request): JsonResponse
    {
        if (DiskSetting::get('initialized', false)) {
            return response()->json(['code' => 1001, 'message' => '网盘已初始化，请勿重复操作'], 422);
        }

        $user = $request->user();
        $data = $request->validate([
            'storage_path' => 'nullable|string|max:500',
            'auto_detect'  => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            // 1. 确定存储路径
            $storagePath = null;
            if (!empty($data['storage_path'])) {
                $storagePath = rtrim($data['storage_path'], '/');
            } elseif ($data['auto_detect'] ?? true) {
                // 自动选择最大可用盘
                $disks = $this->diskList()->getData(true)['data'] ?? [];
                if (!empty($disks)) {
                    $best = $disks[0]; // 已按可用降序
                    $storagePath = rtrim($best['mount'], '/') . '/oa_disk';
                }
            }
            if (!$storagePath) {
                $storagePath = storage_path('app/attachments');
            }

            // 创建存储目录
            if (!is_dir($storagePath)) {
                @mkdir($storagePath, 0755, true);
            }
            // 如果自动检测的目录不可写，回退到默认存储路径
            if (!is_dir($storagePath) || !is_writable($storagePath)) {
                if ($data['auto_detect'] ?? true) {
                    $storagePath = storage_path('app/attachments');
                    if (!is_dir($storagePath)) {
                        @mkdir($storagePath, 0755, true);
                    }
                }
            }
            if (!is_dir($storagePath) || !is_writable($storagePath)) {
                DB::rollBack();
                return response()->json(['code' => 1001, 'message' => '存储目录不可写，请手动指定'], 422);
            }

            // 2. 创建 3 个根文件夹
            $roots = [
                ['name' => '项目',   'scope' => DiskFolder::SCOPE_PROJECT_ROOT, 'system_type' => DiskFolder::SYS_TYPE_PROJECT_ROOT],
                ['name' => '工作',   'scope' => DiskFolder::SCOPE_WORK_ROOT,    'system_type' => DiskFolder::SYS_TYPE_WORK],
                ['name' => '公共',   'scope' => DiskFolder::SCOPE_SHARE_ROOT,   'system_type' => DiskFolder::SYS_TYPE_SHARE],
            ];

            foreach ($roots as $r) {
                $folder = DiskFolder::create([
                    'parent_id'    => null,
                    'name'         => $r['name'],
                    'path'         => '/',
                    'created_by'   => $user->id,
                    'is_system'    => true,
                    'scope'        => $r['scope'],
                    'is_protected' => true,
                    'system_type'  => $r['system_type'],
                ]);
                $folder->path = '/' . $folder->id . '/';
                $folder->save();
            }

            // 3. 保存设置
            DiskSetting::set('storage_path', $storagePath);
            DiskSetting::set('auto_detect', $data['auto_detect'] ?? true);
            DiskSetting::set('initialized', true);

            DB::commit();
            return response()->json(['code' => 0, 'message' => '网盘初始化成功']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['code' => 5000, 'message' => '初始化失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 确保项目已关联网盘文件夹（项目创建时调用）
     *
     * POST /api/disk/ensure-project-folder/{project}
     * 由项目模块创建/更新时调用
     */
    public function ensureProjectFolder(Request $request, \App\Models\Project $project): JsonResponse
    {
        // 查找 project_root
        $projectRoot = DiskFolder::where('scope', DiskFolder::SCOPE_PROJECT_ROOT)->first();
        if (!$projectRoot) {
            return response()->json(['code' => 1001, 'message' => '网盘未初始化'], 400);
        }

        // 检查是否已有该项目文件夹
        $folder = DiskFolder::where('parent_id', $projectRoot->id)
            ->where('project_id', $project->id)
            ->first();

        if (!$folder) {
            $folder = DiskFolder::create([
                'parent_id'    => $projectRoot->id,
                'name'         => $project->name,
                'path'         => $projectRoot->path,
                'created_by'   => $request->user()?->id ?? 1,
                'is_system'    => false,
                'scope'        => DiskFolder::SCOPE_NONE,
                'is_protected' => false,
                'system_type'  => DiskFolder::SYS_TYPE_PROJECT_DOC,
                'project_id'   => $project->id,
            ]);
            $folder->path = $projectRoot->path . $folder->id . '/';
            $folder->save();
        }

        return response()->json(['code' => 0, 'message' => '项目文件夹已就绪', 'data' => ['folder_id' => $folder->id]]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    /**
    /**
     * 文件夹树（带权限过滤）
     *
     * GET /api/disk/tree
     *  - 不带 scope: 返回所有 3 个根（用户能看到的）
     *  - 带 scope=project/work/share: 返回该根 + 1 层子（懒加载由前端递归）
     */
    public function tree(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = $request->query('scope');

        if ($scope) {
            $root = DiskFolder::where('scope', $scope)->first();
            if (!$root) {
                return response()->json(['code' => 0, 'data' => []]);
            }
            return response()->json([
                'code' => 0,
                'data' => $this->buildTreeForUser($user, $root),
            ]);
        }

        // 返回所有 3 个根
        $roots = DiskFolder::whereIn('scope', [
            DiskFolder::SCOPE_PROJECT_ROOT,
            DiskFolder::SCOPE_WORK_ROOT,
            DiskFolder::SCOPE_SHARE_ROOT,
        ])->get();

        $data = [];
        foreach ($roots as $root) {
            $data[] = $this->buildTreeForUser($user, $root);
        }
        return response()->json(['code' => 0, 'data' => $data]);
    }

    private function buildTreeForUser(User $user, DiskFolder $root): array
    {
        $children = DiskFolder::where('parent_id', $root->id)
            ->orderBy('name')
            ->get()
            ->filter(fn($f) => DiskPermissionService::canView($user, $f))
            ->map(fn($f) => $this->buildTreeForUser($user, $f))
            ->values()
            ->toArray();

        return [
            'id'            => $root->id,
            'parent_id'     => $root->parent_id,
            'name'          => $root->name,
            'is_system'     => $root->is_system,
            'is_protected'  => (bool) $root->is_protected,
            'scope'         => $root->scope,
            'system_type'   => $root->system_type,
            'project_id'    => $root->project_id,
            'employee_id'   => $root->employee_id,
            'children'      => $children,
        ];
    }

    /**
     * 文件夹列表（子文件夹 + 文件，平铺）
     *
     * GET /api/disk/folders?parent_id=X
     */
    public function folders(Request $request): JsonResponse
    {
        $user = $request->user();
        $parentId = $request->query('parent_id');

        $folders = DiskFolder::withCount('files')
            ->where('parent_id', $parentId ?: null)
            ->orderBy('name')
            ->get()
            ->filter(fn($f) => DiskPermissionService::canView($user, $f))
            ->values()
            ->map(fn($f) => $this->folder2api($f));

        return response()->json(['code' => 0, 'data' => $folders]);
    }

    /**
     * 文件列表
     *
     * GET /api/disk/files?folder_id=X
     */
    public function files(Request $request): JsonResponse
    {
        $user = $request->user();
        $folderId = $request->query('folder_id');

        $folder = $folderId ? DiskFolder::find($folderId) : null;
        if ($folder && !DiskPermissionService::canView($user, $folder)) {
            return response()->json(['code' => 1003, 'message' => '无权访问此文件夹'], 403);
        }

        $items = DiskFile::where('folder_id', $folderId)
            ->orderByDesc('id')
            ->paginate(50);

        // 把 data.items 改造成包含 uploader name
        $items->getCollection()->transform(function ($f) {
            $f->uploader_name = $f->uploadedByUser?->name;
            return $f;
        });

        return response()->json(['code' => 0, 'data' => $items]);
    }

    /**
     * 新建子文件夹
     *
     * POST /api/disk/folders  body: {name, parent_id}
     */
    public function createFolder(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name'      => 'required|string|max:200',
            'parent_id' => 'nullable|exists:disk_folders,id',
        ]);

        $parent = $validated['parent_id'] ? DiskFolder::find($validated['parent_id']) : null;
        if ($parent && !DiskPermissionService::canWrite($user, $parent)) {
            return response()->json(['code' => 1003, 'message' => '无权在此创建文件夹'], 403);
        }

        $folder = DiskFolder::create([
            'parent_id'     => $validated['parent_id'] ?? null,
            'name'          => $validated['name'],
            'path'          => $parent ? $parent->path : '/',
            'created_by'    => $user->id,
            'is_system'     => false,
            'scope'         => DiskFolder::SCOPE_NONE,
            'is_protected'  => false,
            'system_type'   => null,
        ]);
        $folder->path = ($parent ? $parent->path : '/') . $folder->id . '/';
        $folder->save();

        return response()->json(['code' => 0, 'message' => '创建成功', 'data' => $this->folder2api($folder)]);
    }

    /**
     * 重命名文件夹
     *
     * PUT /api/disk/folders/{folder}  body: {name}
     */
    public function renameFolder(Request $request, DiskFolder $folder): JsonResponse
    {
        $user = $request->user();
        if (!DiskPermissionService::canMutate($user, $folder)) {
            return response()->json(['code' => 1003, 'message' => '此文件夹受保护，无法修改'], 403);
        }
        $validated = $request->validate(['name' => 'required|string|max:200']);
        $folder->name = $validated['name'];
        $folder->save();
        return response()->json(['code' => 0, 'message' => '已重命名', 'data' => $this->folder2api($folder)]);
    }

    /**
     * 重命名文件（仅改 name/original_name，不动物理路径）
     */
    public function renameFile(Request $request, DiskFile $file): JsonResponse
    {
        $user = $request->user();
        $folder = $file->folder;
        if (!$folder || !DiskPermissionService::canWrite($user, $folder)) {
            return response()->json(['code' => 1003, 'message' => '无权修改'], 403);
        }
        $validated = $request->validate([
            'name'          => 'nullable|string|max:255',
            'original_name' => 'nullable|string|max:255',
        ]);
        if (isset($validated['name']))          $file->name = $validated['name'];
        if (isset($validated['original_name'])) $file->original_name = $validated['original_name'];
        $file->save();
        return response()->json(['code' => 0, 'message' => '已重命名', 'data' => $file]);
    }

    /**
     * 上传文件
     *
     * POST /api/disk/upload  body: {file, folder_id}
     *
     * 合规: 走 FileUploadService 统一校验 (audit-2026-06-28 P1: 抽离上传逻辑)
     */
    public function upload(Request $request, FileUploadService $uploader): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            // 合规 (audit-2026-06-28 C1): 加 mimes 防任意文件上传 — attachments disk 私有但运维误配 public 即 RCE
            'file'      => 'required|file|max:51200|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,md,zip,rar',
            'folder_id' => 'required|exists:disk_folders,id',
        ]);

        $folder = DiskFolder::findOrFail($request->folder_id);
        if (!DiskPermissionService::canWrite($user, $folder)) {
            return response()->json(['code' => 1003, 'message' => '无权上传到此文件夹'], 403);
        }

        // 统一上传服务 (P1 重构): 自动 extension + 真实 MIME 双重校验, 自动 SHA256, 自动 UUID 文件名
        $result = $uploader->store($request, 'file', [
            'disk'         => 'attachments',
            'subdir'       => 'attachments/' . date('Y/m'),
            'max_size'     => 51200,
        ]);

        $record = DiskFile::create([
            'folder_id'     => $folder->id,
            'name'          => $result['original_name'],
            'original_name' => $result['original_name'],
            'extension'     => $result['ext'],
            'mime_type'     => $result['mime'],
            'size'          => $result['size'],
            'path'          => $result['path'],
            'uploaded_by'   => $user->id,
            'version'       => 1,
        ]);

        return response()->json(['code' => 0, 'message' => '上传成功', 'data' => $record]);
    }

    /**
     * 下载文件
     *
     * GET /api/disk/files/{file}/download
     */
    public function download(DiskFile $file): BinaryFileResponse|JsonResponse
    {
        $user = request()->user();
        $folder = $file->folder;
        if (!$folder || !DiskPermissionService::canView($user, $folder)) {
            return response()->json(['code' => 1003, 'message' => '无权下载'], 403);
        }
        $disk = Storage::disk('attachments');
        if (!$disk->exists($file->path)) {
            return response()->json(['code' => 1004, 'message' => '文件已丢失'], 404);
        }
        return response()->download(
            $disk->path($file->path),
            $file->original_name,
            ['Content-Type' => $file->mime_type]
        );
    }

    /**
     * 删除文件夹
     *
     * DELETE /api/disk/folders/{folder}
     */
    public function destroyFolder(Request $request, DiskFolder $folder): JsonResponse
    {
        $user = $request->user();
        if (!DiskPermissionService::canMutate($user, $folder)) {
            return response()->json(['code' => 1003, 'message' => '此文件夹受保护，无法删除'], 403);
        }
        if ($folder->files()->count() > 0 || DiskFolder::where('parent_id', $folder->id)->count() > 0) {
            return response()->json(['code' => 1001, 'message' => '文件夹非空，无法删除']);
        }
        $folder->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    /**
     * 删除文件
     */
    public function destroyFile(Request $request, DiskFile $file): JsonResponse
    {
        $user = $request->user();
        $folder = $file->folder;
        if (!$folder || !DiskPermissionService::canWrite($user, $folder)) {
            return response()->json(['code' => 1003, 'message' => '无权删除'], 403);
        }
        Storage::disk('attachments')->delete($file->path);
        $file->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    /**
     * 统计（首页仪表盘可能用到）
     *
     * GET /api/disk/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $initialized = DiskSetting::get('initialized', false);
        $st = DiskSetting::get('storage_path', storage_path('app/attachments'));

        $projectCount = 0;
        $employeeCount = 0;
        $totalFiles = 0;
        $diskUsage = 0;
        $diskLimit = 0;

        if ($initialized) {
            $projectCount = DiskFolder::where('scope', DiskFolder::SCOPE_NONE)
                ->whereNotNull('project_id')->whereNull('parent_id')->count();
            $employeeCount = DiskFolder::where('scope', DiskFolder::SCOPE_NONE)
                ->whereNotNull('employee_id')->count();
            $totalFiles = DiskFile::count();

            // 存储用量
            $diskLimit = disk_total_space($st) ?: 0;
            $diskUsage = disk_free_space($st) ? ($diskLimit - disk_free_space($st)) : 0;
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'initialized'     => $initialized,
                'storage_path'    => $st,
                'project_folders' => $projectCount,
                'employee_folders' => $employeeCount,
                'total_files'     => $totalFiles,
                'disk_usage'      => $diskUsage,
                'disk_limit'      => $diskLimit,
            ],
        ]);
    }

    /**
     * 文件夹 → API 输出
     */
    private function folder2api(DiskFolder $f): array
    {
        return [
            'id'            => $f->id,
            'parent_id'     => $f->parent_id,
            'name'          => $f->name,
            'path'          => $f->path,
            'is_system'     => (bool) $f->is_system,
            'is_protected'  => (bool) $f->is_protected,
            'scope'         => $f->scope,
            'system_type'   => $f->system_type,
            'project_id'    => $f->project_id,
            'employee_id'   => $f->employee_id,
            'files_count'   => $f->files_count ?? 0,
            'created_at'    => $f->created_at,
            'updated_at'    => $f->updated_at,
        ];
    }
}
