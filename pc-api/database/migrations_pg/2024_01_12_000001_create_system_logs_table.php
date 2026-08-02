<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作用户');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('type', 50);
            $table->string('module', 50)->nullable()->comment('模块');
            $table->string('action', 100)->nullable()->comment('操作');
            $table->text('description')->nullable()->comment('操作描述');
            $table->string('ip', 45)->nullable()->comment('IP地址');
            $table->text('user_agent')->nullable()->comment('用户代理');
            $table->json('request_data')->nullable()->comment('请求数据');
            $table->unsignedSmallInteger('response_code')->nullable()->comment('响应码');
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
            $table->index('module');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
