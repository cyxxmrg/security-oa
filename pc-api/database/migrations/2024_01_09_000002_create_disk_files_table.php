<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('disk_files')) {
            return;
        }

        Schema::create('disk_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('folder_id')->comment('文件夹ID');
            $table->foreign('folder_id')->references('id')->on('disk_folders')->onDelete('cascade');
            $table->string('name', 255)->comment('文件名');
            $table->string('original_name', 255)->comment('原始文件名');
            $table->string('extension', 10)->comment('扩展名');
            $table->string('mime_type', 100)->comment('MIME类型');
            $table->unsignedBigInteger('size')->comment('文件大小(字节)');
            $table->string('path', 500)->comment('存储路径');
            $table->unsignedBigInteger('uploaded_by')->comment('上传人');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('restrict');
            $table->unsignedInteger('version')->default(1)->comment('版本');
            $table->text('description')->nullable()->comment('描述');
            $table->boolean('is_starred')->default(false)->comment('是否收藏');
            $table->timestamps();

            $table->index('folder_id');
            $table->index('uploaded_by');
            $table->index('extension');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disk_files');
    }
};
