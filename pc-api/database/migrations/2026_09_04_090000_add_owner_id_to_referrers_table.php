<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('referrers') || Schema::hasColumn('referrers', 'owner_id')) {
            return;
        }

        Schema::table('referrers', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('customer_id');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('referrers') && Schema::hasColumn('referrers', 'owner_id')) {
            Schema::table('referrers', function (Blueprint $table) {
                $table->dropIndex(['owner_id']);
                $table->dropColumn('owner_id');
            });
        }
    }
};
