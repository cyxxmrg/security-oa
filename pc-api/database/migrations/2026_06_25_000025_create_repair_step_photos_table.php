<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // V0.5.7 块2 — 维修过程照片表 (工单+返修共用, 7 步进度)
        Schema::create('repair_step_photos', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 32)->comment('work_order | repair_order');
            $table->unsignedBigInteger('target_id')->comment('工单或返修单 id');
            $table->string('step', 32)->comment('诊断/拆机/换件/调试/通电/测试/包装/其他');
            $table->string('file_path', 500);
            $table->string('file_name', 255)->nullable();
            $table->string('file_type', 64)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->index(['target_type', 'target_id', 'step'], 'rsp_target_idx');
            $table->index('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_step_photos');
    }
};
