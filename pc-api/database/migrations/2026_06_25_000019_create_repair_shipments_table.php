<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('repair_shipments')) {
            return;
        }

        Schema::create('repair_shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repair_order_id')->index();
            $table->string('direction', 16)->comment('outbound=去程, inbound=回程');

            // 物流公司
            $table->string('carrier', 32)->nullable()->comment('顺丰/京东/中通/...');
            $table->string('tracking_no', 64)->nullable();
            $table->decimal('cost', 10, 2)->default(0);

            // 时间
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('estimated_arrival')->nullable();
            $table->timestamp('actual_arrival')->nullable();
            $table->string('delivery_status', 32)->default('pending')
                ->comment('pending/in_transit/delivered/exception');

            // 发件人
            $table->string('sender_name', 64)->nullable();
            $table->string('sender_phone', 32)->nullable();
            $table->string('sender_address', 255)->nullable();

            // 收件人
            $table->string('receiver_name', 64)->nullable();
            $table->string('receiver_phone', 32)->nullable();
            $table->string('receiver_address', 255)->nullable();

            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'direction'], 'rs_order_dir_idx');
            $table->index('tracking_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_shipments');
    }
};
