<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.2 供应商联系人
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_contacts')) {
            return;
        }

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->string('name', 50)->comment('联系人姓名');
            $table->string('position', 50)->nullable()->comment('职位');
            $table->string('phone', 20)->nullable()->comment('手机');
            $table->string('tel', 20)->nullable()->comment('座机');
            $table->string('email', 100)->nullable()->comment('邮箱');
            $table->string('wechat', 50)->nullable()->comment('微信');
            $table->boolean('is_primary')->default(false)->comment('是否主联系人');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_contacts');
    }
};
