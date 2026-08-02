<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('positions')) {
            return;
        }

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('岗位名称');
            $table->unsignedBigInteger('department_id')->comment('所属部门');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            $table->string('level', 50)->comment('职级');
            $table->text('description')->nullable()->comment('岗位描述');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
