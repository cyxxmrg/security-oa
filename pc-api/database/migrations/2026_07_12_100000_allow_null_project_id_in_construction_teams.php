<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_teams', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('construction_teams', function (Blueprint $table) {
            // 先把 null 值设为 0 再改回 not null
            \Illuminate\Support\Facades\DB::table('construction_teams')
                ->whereNull('project_id')
                ->update(['project_id' => 0]);
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });
    }
};
