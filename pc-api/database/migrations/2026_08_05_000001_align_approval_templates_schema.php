<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('approval_templates')) {
            return;
        }

        Schema::table('approval_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_templates', 'type')) {
                $table->string('type', 50)->nullable();
            }
            if (!Schema::hasColumn('approval_templates', 'steps')) {
                $table->jsonb('steps')->default('[]');
            }
            if (!Schema::hasColumn('approval_templates', 'enabled')) {
                $table->boolean('enabled')->default(true);
            }
            if (!Schema::hasColumn('approval_templates', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0);
            }
        });

        if (Schema::hasColumn('approval_templates', 'nodes')) {
            DB::statement("UPDATE approval_templates SET steps = nodes WHERE nodes IS NOT NULL");
        }
        if (Schema::hasColumn('approval_templates', 'status')) {
            DB::statement("UPDATE approval_templates SET enabled = (status = '启用')");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('approval_templates')) {
            return;
        }

        Schema::table('approval_templates', function (Blueprint $table) {
            foreach (['type', 'steps', 'enabled', 'sort_order'] as $column) {
                if (Schema::hasColumn('approval_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
