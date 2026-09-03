<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inspection_tasks')) {
            return;
        }

        DB::transaction(function () {
            $duplicates = DB::table('inspection_tasks')
                ->select('plan_id', 'scheduled_date', DB::raw('MAX(id) AS keep_id'))
                ->groupBy('plan_id', 'scheduled_date')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $group) {
                $duplicateIds = DB::table('inspection_tasks')
                    ->where('plan_id', $group->plan_id)
                    ->where('scheduled_date', $group->scheduled_date)
                    ->where('id', '<>', $group->keep_id)
                    ->pluck('id');

                if ($duplicateIds->isEmpty()) {
                    continue;
                }

                if (Schema::hasTable('inspection_records')) {
                    DB::table('inspection_records')
                        ->whereIn('task_id', $duplicateIds)
                        ->update(['task_id' => $group->keep_id]);
                }
                if (Schema::hasTable('inspection_issues')) {
                    DB::table('inspection_issues')
                        ->whereIn('task_id', $duplicateIds)
                        ->update(['task_id' => $group->keep_id]);
                }

                DB::table('inspection_tasks')->whereIn('id', $duplicateIds)->delete();
            }

            if (Schema::hasTable('inspection_plans')) {
                DB::table('inspection_plans')->update([
                    'total_generated' => 0,
                    'updated_at' => now(),
                ]);

                $taskCounts = DB::table('inspection_tasks')
                    ->select('plan_id', DB::raw('COUNT(*) AS task_count'))
                    ->groupBy('plan_id')
                    ->get();

                foreach ($taskCounts as $taskCount) {
                    DB::table('inspection_plans')
                        ->where('id', $taskCount->plan_id)
                        ->update([
                            'total_generated' => $taskCount->task_count,
                            'updated_at' => now(),
                        ]);
                }
            }
        });

        $indexName = 'inspection_tasks_plan_date_unique';
        if (DB::getDriverName() === 'mysql') {
            $exists = DB::table('information_schema.statistics')
                ->where('table_schema', DB::raw('DATABASE()'))
                ->where('table_name', 'inspection_tasks')
                ->where('index_name', $indexName)
                ->exists();

            if (!$exists) {
                Schema::table('inspection_tasks', function (Blueprint $table) use ($indexName) {
                    $table->unique(['plan_id', 'scheduled_date'], $indexName);
                });
            }
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS ' . $indexName
            . ' ON inspection_tasks (plan_id, scheduled_date)'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('inspection_tasks')) {
            return;
        }

        $indexName = 'inspection_tasks_plan_date_unique';
        if (DB::getDriverName() === 'mysql') {
            Schema::table('inspection_tasks', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
            return;
        }

        DB::statement('DROP INDEX IF EXISTS ' . $indexName);
    }
};
