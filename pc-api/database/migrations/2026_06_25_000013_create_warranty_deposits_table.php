<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.5 质保期管理 - 质保金留置
 *
 * - 一份质保金记录对应一个项目（一对一为主，也可多份）
 * - contract_amount × deposit_rate = deposit_amount（应用层计算）
 * - status 状态机: held 留置中 → partial_released 部分释放 / fully_released 全部释放 / forfeited 没收
 * - release_date 可空：未释放时为 null
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warranty_deposits')) {
            return;
        }

        Schema::create('warranty_deposits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');

            $table->decimal('contract_amount', 12, 2);
            $table->decimal('deposit_rate', 5, 2)->default(5.00);
            $table->decimal('deposit_amount', 12, 2);

            $table->date('hold_date');
            $table->date('release_date')->nullable();

            $table->string('status', 20)->default('held');
            // held 留置中 / partial_released 部分释放 / fully_released 全部释放 / forfeited 已没收

            $table->decimal('release_amount', 12, 2)->default(0);
            $table->decimal('forfeit_amount', 12, 2)->default(0);
            $table->text('reason')->nullable();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamp('approved_at')->nullable();

            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['release_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_deposits');
    }
};
