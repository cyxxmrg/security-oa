<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * V1.0 网盘增强 — disk_folders 加 scope/is_protected/employee_id/system_type
     *
     * scope 枚举: project_root / work_root / share_root / none
     *   - project_root: 项目根（不可改/删）
     *   - work_root:    员工工作根（不可改/删）
     *   - share_root:   公共资料根（可改）
     *   - none:         普通用户创建的子文件夹
     *
     * is_protected: 标识根目录是否被锁定（防止误删/误改）
     * employee_id:  work 文件夹指向的员工（用于权限校验）
     * system_type:  前端展示用（project_root / project_doc / work / share）
     */
    public function up(): void
    {
        if (!Schema::hasTable('disk_folders')) {
            return; // disk_folders 还没建（早期环境），等基线 migration 跑
        }

        Schema::table('disk_folders', function (Blueprint $table) {
            if (!Schema::hasColumn('disk_folders', 'scope')) {
                $table->string('scope', 30)->default('none')->after('is_system')
                    ->comment('project_root / work_root / share_root / none');
            }
            if (!Schema::hasColumn('disk_folders', 'is_protected')) {
                $table->boolean('is_protected')->default(false)->after('scope')
                    ->comment('是否锁定（根目录不可改/删）');
            }
            if (!Schema::hasColumn('disk_folders', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('project_id')
                    ->comment('work 文件夹指向的员工');
                $table->foreign('employee_id')->references('id')->on('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('disk_folders', 'system_type')) {
                $table->string('system_type', 50)->nullable()->after('employee_id')
                    ->comment('前端展示类型: project_root/project_doc/work/share');
            }
            if (!Schema::hasIndex('disk_folders', 'disk_folders_scope_index')) {
                $table->index('scope');
            }
            if (!Schema::hasIndex('disk_folders', 'disk_folders_employee_id_index')) {
                $table->index('employee_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('disk_folders')) {
            return;
        }
        Schema::table('disk_folders', function (Blueprint $table) {
            $cols = ['scope', 'is_protected', 'system_type'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('disk_folders', $c)) {
                    $table->dropColumn($c);
                }
            }
            if (Schema::hasColumn('disk_folders', 'employee_id')) {
                $table->dropForeign(['employee_id']);
                $table->dropColumn('employee_id');
            }
        });
    }
};
