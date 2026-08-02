<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('disk_folders')) {
            return;
        }

        Schema::create('disk_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父文件夹ID');
            $table->foreign('parent_id')->references('id')->on('disk_folders')->onDelete('cascade');
            $table->string('name', 200)->comment('文件夹名');
            $table->string('path', 500)->comment('完整路径');
            $table->unsignedBigInteger('created_by')->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->boolean('is_system')->default(false)->comment('是否系统目录');
            $table->unsignedBigInteger('project_id')->nullable()->comment('关联项目');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->timestamps();

            $table->index('parent_id');
            $table->index('created_by');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disk_folders');
    }
};
