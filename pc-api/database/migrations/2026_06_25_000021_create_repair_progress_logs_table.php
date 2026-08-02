<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('repair_progress_logs')) {
            return;
        }

        Schema::create('repair_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repair_order_id')->index();
            $table->unsignedBigInteger('method_id')->nullable()->index();

            $table->string('progress', 32)->comment('诊断/拆机/换件/调试/...');
            $table->string('status_before', 32)->nullable();
            $table->string('status_after', 32)->nullable();
            $table->text('description')->nullable();
            $table->decimal('cost_added', 10, 2)->default(0);
            $table->boolean('is_paid')->default(false)->comment('本步骤是否计费');

            $table->unsignedBigInteger('action_by')->nullable();
            $table->timestamp('action_at')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'action_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_progress_logs');
    }
};
