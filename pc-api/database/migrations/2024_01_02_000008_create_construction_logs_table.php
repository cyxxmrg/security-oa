<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('construction_logs')) {
            return;
        }

        Schema::create('construction_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->comment('施工人员');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->date('work_date')->comment('工作日期');
            $table->string('weather', 50)->nullable()->comment('天气');
            $table->text('content')->comment('施工内容');
            $table->text('problems')->nullable()->comment('遇到问题');
            $table->text('solutions')->nullable()->comment('解决方案');
            $table->json('photos')->nullable()->comment('照片JSON数组');
            $table->decimal('work_hours', 4, 1)->default(8)->comment('工时');
            $table->text('location')->nullable()->comment('工作地点');
            $table->enum('status', ['submitted', 'approved'])->default('submitted')->comment('状态');
            $table->timestamps();

            $table->index('project_id');
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_logs');
    }
};
