<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('标签名');
            $table->string('category', 50)->default('other')->comment('分类');
            $table->string('color', 7)->default('#409EFF')->comment('颜色');
            $table->text('description')->nullable()->comment('描述');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_tags');
    }
};
