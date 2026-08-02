<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_no', 20)->unique()->comment('车牌号');
            $table->string('brand', 50)->comment('品牌');
            $table->string('model', 50)->comment('型号');
            $table->string('color', 20)->nullable()->comment('颜色');
            $table->date('purchase_date')->nullable()->comment('购买日期');
            $table->decimal('purchase_price', 12, 2)->nullable()->comment('购买价格');
            $table->unsignedBigInteger('department_id')->nullable()->comment('使用部门');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->unsignedBigInteger('responsible_user_id')->nullable()->comment('责任人');
            $table->foreign('responsible_user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('status', 50);
            $table->string('vin', 50)->nullable()->comment('车架号');
            $table->string('engine_no', 50)->nullable()->comment('发动机号');
            $table->unsignedTinyInteger('seats')->nullable()->comment('座位数');
            $table->string('fuel_type', 50);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
