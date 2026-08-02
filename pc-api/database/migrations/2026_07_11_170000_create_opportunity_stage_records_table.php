<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 幂等守卫 (V1.2.x)
        if (!Schema::hasTable('opportunity_stage_records')) {
            Schema::create('opportunity_stage_records', function (Blueprint $t) {
                $t->id();
                $t->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
                $t->string('stage', 30);                          // 7 段枚举: inquiry/qualification/proposal/negotiating/quoted/won/lost
                $t->json('data')->nullable();                     // 阶段自定义录入数据 (按 stage 类型 schema)
                $t->text('note')->nullable();                     // 备注
                $t->timestamp('entered_at')->useCurrent();        // 进入阶段时间
                $t->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();

                $t->index(['opportunity_id', 'stage']);
                $t->index('entered_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_stage_records');
    }
};