<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'users' => ['id_card'],
        'employee_profiles' => ['bank_account'],
        'employee_onboardings' => ['id_card_no'],
        'construction_team_members' => ['id_card', 'id_number'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->text($column)->nullable()->change();
                    }
                }
            });

            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column) {
                        $value = $row->{$column} ?? null;
                        if ($value !== null && $value !== '' && !$this->isEncrypted((string) $value)) {
                            $updates[$column] = Crypt::encryptString((string) $value);
                        }
                    }
                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Personal data is not decrypted automatically during rollback.
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
