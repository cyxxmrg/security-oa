<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V1.2.10 — 系统日志 Model (替代 DB::table('system_logs') 直连)
 *
 * 用于审计日志/权限拒绝/操作日志的查询。写入仍可用 DB::table (性能更优)。
 */
class SystemLog extends Model
{
    protected $table = 'system_logs';

    protected $fillable = [
        'user_id', 'type', 'module', 'action', 'description',
        'ip', 'user_agent', 'request_data', 'response_code',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_code' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
