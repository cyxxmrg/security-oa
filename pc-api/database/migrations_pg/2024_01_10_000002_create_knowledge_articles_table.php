<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->string('status', 50);
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->text('summary')->nullable()->comment('摘要');
            $table->string('cover_image', 255)->nullable()->comment('封面图');
            $table->timestamps();

            $table->index('category_id');
            $table->index('author_id');
            $table->index('status');
            $table->index('published_at');
        });

        // v3.9.0 PG: fullText 改 GIN (在闭包外, 单独 try/catch 防止阻塞)
        try {
            DB::statement("CREATE INDEX IF NOT EXISTS idx_knowledge_articles_fts ON knowledge_articles USING GIN(to_tsvector('simple', title, summary))");
        } catch (\Exception $e) {
            // 全文索引失败不阻塞主表
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};
