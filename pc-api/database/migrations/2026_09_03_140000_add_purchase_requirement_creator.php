<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_requirements') || Schema::hasColumn('purchase_requirements', 'created_by')) {
            return;
        }

        Schema::table('purchase_requirements', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('creator')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('创建人 (DataScope 用)');
            $table->index('created_by');
        });

        DB::table('purchase_requirements')
            ->whereNull('created_by')
            ->whereNotNull('creator')
            ->orderBy('id')
            ->chunkById(200, function ($requirements): void {
                foreach ($requirements as $requirement) {
                    $userId = DB::table('users')
                        ->where(function ($query) use ($requirement): void {
                            $query->where('name', $requirement->creator)
                                ->orWhere('username', $requirement->creator);
                        })
                        ->orderBy('id')
                        ->value('id');
                    if ($userId) {
                        DB::table('purchase_requirements')
                            ->where('id', $requirement->id)
                            ->update(['created_by' => $userId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('purchase_requirements', 'created_by')) {
            return;
        }

        Schema::table('purchase_requirements', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
