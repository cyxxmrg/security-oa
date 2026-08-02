<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('project_pool')) {
            return;
        }

        Schema::create('project_pool', function (Blueprint $table) {
            $table->id();
            $table->string('pool_no', 30)->unique()->comment('项目池编号');
            $table->unsignedBigInteger('opportunity_id')->nullable()->comment('来源商机');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->onDelete('set null');
            $table->string('name', 200)->comment('项目名称');
            $table->unsignedBigInteger('customer_id')->nullable()->comment('客户');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->decimal('contract_amount', 12, 2)->default(0)->comment('合同金额');
            $table->date('signed_at')->nullable()->comment('签约日期');
            $table->string('status', 20)->default('pending')->comment('pending/active/archived');
            $table->unsignedBigInteger('related_project_id')->nullable()->comment('关联施工项目');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_id');
            $table->index('opportunity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_pool');
    }
};
