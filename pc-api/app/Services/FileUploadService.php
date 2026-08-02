<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 统一文件上传服务
 *
 * 解决 audit-2026-06-28 P1: 各 Controller 上传逻辑散落、MIME/extension 校验不统一
 *
 * 用法:
 *   $result = $this->uploader->store($request, 'file', [
 *       'disk'         => 'public',
 *       'subdir'       => 'tenders/' . $tender->id,
 *       'allowed_ext'  => ['pdf','doc','docx'],
 *       'allowed_mime' => ['application/pdf','application/msword',...],
 *       'max_size'     => 20480,
 *   ]);
 *   // 返回 ['path','name','mime','size','ext','original_name','hash']
 *
 * 校验规则 (双重):
 *   1) Laravel validate 的 mimes 规则 (基于 finfo/finfo_file 真实 MIME)
 *   2) 业务 Service 内 allowed_ext (extension)
 *   3) 业务 Service 内 allowed_mime (getMimeType() 真实 MIME)
 */
class FileUploadService
{
    /**
     * 默认允许的 extension 白名单 (各 Controller 可覆盖)
     */
    public const DEFAULT_ALLOWED_EXT = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'md', 'csv',
        'zip', 'rar', '7z',
        'dwg', 'dxf',
    ];

    /**
     * 默认允许的 MIME 白名单 (基于真实 MIME, 非客户端声明)
     */
    public const DEFAULT_ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/markdown', 'text/csv',
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        'application/acad', 'application/dwg', 'image/vnd.dwg', 'application/dxf',
        'video/mp4', 'video/quicktime',
    ];

    /**
     * 存储上传文件, 返回结构化结果
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $field  form-data 字段名
     * @param  array{
     *     disk?: string,
     *     subdir?: string,
     *     allowed_ext?: array<int,string>,
     *     allowed_mime?: array<int,string>,
     *     max_size?: int,
     *     uuid_name?: bool
     * }  $options
     * @return array{path: string, name: string, original_name: string, mime: string, size: int, ext: string, hash: string}
     *
     * @throws ValidationException  文件类型/大小/MIME 不符合
     */
    public function store($request, string $field, array $options = []): array
    {
        $disk         = $options['disk']         ?? config('filesystems.default');
        $subdir       = $options['subdir']       ?? date('Y/m');
        $allowedExt   = $options['allowed_ext']  ?? self::DEFAULT_ALLOWED_EXT;
        $allowedMime  = $options['allowed_mime'] ?? self::DEFAULT_ALLOWED_MIME;
        $maxSize      = $options['max_size']     ?? 20480; // 20MB default
        $uuidName     = $options['uuid_name']    ?? true;

        $file = $request->file($field);
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([$field => '未检测到上传文件']);
        }

        // 大小
        if ($file->getSize() > $maxSize * 1024) {
            throw ValidationException::withMessages([$field => "文件大小不能超过 {$maxSize}KB (当前 " . round($file->getSize()/1024, 1) . "KB)"]);
        }

        // extension (客户端声明, 仅辅助)
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowedExt, true)) {
            throw ValidationException::withMessages([$field => "文件类型 .{$ext} 不被允许 (允许: " . implode(',', $allowedExt) . ")"]);
        }

        // 真实 MIME (服务端 finfo 检测)
        $realMime = $file->getMimeType();
        if (! in_array($realMime, $allowedMime, true)) {
            throw ValidationException::withMessages([$field => "文件 MIME 类型不被允许: {$realMime}"]);
        }

        // 计算 sha256 用于去重 / 完整性校验
        $hash = hash_file('sha256', $file->getRealPath());

        // 路径
        $baseName = $uuidName ? Str::uuid()->toString() : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $relPath  = trim($subdir, '/') . '/' . $baseName . '.' . $ext;

        Storage::disk($disk)->put($relPath, file_get_contents($file->getRealPath()));

        return [
            'path'          => $relPath,
            'name'          => $baseName . '.' . $ext,
            'original_name' => $file->getClientOriginalName(),
            'mime'          => $realMime,
            'size'          => $file->getSize(),
            'ext'           => $ext,
            'hash'          => $hash,
        ];
    }
}