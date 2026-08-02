<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('岗位名称');
            $table->unsignedBigInteger('department_id')->comment('所属部门');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            $table->string('level', 50)->comment('职级');
            $table->text('description')->nullable()->comment('岗位描述');
            $table->string('status', 50)->default('active')->comment('状态');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
