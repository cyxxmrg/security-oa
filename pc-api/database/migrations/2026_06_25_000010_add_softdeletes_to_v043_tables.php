<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.3 补丁：3 个表启用 SoftDeletes 但缺 deleted_at 字段
 *  - construction_teams
 *  - project_commencement_orders
 *  - external_construction_works
 *  - external_construction_bids
 */
return new class extends Migration {
    public function up(): void
    {
        $tables = [
            'construction_teams',
            'project_commencement_orders',
            'external_construction_works',
            'external_construction_bids',
        ];
        foreach ($tables as $t) {
            if (!Schema::hasColumn($t, 'deleted_at')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'construction_teams',
            'project_commencement_orders',
            'external_construction_works',
            'external_construction_bids',
        ];
        foreach ($tables as $t) {
            if (Schema::hasColumn($t, 'deleted_at')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
