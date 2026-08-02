<?php

namespace App\Console\Commands;

use App\Models\DiskFolder;
use App\Models\Project;
use App\Models\User;
use App\Observers\ProjectDiskObserver;
use App\Observers\UserDiskObserver;
use Illuminate\Console\Command;

/**
 * V1.0 网盘初始化命令
 *
 * 用法: php artisan oa:disk-init
 *
 * 行为（幂等）:
 *  1. 创建 3 个根目录: project / work / share（已存在则跳过）
 *  2. 回填所有已有项目 → 在 project 下建 {项目名}/ 4 子目录
 *  3. 回填所有已有员工 → 在 work 下建 {姓名}/
 */
class DiskInitCommand extends Command
{
    protected $signature = 'oa:disk-init {--force : 重新建根目录（会跳过已存在）}';
    protected $description = '初始化网盘: 3 个根目录 + 回填项目/员工';

    public function handle(): int
    {
        $this->info('=== V1.0 网盘初始化 ===');

        // 1) 建 3 个根目录
        $roots = $this->ensureRoots();
        $this->line("  根目录: project={$roots['project']->id} work={$roots['work']->id} share={$roots['share']->id}");

        // 2) 回填项目
        $projects = Project::all();
        $this->line("  已有项目: {$projects->count()} 个");
        $obs = new ProjectDiskObserver();
        foreach ($projects as $p) {
            $exists = DiskFolder::where('scope', DiskFolder::SCOPE_NONE)
                ->where('project_id', $p->id)
                ->whereNull('parent_id')
                ->where('name', $p->name)
                ->exists();
            if ($exists) {
                $this->line("    - 跳过（已存在）: {$p->name}");
                continue;
            }
            $obs->created($p);
            $this->line("    + 创建: {$p->name}");
        }

        // 3) 回填员工
        $users = User::all();
        $this->line("  已有员工: {$users->count()} 个");
        $uobs = new UserDiskObserver();
        foreach ($users as $u) {
            $exists = DiskFolder::where('scope', DiskFolder::SCOPE_NONE)
                ->where('employee_id', $u->id)
                ->exists();
            if ($exists) {
                $this->line("    - 跳过（已存在）: {$u->name}");
                continue;
            }
            $uobs->created($u);
            $this->line("    + 创建: {$u->name}");
        }

        $this->info('=== 完成 ===');
        $this->table(
            ['scope', 'name', 'is_protected', 'count'],
            DiskFolder::whereIn('scope', [
                DiskFolder::SCOPE_PROJECT_ROOT,
                DiskFolder::SCOPE_WORK_ROOT,
                DiskFolder::SCOPE_SHARE_ROOT,
            ])->get()->map(fn($f) => [
                $f->scope, $f->name, $f->is_protected ? 'YES' : 'no',
                DiskFolder::where('parent_id', $f->id)->count(),
            ])->toArray()
        );
        return self::SUCCESS;
    }

    /**
     * 确保 3 个根目录存在（幂等）
     */
    private function ensureRoots(): array
    {
        $out = [];
        $map = [
            DiskFolder::SCOPE_PROJECT_ROOT => ['project', '项目目录', DiskFolder::SYS_TYPE_PROJECT_ROOT, true],
            DiskFolder::SCOPE_WORK_ROOT    => ['work',    '员工工作', DiskFolder::SYS_TYPE_WORK,         true],
            DiskFolder::SCOPE_SHARE_ROOT   => ['share',   '公共资料', DiskFolder::SYS_TYPE_SHARE,        false],
        ];
        foreach ($map as $scope => [$name, $label, $sysType, $protected]) {
            $existing = DiskFolder::where('scope', $scope)->first();
            if ($existing) {
                $this->line("  根目录已存在: {$name} (id={$existing->id})");
                $out[$scope === DiskFolder::SCOPE_PROJECT_ROOT ? 'project'
                    : ($scope === DiskFolder::SCOPE_WORK_ROOT ? 'work' : 'share')] = $existing;
                continue;
            }
            $folder = DiskFolder::create([
                'parent_id'     => null,
                'name'          => $name,
                'path'          => '/',
                'created_by'    => 1,
                'is_system'     => true,
                'scope'         => $scope,
                'is_protected'  => $protected,
                'system_type'   => $sysType,
            ]);
            $folder->path = '/' . $folder->id . '/';
            $folder->save();
            $this->line("  创建根目录: {$name} (id={$folder->id})");
            $out[$scope === DiskFolder::SCOPE_PROJECT_ROOT ? 'project'
                : ($scope === DiskFolder::SCOPE_WORK_ROOT ? 'work' : 'share')] = $folder;
        }
        return $out;
    }
}
