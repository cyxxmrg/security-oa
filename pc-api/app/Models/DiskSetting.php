<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 网盘设置（存储路径、是否已初始化等）
 *
 * key => value (json) 键值对存储
 * 预定义 key:
 *   - storage_path: string, 存储根目录, 如 /data/disk
 *   - initialized: boolean, 是否已完成初始化
 *   - auto_detect: boolean, 是否自动选择最大盘
 */
class DiskSetting extends Model
{
    protected $table = 'disk_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function get(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();
        if (!$row) return $default;
        return $row->value ?? $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }
}
