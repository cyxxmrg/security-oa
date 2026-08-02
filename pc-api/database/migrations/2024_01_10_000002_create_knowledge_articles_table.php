<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('knowledge_articles')) {
            return;
        }

        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->comment('分类ID');
            $table->foreign('category_id')->references('id')->on('knowledge_categories')->onDelete('restrict');
            $table->string('title', 255)->comment('标题');
            $table->longText('content')->comment('内容');
            $table->unsignedBigInteger('author_id')->comment('作者');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
            $table->json('tags')->nullable()->comment('标签');
            $table->unsignedInteger('view_count')->default(0)->comment('浏览数');
            $table->unsignedInteger('like_count')->default(0)->comment('点赞数');
            $table->enum('status', ['draft', 'published', 'archived'])
                ->default('draft')
                ->comment('状态');
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->text('summary')->nullable()->comment('摘要');
            $table->string('cover_image', 255)->nullable()->comment('封面图');
            $table->timestamps();

            $table->index('category_id');
            $table->index('author_id');
            $table->index('status');
            $table->index('published_at');
            $table->fullText(['title', 'summary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};
