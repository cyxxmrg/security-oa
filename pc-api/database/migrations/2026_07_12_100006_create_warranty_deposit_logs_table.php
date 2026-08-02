<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_deposit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deposit_id')->comment('质保金ID');
            $table->string('operation_type', 30)->comment('操作类型: partial_release/full_release/forfeit');
            $table->decimal('amount', 12, 2)->comment('操作金额');
            $table->string('before_status', 30)->nullable()->comment('操作前状态');
            $table->string('after_status', 30)->nullable()->comment('操作后状态');
            $table->unsignedBigInteger('bank_account_id')->nullable()->comment('到账银行ID');
            $table->string('beneficiary', 100)->nullable()->comment('收款人');
            $table->string('reason', 500)->nullable()->comment('原因');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_deposit_logs');
    }
};
