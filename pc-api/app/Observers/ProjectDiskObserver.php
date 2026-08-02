<?php

namespace App\Observers;

use App\Models\DiskFolder;
use App\Models\Project;

/**
 * V1.0 项目 Observer — 创建项目时自动建网盘子目录
 *
 * 行为:
 *  - project created: 在 project 根下建 {项目名}/，并预建 4 个默认子目录（合同/报销/验收/报告）
 *  - project deleted: 标记网盘文件夹为废弃（不真删，保留可查）
 */
class ProjectDiskObserver
{
    /** 默认子目录 */
    public const DEFAULT_SUB_FOLDERS = ['合同', '报销', '验收', '报告'];

    public function created(Project $project): void
    {
        $projectRoot = DiskFolder::where('scope', DiskFolder::SCOPE_PROJECT_ROOT)->first();
        if (!$projectRoot) {
            // 根目录还没初始化（应在 init 时建好）
            return;
        }

        // 1. 建项目文件夹
        $projectFolder = DiskFolder::create([
            'parent_id'     => $projectRoot->id,
            'name'          => $project->name,
            'path'          => $projectRoot->path, // 先占位，下方更新
            'created_by'    => $project->manager_id ?? 1,
            'is_system'     => true,
            'project_id'    => $project->id,
            'scope'         => DiskFolder::SCOPE_NONE,
            'is_protected'  => false,
            'system_type'   => DiskFolder::SYS_TYPE_PROJECT_DOC,
        ]);
        $projectFolder->path = $projectRoot->path . $projectFolder->id . '/';
        $projectFolder->save();

        // 2. 建 4 个默认子目录
        foreach (self::DEFAULT_SUB_FOLDERS as $subName) {
            $sub = DiskFolder::create([
                'parent_id'     => $projectFolder->id,
                'name'          => $subName,
                'path'          => $projectFolder->path,
                'created_by'    => $project->manager_id ?? 1,
                'is_system'     => false,
                'project_id'    => $project->id,
                'scope'         => DiskFolder::SCOPE_NONE,
                'is_protected'  => false,
                'system_type'   => null,
            ]);
            $sub->path = $projectFolder->path . $sub->id . '/';
            $sub->save();
        }
    }

    public function deleted(Project $project): void
    {
        // 不真删文件夹（项目可能误删，保留文件可查）
        // 这里可以做归档标记，但当前实现保留
    }
}
