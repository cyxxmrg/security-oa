<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100)->comment('类型');
            $table->string('title', 255)->comment('标题');
            $table->text('content')->comment('内容');
            $table->json('data')->nullable()->comment('额外数据');
            $table->timestamp('read_at')->nullable()->comment('已读时间');
            $table->unsignedBigInteger('notifiable_id')->comment('接收者ID');
            $table->string('notifiable_type', 100)->comment('接收者类型');
            $table->unsignedBigInteger('sender_id')->nullable()->comment('发送者');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
            $table->string('level', 50);
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('read_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
