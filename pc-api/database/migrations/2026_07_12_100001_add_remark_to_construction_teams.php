<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_teams', function (Blueprint $table) {
            $table->text('remark')->nullable()->comment('备注/解散原因');
        });
    }

    public function down(): void
    {
        Schema::table('construction_teams', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
