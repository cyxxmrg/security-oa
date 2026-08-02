<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('knowledge_categories')) {
            return;
        }

        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父分类ID');
            $table->foreign('parent_id')->references('id')->on('knowledge_categories')->onDelete('cascade');
            $table->string('name', 100)->comment('分类名称');
            $table->string('icon', 50)->nullable()->comment('图标');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序');
            $table->text('description')->nullable()->comment('描述');
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_categories');
    }
};
