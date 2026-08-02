<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('姓名');
            $table->string('username', 50)->unique()->comment('用户名');
            $table->string('email', 100)->unique()->nullable()->comment('邮箱');
            $table->string('phone', 20)->unique()->comment('手机号');
            $table->string('password')->comment('密码');
            $table->string('avatar', 255)->nullable()->comment('头像路径');
            $table->unsignedBigInteger('department_id')->nullable()->comment('所属部门');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->unsignedBigInteger('position_id')->nullable()->comment('岗位');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            $table->string('gender', 50)->default('other')->comment('性别');
            $table->string('id_card', 20)->nullable()->comment('身份证号');
            $table->string('status', 50)->default('inactive')->comment('状态');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->string('last_login_ip', 45)->nullable()->comment('最后登录IP');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
