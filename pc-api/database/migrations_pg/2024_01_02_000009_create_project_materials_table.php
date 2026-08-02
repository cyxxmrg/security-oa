<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->string('material_name', 200)->comment('材料名称');
            $table->string('specification', 200)->nullable()->comment('规格');
            $table->decimal('quantity', 10, 2)->comment('领用数量');
            $table->string('unit', 20)->comment('单位');
            $table->decimal('unit_cost', 12, 2)->default(0)->comment('单价');
            $table->decimal('total_cost', 12, 2)->comment('总成本');
            $table->unsignedBigInteger('used_by')->nullable()->comment('使用人');
            $table->foreign('used_by')->references('id')->on('users')->onDelete('set null');
            $table->date('use_date')->comment('使用日期');
            $table->unsignedBigInteger('inventory_item_id')->nullable()->comment('关联库存');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_materials');
    }
};
