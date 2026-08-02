<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_shipments')) {
            return;
        }

        Schema::create('purchase_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('发货单号 SH-YYYY-NNN');
            $table->foreignId('contract_id')->nullable()->comment('关联合同')->constrained('purchase_contracts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('shipped_at')->nullable()->comment('发货日期');
            $table->date('expected_arrival_at')->nullable()->comment('预计到达日期');
            $table->date('arrived_at')->nullable()->comment('实际到达日期');
            $table->string('carrier', 100)->nullable()->comment('承运商');
            $table->string('tracking_no', 100)->nullable()->comment('运单号');
            $table->string('status', 20)->default('shipped')->comment('shipped/in_transit/arrived/closed');
            $table->string('consignee', 50)->nullable()->comment('收货人');
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index(['status', 'carrier']);
            $table->index('shipped_at');
        });

        Schema::create('purchase_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('purchase_shipments')->cascadeOnDelete();
            $table->string('material', 200)->comment('物资名称');
            $table->string('spec', 200)->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('unit', 20)->default('件');
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_shipment_items');
        Schema::dropIfExists('purchase_shipments');
    }
};
