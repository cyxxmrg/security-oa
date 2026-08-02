<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('员工ID');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->date('date')->comment('打卡日期');
            $table->time('clock_in')->nullable()->comment('上班打卡时间');
            $table->string('clock_in_location', 200)->nullable()->comment('上班打卡地点');
            $table->decimal('clock_in_lat', 10, 7)->nullable()->comment('上班打卡纬度');
            $table->decimal('clock_in_lng', 10, 7)->nullable()->comment('上班打卡经度');
            $table->time('clock_out')->nullable()->comment('下班打卡时间');
            $table->string('clock_out_location', 200)->nullable()->comment('下班打卡地点');
            $table->decimal('clock_out_lat', 10, 7)->nullable()->comment('下班打卡纬度');
            $table->decimal('clock_out_lng', 10, 7)->nullable()->comment('下班打卡经度');
            $table->string('status', 50);
            $table->decimal('work_hours', 4, 1)->default(0)->comment('工作小时');
            $table->decimal('overtime_hours', 4, 1)->default(0)->comment('加班小时');
            $table->unsignedBigInteger('project_id')->nullable()->comment('外勤关联项目');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
