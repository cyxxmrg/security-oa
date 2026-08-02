<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('fuel_cards')) {
            return;
        }

        Schema::create('fuel_cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_no', 50)->unique()->comment('油卡卡号');
            $table->string('card_name', 100)->nullable()->comment('油卡名称 (中石化/中石油等)');
            $table->unsignedBigInteger('vehicle_id')->nullable()->comment('绑定车辆');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('set null');
            $table->decimal('balance', 12, 2)->default(0)->comment('当前余额');
            $table->enum('status', ['active', 'lost', 'expired'])->default('active')->comment('状态');
            $table->date('issue_date')->nullable()->comment('发卡日期');
            $table->date('expire_date')->nullable()->comment('到期日期');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('vehicle_id');
            $table->index('status');
        });

        Schema::create('fuel_card_recharges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_id');
            $table->foreign('card_id')->references('id')->on('fuel_cards')->onDelete('cascade');
            $table->decimal('amount', 12, 2)->comment('充值金额');
            $table->date('recharge_date')->comment('充值日期');
            $table->string('payment_method', 50)->nullable()->comment('支付方式(转账/微信/支付宝/现金)');
            $table->string('operator', 50)->nullable()->comment('经办人');
            $table->string('voucher_no', 100)->nullable()->comment('凭证号');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('card_id');
            $table->index('recharge_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_card_recharges');
        Schema::dropIfExists('fuel_cards');
    }
};
