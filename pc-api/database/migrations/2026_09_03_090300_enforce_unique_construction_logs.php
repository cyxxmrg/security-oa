<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('construction_logs')) {
            return;
        }

        if (!Schema::hasColumn('construction_logs', 'process_progress')) {
            Schema::table('construction_logs', function (Blueprint $table) {
                $table->json('process_progress')->nullable();
            });
        }

        if (Schema::hasTable('work_process_progress')) {
            $hasLastLogId = Schema::hasColumn('work_process_progress', 'last_log_id');
            $hasLastLogDate = Schema::hasColumn('work_process_progress', 'last_log_date');
            $hasUpdatedBy = Schema::hasColumn('work_process_progress', 'updated_by');

            Schema::table('work_process_progress', function (Blueprint $table) use ($hasLastLogId, $hasLastLogDate, $hasUpdatedBy) {
                if (!$hasLastLogId) {
                    $table->unsignedBigInteger('last_log_id')->nullable()->index();
                }
                if (!$hasLastLogDate) {
                    $table->date('last_log_date')->nullable();
                }
                if (!$hasUpdatedBy) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index();
                }
            });
        }

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE construction_logs DROP CONSTRAINT IF EXISTS construction_logs_status_check');
            DB::statement('ALTER TABLE construction_logs ALTER COLUMN status TYPE VARCHAR(20) USING status::text');
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE construction_logs MODIFY status VARCHAR(20) NOT NULL DEFAULT 'submitted'");
        }

        DB::transaction(function () {
            $duplicates = DB::table('construction_logs')
                ->select('project_id', 'user_id', 'work_date', DB::raw('MAX(id) AS keep_id'))
                ->groupBy('project_id', 'user_id', 'work_date')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $group) {
                $duplicateIds = DB::table('construction_logs')
                    ->where('project_id', $group->project_id)
                    ->where('user_id', $group->user_id)
                    ->where('work_date', $group->work_date)
                    ->where('id', '<>', $group->keep_id)
                    ->pluck('id');

                if ($duplicateIds->isEmpty()) {
                    continue;
                }

                if (Schema::hasTable('rectification_daily_required')) {
                    DB::table('rectification_daily_required')
                        ->whereIn('submitted_log_id', $duplicateIds)
                        ->update(['submitted_log_id' => $group->keep_id]);
                }
                if (Schema::hasTable('rectifications')) {
                    DB::table('rectifications')
                        ->whereIn('construction_log_id', $duplicateIds)
                        ->update(['construction_log_id' => $group->keep_id]);
                }
                if (Schema::hasTable('work_process_progress') && Schema::hasColumn('work_process_progress', 'last_log_id')) {
                    DB::table('work_process_progress')
                        ->whereIn('last_log_id', $duplicateIds)
                        ->update(['last_log_id' => $group->keep_id]);
                }

                DB::table('construction_logs')->whereIn('id', $duplicateIds)->delete();
            }

            if (DB::getDriverName() === 'mysql') {
                $indexExists = DB::table('information_schema.statistics')
                    ->where('table_schema', DB::raw('DATABASE()'))
                    ->where('table_name', 'construction_logs')
                    ->where('index_name', 'construction_logs_project_user_date_unique')
                    ->exists();

                if (!$indexExists) {
                    DB::statement(
                        'ALTER TABLE construction_logs ADD UNIQUE KEY construction_logs_project_user_date_unique '
                        . '(project_id, user_id, work_date)'
                    );
                }
            } else {
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS construction_logs_project_user_date_unique '
                    . 'ON construction_logs (project_id, user_id, work_date)'
                );
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE construction_logs DROP INDEX construction_logs_project_user_date_unique');
        } else {
            DB::statement('DROP INDEX IF EXISTS construction_logs_project_user_date_unique');
        }
    }
};
