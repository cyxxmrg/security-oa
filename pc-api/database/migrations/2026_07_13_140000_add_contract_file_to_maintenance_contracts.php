<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->text('contract_file')->nullable()->after('notes')->comment('合同扫描件(base64)');
            $table->string('contract_file_name', 255)->nullable()->after('contract_file')->comment('合同文件名');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->dropColumn(['contract_file', 'contract_file_name']);
        });
    }
};
