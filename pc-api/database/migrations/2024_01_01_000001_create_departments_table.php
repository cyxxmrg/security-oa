<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('departments')) {
            return;
        }

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('部门名称');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('上级部门');
            $table->foreign('parent_id')->references('id')->on('departments')->onDelete('set null');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('部门负责人');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->text('description')->nullable()->comment('部门描述');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
