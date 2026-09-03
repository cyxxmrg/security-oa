<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_follow_up_attachments')) {
            return;
        }

        $publicDisk = Storage::disk('public');
        $privateDisk = Storage::disk('attachments');

        DB::table('sales_follow_up_attachments')
            ->select('id', 'path')
            ->orderBy('id')
            ->get()
            ->each(function ($attachment) use ($publicDisk, $privateDisk) {
                $path = trim((string) $attachment->path, '/');
                if ($path === '' || $privateDisk->exists($path) || !$publicDisk->exists($path)) {
                    return;
                }

                if ($privateDisk->put($path, $publicDisk->get($path))) {
                    $publicDisk->delete($path);
                }
            });
    }

    public function down(): void
    {
    }
};
