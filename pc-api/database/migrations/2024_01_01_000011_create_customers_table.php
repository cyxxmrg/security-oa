<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('customers')) {
            return;
        }

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('客户名称');
            $table->string('credit_code', 50)->nullable()->comment('统一社会信用代码');
            $table->string('industry', 100)->nullable()->comment('行业');
            $table->enum('category', ['vip', 'normal', 'potential'])->default('normal')->comment('客户分类');
            $table->string('province', 50)->comment('省');
            $table->string('city', 50)->comment('市');
            $table->string('district', 50)->comment('区');
            $table->text('address')->comment('详细地址');
            $table->decimal('longitude', 10, 7)->nullable()->comment('经度');
            $table->decimal('latitude', 10, 7)->nullable()->comment('纬度');
            $table->json('tags')->nullable()->comment('自定义标签');
            $table->string('source', 100)->nullable()->comment('来源');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->unsignedBigInteger('assigned_user_id')->nullable()->comment('负责人');
            $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('set null');
            $table->text('description')->nullable()->comment('描述');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
