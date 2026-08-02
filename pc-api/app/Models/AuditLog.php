<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V1.2.10 — 审计日志 Model (替代 DB::table('audit_logs') 直连)
 *
 * AuditLogger 中间件自动记录所有写操作 (POST/PUT/PATCH/DELETE)。
 * 写入仍可用 DB::table (中间件场景, 性能更优), 此 Model 供查询用。
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id', 'method', 'path', 'ip', 'user_agent',
        'payload', 'response_code',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_code' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
