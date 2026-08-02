<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('display_name', 100)->comment('显示名称');
            $table->string('guard_name', 50)->default('web');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父级权限');
            $table->foreign('parent_id')->references('id')->on('permissions')->onDelete('set null');
            $table->string('module', 50)->comment('模块');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->text('description')->nullable()->comment('权限描述');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
