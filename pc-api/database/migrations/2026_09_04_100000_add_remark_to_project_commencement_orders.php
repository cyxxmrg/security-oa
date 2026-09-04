<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_commencement_orders')
            && !Schema::hasColumn('project_commencement_orders', 'remark')) {
            Schema::table('project_commencement_orders', function (Blueprint $table) {
                $table->text('remark')->nullable()->comment('备注');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_commencement_orders')
            && Schema::hasColumn('project_commencement_orders', 'remark')) {
            Schema::table('project_commencement_orders', function (Blueprint $table) {
                $table->dropColumn('remark');
            });
        }
    }
};
