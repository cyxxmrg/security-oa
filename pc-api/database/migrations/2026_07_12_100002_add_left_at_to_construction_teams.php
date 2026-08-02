<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_teams', function (Blueprint $table) {
            $table->date('left_at')->nullable()->comment('解散/离场日期');
        });
    }

    public function down(): void
    {
        Schema::table('construction_teams', function (Blueprint $table) {
            $table->dropColumn('left_at');
        });
    }
};
