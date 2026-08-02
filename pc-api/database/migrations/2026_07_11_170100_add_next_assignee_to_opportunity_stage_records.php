<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('opportunity_stage_records', function (Blueprint $t) {
            // 下一个流转人：阶段录入完后，下一步交给谁（@内部员工）
            $t->unsignedBigInteger('next_assignee_id')->nullable()->after('note');
            $t->string('next_assignee_name')->nullable()->after('next_assignee_id');
            $t->timestamp('next_due_at')->nullable()->after('next_assignee_name');
            $t->index('next_assignee_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_stage_records', function (Blueprint $t) {
            $t->dropIndex(['next_assignee_id']);
            $t->dropColumn(['next_assignee_id', 'next_assignee_name', 'next_due_at']);
        });
    }
};