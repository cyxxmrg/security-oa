<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('sales_follow_ups')) {
            return;
        }

        Schema::create('sales_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 20)->comment('lead/opp/quote');
            $table->unsignedBigInteger('target_id')->comment('关联对象ID');
            $table->string('contact_method', 20)->nullable()->comment('phone/wechat/visit/email/other');
            $table->text('content')->comment('沟通内容');
            $table->text('result')->nullable()->comment('沟通结果');
            $table->text('next_action')->nullable();
            $table->date('next_action_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->comment('跟进人');
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('user_id');
        });

        Schema::create('sales_follow_up_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('follow_up_id')->comment('跟进记录ID');
            $table->foreign('follow_up_id')->references('id')->on('sales_follow_ups')->onDelete('cascade');
            $table->string('name', 200)->comment('文件名');
            $table->string('path', 500)->comment('存储路径');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('follow_up_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_follow_up_attachments');
        Schema::dropIfExists('sales_follow_ups');
    }
};
