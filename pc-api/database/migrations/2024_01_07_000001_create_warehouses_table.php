<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('warehouses')) {
            return;
        }

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('仓库名称');
            $table->string('code', 50)->unique()->comment('仓库编码');
            $table->enum('type', ['main', 'project', 'aftermarket'])
                ->default('main')
                ->comment('仓库类型');
            $table->string('address', 255)->nullable()->comment('地址');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('仓库管理员');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['active', 'inactive'])
                ->default('active')
                ->comment('状态');
            $table->text('description')->nullable()->comment('描述');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
