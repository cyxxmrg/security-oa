<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('system_dicts')) {
            return;
        }

        Schema::create('system_dicts', function (Blueprint $table) {
            $table->id();
            // 字典分类: repair_method / customer_source / device_type / region / fault_type / urgency / payment_method / product_unit
            $table->string('kind', 50)->index();
            $table->string('code', 50)->comment('字典项 key (e.g. paid_repair)');
            $table->string('label', 100)->comment('中文/显示名');
            $table->string('color', 20)->nullable()->comment('前端 el-tag type: success/warning/danger/info/primary');
            $table->string('icon', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false)->comment('是否该 kind 的默认值');
            $table->text('description')->nullable();
            $table->jsonb('extra')->nullable()->comment('扩展元数据');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'code']);
            $table->index(['kind', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_dicts');
    }
};
