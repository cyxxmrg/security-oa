<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('opportunities')) {
            return;
        }

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('opp_no', 30)->unique()->comment('商机编号 OPP-2026-001');
            $table->string('name', 200)->comment('商机名称');
            $table->unsignedBigInteger('customer_id')->nullable()->comment('客户ID');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->unsignedBigInteger('lead_id')->nullable()->comment('来源线索ID(历史追溯, leads 表已废弃, 无外键)');
            $table->string('type', 50)->default('comprehensive')->comment('项目类型');
            $table->decimal('estimated_amount', 12, 2)->default(0)->comment('预计签约金额');
            $table->date('expected_sign_date')->nullable()->comment('预计签约日期');
            $table->string('stage', 20)->default('requirement')->comment('需求确认/方案制定/报价谈判/合同拟定/won/lost');
            $table->unsignedSmallInteger('probability')->default(50)->comment('签约概率 %');
            $table->unsignedBigInteger('sales_id')->nullable()->comment('销售负责人');
            $table->unsignedBigInteger('presale_id')->nullable()->comment('售前负责人');
            $table->string('competitor', 200)->nullable()->comment('竞争对手');
            $table->string('lost_reason', 30)->nullable()->comment('战败原因: price_high/competitor/budget/tech/relation/other');
            $table->unsignedBigInteger('project_id')->nullable()->comment('转化项目ID');
            $table->unsignedBigInteger('pool_id')->nullable()->comment('项目池ID');
            $table->timestamp('last_contact_at')->nullable()->comment('最近联系');
            $table->text('next_action')->nullable()->comment('下一步行动');
            $table->date('next_action_at')->nullable()->comment('下一步时间');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('stage');
            $table->index('customer_id');
            $table->index('sales_id');
            $table->index('presale_id');
            $table->index('expected_sign_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
