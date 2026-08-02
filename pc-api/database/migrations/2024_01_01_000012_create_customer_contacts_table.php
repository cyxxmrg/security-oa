<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('customer_contacts')) {
            return;
        }

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->comment('客户');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->string('name', 50)->comment('联系人姓名');
            $table->string('position', 100)->nullable()->comment('职位');
            $table->string('phone', 20)->comment('电话');
            $table->string('email', 100)->nullable()->comment('邮箱');
            $table->boolean('is_primary')->default(false)->comment('是否主联系人');
            $table->string('wechat', 50)->nullable()->comment('微信');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
    }
};
