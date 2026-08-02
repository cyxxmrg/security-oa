<?php

namespace App\Services;

use App\Models\DiskFolder;
use App\Models\Project;
use App\Models\User;

/**
 * V1.0 网盘权限服务
 *
 * 规则:
 *  - share 根及子目录：全员可读、可写
 *  - work 根：所有人都能看见 work 根和别人的子文件夹名字（导航用），但只能读写自己的子文件夹
 *  - work/{自己姓名}：仅自己可读写（admin 除外）
 *  - project 根：全员可见（导航用）
 *  - project/{项目名}：仅项目成员 + admin 可访问
 *  - project/{项目名}/{子目录}：跟随父项目
 *  - 受保护根（is_protected=true）：禁止删除、重命名、移动
 */
class DiskPermissionService
{
    /**
     * 判断用户能否访问文件夹
     */
    public static function canView(User $user, DiskFolder $folder): bool
    {
        // admin 全权
        if ($user->hasRole('admin')) {
            return true;
        }

        // share 根和子目录：全员可访问
        if (self::isUnderShare($folder)) {
            return true;
        }

        // work 根：所有人能看见（导航）
        if ($folder->scope === DiskFolder::SCOPE_WORK_ROOT) {
            return true;
        }

        // work/{员工姓名}：仅该员工可访问
        if ($folder->scope === DiskFolder::SCOPE_NONE && $folder->employee_id) {
            return (int) $folder->employee_id === (int) $user->id;
        }

        // work 下其他子目录：跟随父级
        if (self::isUnderWork($folder) && $folder->employee_id) {
            return (int) $folder->employee_id === (int) $user->id;
        }

        // project 根：所有人能看见（导航）
        if ($folder->scope === DiskFolder::SCOPE_PROJECT_ROOT) {
            return true;
        }

        // project/{项目名} 及子目录：仅项目成员可访问
        $project = self::resolveProject($folder);
        if ($project) {
            return self::isProjectMember($user, $project);
        }

        // 普通文件夹：创建者 + 父级可访问
        return (int) $folder->created_by === (int) $user->id;
    }

    /**
     * 判断用户能否写（创建子文件夹/上传/删除文件）
     */
    public static function canWrite(User $user, DiskFolder $folder): bool
    {
        if (!self::canView($user, $folder)) {
            return false;
        }

        // admin 全权
        if ($user->hasRole('admin')) {
            return true;
        }

        // share 子目录：可写
        if (self::isUnderShare($folder)) {
            return true;
        }

        // work/{自己}：可写
        if ($folder->employee_id && (int) $folder->employee_id === (int) $user->id) {
            return true;
        }

        // project/{项目名} 及子目录：项目成员可写
        $project = self::resolveProject($folder);
        if ($project) {
            return self::isProjectMember($user, $project);
        }

        return false;
    }

    /**
     * 判断用户能否改/删文件夹本身
     */
    public static function canMutate(User $user, DiskFolder $folder): bool
    {
        // 受保护根（project_root / work_root）：禁止操作
        if ($folder->is_protected) {
            return $user->hasRole('admin'); // 只有 admin 能改（实际也不让改，但留 admin 逃生口）
        }

        // share 根：可改（admin 才行）
        if ($folder->scope === DiskFolder::SCOPE_SHARE_ROOT) {
            return $user->hasRole('admin');
        }

        // 业务子文件夹：跟随 write 权限
        return self::canWrite($user, $folder);
    }

    /**
     * 是否是 share 根或子目录
     */
    public static function isUnderShare(DiskFolder $folder): bool
    {
        if ($folder->scope === DiskFolder::SCOPE_SHARE_ROOT) {
            return true;
        }
        // 父级链回溯
        $cur = $folder;
        while ($cur->parent_id) {
            $cur = DiskFolder::find($cur->parent_id);
            if (!$cur) {
                break;
            }
            if ($cur->scope === DiskFolder::SCOPE_SHARE_ROOT) {
                return true;
            }
        }
        return false;
    }

    /**
     * 是否是 work 根或子目录
     */
    public static function isUnderWork(DiskFolder $folder): bool
    {
        if ($folder->scope === DiskFolder::SCOPE_WORK_ROOT) {
            return true;
        }
        $cur = $folder;
        while ($cur->parent_id) {
            $cur = DiskFolder::find($cur->parent_id);
            if (!$cur) {
                break;
            }
            if ($cur->scope === DiskFolder::SCOPE_WORK_ROOT) {
                return true;
            }
        }
        return false;
    }

    /**
     * 是否是 project 根或子目录
     */
    public static function isUnderProject(DiskFolder $folder): bool
    {
        if ($folder->scope === DiskFolder::SCOPE_PROJECT_ROOT) {
            return true;
        }
        $cur = $folder;
        while ($cur->parent_id) {
            $cur = DiskFolder::find($cur->parent_id);
            if (!$cur) {
                break;
            }
            if ($cur->scope === DiskFolder::SCOPE_PROJECT_ROOT) {
                return true;
            }
        }
        return false;
    }

    /**
     * 解析文件夹所属项目
     */
    public static function resolveProject(DiskFolder $folder): ?Project
    {
        if ($folder->project_id) {
            return Project::find($folder->project_id);
        }
        // 父级链回溯
        $cur = $folder;
        while ($cur->parent_id) {
            $cur = DiskFolder::find($cur->parent_id);
            if (!$cur) {
                break;
            }
            if ($cur->project_id) {
                return Project::find($cur->project_id);
            }
        }
        return null;
    }

    /**
     * 是否项目成员
     */
    public static function isProjectMember(User $user, Project $project): bool
    {
        if ($project->manager_id && (int) $project->manager_id === (int) $user->id) {
            return true;
        }
        return $project->members()->where('users.id', $user->id)->exists();
    }
}
