<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('vehicle_maintenance_records')) {
            return;
        }

        Schema::create('vehicle_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->comment('车辆ID');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->enum('maintenance_type', ['routine', 'repair', 'inspection'])
                ->default('routine')
                ->comment('保养类型');
            $table->unsignedBigInteger('mileage')->nullable()->comment('里程数');
            $table->decimal('cost', 10, 2)->default(0)->comment('费用');
            $table->date('maintenance_date')->comment('保养日期');
            $table->text('description')->comment('维修保养内容');
            $table->unsignedBigInteger('next_maintenance_mileage')->nullable()->comment('下次保养里程');
            $table->date('next_maintenance_date')->nullable()->comment('下次保养日期');
            $table->unsignedBigInteger('handled_by')->nullable()->comment('经办人');
            $table->foreign('handled_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();

            $table->index('vehicle_id');
            $table->index('maintenance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_records');
    }
};
