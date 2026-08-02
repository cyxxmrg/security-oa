<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $t) {
            $t->string('content_type', 20)->default('text')->after('content');
            $t->string('file_path', 500)->nullable()->after('content_type');
            $t->string('file_name', 255)->nullable()->after('file_path');
            $t->bigInteger('file_size')->nullable()->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $t) {
            $t->dropColumn(['content_type', 'file_path', 'file_name', 'file_size']);
        });
    }
};
