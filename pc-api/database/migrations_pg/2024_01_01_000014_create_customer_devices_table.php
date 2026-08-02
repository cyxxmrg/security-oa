<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->comment('客户');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedBigInteger('project_id')->nullable()->comment('关联项目');
            $table->string('device_name', 200)->comment('设备名称');
            $table->string('device_type', 50)->default('camera')->comment('设备类型');
            $table->string('brand', 100)->comment('品牌');
            $table->string('model', 100)->comment('型号');
            $table->string('serial_number', 100)->unique()->nullable()->comment('序列号');
            $table->string('install_location', 200)->nullable()->comment('安装位置');
            $table->date('install_date')->nullable()->comment('安装日期');
            $table->date('warranty_end')->nullable()->comment('保修到期日');
            $table->string('status', 50)->default('normal')->comment('状态');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_devices');
    }
};
