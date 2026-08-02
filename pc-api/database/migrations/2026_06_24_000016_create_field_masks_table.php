<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_masks', function (Blueprint $table) {
            $table->id();
            // 端点前缀, e.g. "finance" / "projects" / "sales"
            $table->string('endpoint', 64)->index();
            // 字段名
            $table->string('field', 64);
            // 哪些角色能看真值 (逗号分隔, 空=仅 admin)
            // 例: "admin,finance" 表示 admin + finance 能看, 其他角色 mask
            $table->string('allowed_roles', 128)->default('admin');
            // 中文说明 (admin 配置时的提示)
            $table->string('description', 255)->nullable();
            // 启用标记 (临时关闭某字段脱敏)
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['endpoint', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_masks');
    }
};
