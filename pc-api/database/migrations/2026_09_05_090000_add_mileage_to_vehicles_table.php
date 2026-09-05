<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicles') || Schema::hasColumn('vehicles', 'mileage')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('mileage')->nullable()->default(0)->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'mileage')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('mileage');
            });
        }
    }
};
