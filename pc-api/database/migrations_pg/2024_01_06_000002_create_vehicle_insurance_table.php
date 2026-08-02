<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_insurance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->comment('车辆ID');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->string('insurance_company', 100)->comment('保险公司');
            $table->string('policy_no', 100)->comment('保单号');
            $table->string('type', 50);
            $table->decimal('premium', 10, 2)->comment('保费');
            $table->date('start_date')->comment('起始日期');
            $table->date('end_date')->comment('结束日期');
            $table->string('status', 50);
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
