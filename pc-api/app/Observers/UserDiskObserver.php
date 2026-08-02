<?php

namespace App\Observers;

use App\Models\DiskFolder;
use App\Models\User;

/**
 * V1.0 员工 Observer — 创建员工时自动建 work 子文件夹
 */
class UserDiskObserver
{
    public function created(User $user): void
    {
        $workRoot = DiskFolder::where('scope', DiskFolder::SCOPE_WORK_ROOT)->first();
        if (!$workRoot) {
            return;
        }

        // 避免重名（同名员工加 -2 后缀）
        $name = $user->name;
        $counter = 2;
        while (DiskFolder::where('parent_id', $workRoot->id)->where('name', $name)->exists()) {
            $name = $user->name . '-' . $counter++;
        }

        $folder = DiskFolder::create([
            'parent_id'     => $workRoot->id,
            'name'          => $name,
            'path'          => $workRoot->path,
            'created_by'    => $user->id,
            'is_system'     => true,
            'scope'         => DiskFolder::SCOPE_NONE,
            'is_protected'  => false,
            'employee_id'   => $user->id,
            'system_type'   => DiskFolder::SYS_TYPE_WORK,
        ]);
        $folder->path = $workRoot->path . $folder->id . '/';
        $folder->save();
    }
}
