<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_contracts', function (Blueprint $t) {
            $t->string('type', 20)->default('sales')->after('customer_id'); // sales / purchase
        });
    }
    public function down(): void
    {
        Schema::table('project_contracts', function (Blueprint $t) {
            $t->dropColumn('type');
        });
    }
};