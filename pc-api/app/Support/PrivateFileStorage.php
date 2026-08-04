<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PrivateFileStorage
{
    public static function download(string $path, string $name, array $headers = []): StreamedResponse
    {
        self::assertSafeRelativePath($path);
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, $name, $headers);
        }

        // Compatibility for files uploaded before private storage was enabled.
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, $name, $headers);
        }

        abort(404, '文件不存在');
    }

    public static function delete(string $path): void
    {
        self::assertSafeRelativePath($path);
        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path);
    }

    private static function assertSafeRelativePath(string $path): void
    {
        $segments = preg_split('~/+~', str_replace('\\', '/', $path));
        abort_if($path === '' || str_starts_with($path, '/') || in_array('..', $segments, true), 404);
    }
}
