<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;
    protected $table = 'system_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['key', 'value', 'description', 'updated_at', 'updated_by'];
    protected $casts = [
        'updated_at' => 'datetime',
    ];

    /** 静态便捷：读取一个 key（已自动 json_decode） */
    public static function get(string $key, $default = null)
    {
        $row = static::find($key);
        if (!$row) return $default;
        $v = $row->value;
        // 兜底：某些驱动把 JSONB 当字符串返回
        if (is_string($v) && strlen($v) > 0) {
            $decoded = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        }
        return $v ?? $default;
    }
}
