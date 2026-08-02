<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_stage_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id');
            $table->string('stage', 30);                                   // inquiry/qualification/site_survey/proposal/negotiating/quoted/won/lost
            $table->string('original_name', 255);                          // 原文件名
            $table->string('stored_path', 500);                            // 存储相对路径
            $table->string('mime_type', 100)->nullable();                  // MIME
            $table->unsignedInteger('file_size')->nullable();              // 字节
            $table->string('notes', 2000)->nullable();                     // 备注
            $table->unsignedBigInteger('uploaded_by')->nullable();         // 上传人
            $table->timestamps();

            $table->index(['opportunity_id', 'stage']);
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_stage_files');
    }
};