<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('expense_items')) {
            return;
        }

        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_claim_id')->comment('报销单ID');
            $table->foreign('expense_claim_id')->references('id')->on('expense_claims')->onDelete('cascade');
            $table->date('item_date')->comment('费用日期');
            $table->string('description', 200)->comment('说明');
            $table->decimal('amount', 10, 2)->comment('金额');
            $table->string('category', 50)->nullable()->comment('明细类别');
            $table->string('attachment', 255)->nullable()->comment('发票附件路径');
            $table->timestamps();

            $table->index('expense_claim_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
