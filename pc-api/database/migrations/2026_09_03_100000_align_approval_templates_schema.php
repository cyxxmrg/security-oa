<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('approval_templates')) {
            return;
        }

        Schema::table('approval_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_templates', 'type')) {
                $table->string('type', 50)->nullable();
            }
            if (!Schema::hasColumn('approval_templates', 'steps')) {
                $table->json('steps')->nullable();
            }
            if (!Schema::hasColumn('approval_templates', 'enabled')) {
                $table->boolean('enabled')->default(true);
            }
            if (!Schema::hasColumn('approval_templates', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
        });

        $hasLegacyNodes = Schema::hasColumn('approval_templates', 'nodes');
        $hasLegacyStatus = Schema::hasColumn('approval_templates', 'status');

        DB::table('approval_templates')->orderBy('id')->chunkById(100, function ($templates) use ($hasLegacyNodes, $hasLegacyStatus): void {
            foreach ($templates as $template) {
                $updates = [];
                if ($hasLegacyNodes && empty($template->steps) && !empty($template->nodes)) {
                    $updates['steps'] = is_string($template->nodes)
                        ? $template->nodes
                        : json_encode($template->nodes, JSON_UNESCAPED_UNICODE);
                }
                if ($hasLegacyStatus) {
                    $updates['enabled'] = ($template->status ?? '启用') !== '停用';
                }
                if ($updates !== []) {
                    DB::table('approval_templates')->where('id', $template->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
    }
};
