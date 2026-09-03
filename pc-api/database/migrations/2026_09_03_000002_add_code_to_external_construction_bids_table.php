<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('external_construction_bids')) {
            return;
        }

        if (!Schema::hasColumn('external_construction_bids', 'code')) {
            Schema::table('external_construction_bids', function (Blueprint $table) {
                $table->string('code', 30)->nullable();
            });
        }

        DB::table('external_construction_bids')
            ->whereNull('code')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $bid): void {
                DB::table('external_construction_bids')
                    ->where('id', $bid->id)
                    ->update(['code' => 'ECWB-LEGACY-' . str_pad((string) $bid->id, 6, '0', STR_PAD_LEFT)]);
            });

        Schema::table('external_construction_bids', function (Blueprint $table) {
            $table->unique('code', 'external_construction_bids_code_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('external_construction_bids')
            && Schema::hasColumn('external_construction_bids', 'code')) {
            Schema::table('external_construction_bids', function (Blueprint $table) {
                $table->dropUnique('external_construction_bids_code_unique');
                $table->dropColumn('code');
            });
        }
    }
};
