<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_construction_works', function (Blueprint $table) {
            $table->string('bid_type', 20)->default('public')->comment('发包类型: public=公开发包, internal=内部发包');
        });
    }

    public function down(): void
    {
        Schema::table('external_construction_works', function (Blueprint $table) {
            $table->dropColumn('bid_type');
        });
    }
};
