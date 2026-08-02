<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('vehicle_insurance')) {
            return;
        }

        Schema::create('vehicle_insurance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->comment('车辆ID');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->string('insurance_company', 100)->comment('保险公司');
            $table->string('policy_no', 100)->comment('保单号');
            $table->enum('type', ['compulsory', 'commercial'])
                ->default('commercial')
                ->comment('险种');
            $table->decimal('premium', 10, 2)->comment('保费');
            $table->date('start_date')->comment('起始日期');
            $table->date('end_date')->comment('结束日期');
            $table->enum('status', ['active', 'expired'])
                ->default('active')
                ->comment('状态');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('vehicle_id');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_insurance');
    }
};
