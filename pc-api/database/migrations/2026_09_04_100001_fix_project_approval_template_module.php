<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('approval_templates')) {
            return;
        }

        DB::table('approval_templates')
            ->where('name', '项目立项审批')
            ->where('module', '采购')
            ->update(['module' => '项目']);
    }

    public function down(): void
    {
    }
};
