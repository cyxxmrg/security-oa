<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $publicDisk = Storage::disk('public');
        $privateDisk = Storage::disk('attachments');

        foreach ([
            'purchase_contract_files',
            'purchase_payment_vouchers',
        ] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->select('id', 'file_path')->orderBy('id')->get()->each(function ($file) use ($table, $publicDisk, $privateDisk) {
                $path = trim((string) $file->file_path, '/');
                if ($path === '' || !str_starts_with($path, 'purchase/')) {
                    return;
                }
                if ($privateDisk->exists($path) || !$publicDisk->exists($path)) {
                    return;
                }

                $contents = $publicDisk->get($path);
                if ($privateDisk->put($path, $contents)) {
                    $publicDisk->delete($path);
                    DB::table($table)->where('id', $file->id)->update(['file_path' => $path]);
                }
            });
        }
    }

    public function down(): void
    {
    }
};
