<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_resignations') || !Schema::hasColumn('employee_resignations', 'resign_type')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE employee_resignations DROP CONSTRAINT IF EXISTS employee_resignations_resign_type_check');
            DB::statement(
                "ALTER TABLE employee_resignations ADD CONSTRAINT employee_resignations_resign_type_check CHECK (resign_type::text = ANY (ARRAY['voluntary','involuntary','contract_end','retirement','mutual','dismissed','probation_dismissed','other']))"
            );
        } elseif ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE employee_resignations MODIFY resign_type ENUM('voluntary','involuntary','contract_end','retirement','mutual','dismissed','probation_dismissed','other') NOT NULL DEFAULT 'voluntary'"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_resignations') || !Schema::hasColumn('employee_resignations', 'resign_type')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE employee_resignations DROP CONSTRAINT IF EXISTS employee_resignations_resign_type_check');
            DB::statement(
                "ALTER TABLE employee_resignations ADD CONSTRAINT employee_resignations_resign_type_check CHECK (resign_type::text = ANY (ARRAY['voluntary','involuntary','contract_end','retirement','other']))"
            );
        } elseif ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE employee_resignations MODIFY resign_type ENUM('voluntary','involuntary','contract_end','retirement','other') NOT NULL DEFAULT 'voluntary'"
            );
        }
    }
};
