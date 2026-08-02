<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('suppliers')) {
            return;
        }

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('供应商名称');
            $table->string('contact_person', 50)->comment('联系人');
            $table->string('phone', 20)->comment('电话');
            $table->string('email', 100)->nullable()->comment('邮箱');
            $table->string('address', 255)->nullable()->comment('地址');
            $table->string('category', 100)->comment('类别');
            $table->unsignedTinyInteger('rating')->default(0)->comment('评级(1-5)');
            $table->enum('status', ['active', 'blacklisted'])->default('active')->comment('状态');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
