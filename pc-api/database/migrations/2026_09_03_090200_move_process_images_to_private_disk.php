<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('process_images')) {
            return;
        }

        $legacyDisk = Storage::disk('local');
        $privateDisk = Storage::disk('attachments');

        DB::table('process_images')
            ->select('id', 'file_path')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->get()
            ->each(function ($image) use ($legacyDisk, $privateDisk) {
                $path = trim((string) $image->file_path, '/');
                if ($path === '') {
                    return;
                }

                if ($privateDisk->exists($path)) {
                    if ($legacyDisk->exists($path)) {
                        $legacyDisk->delete($path);
                    }
                    return;
                }

                if (!$legacyDisk->exists($path)) {
                    return;
                }

                if ($privateDisk->put($path, $legacyDisk->get($path))) {
                    $legacyDisk->delete($path);
                }
            });
    }

    public function down(): void
    {
    }
};
