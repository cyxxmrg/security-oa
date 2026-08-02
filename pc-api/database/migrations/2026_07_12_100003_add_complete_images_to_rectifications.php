<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rectifications', function (Blueprint $table) {
            $table->json('complete_images')->nullable()->comment('整改完成图片');
        });
    }

    public function down(): void
    {
        Schema::table('rectifications', function (Blueprint $table) {
            $table->dropColumn('complete_images');
        });
    }
};
