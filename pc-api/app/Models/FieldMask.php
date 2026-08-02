<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * V1.2.10 — 字段脱敏规则 Model (替代 DB::table('field_masks') 直连)
 *
 * 字段级权限控制: 按 endpoint + field 配置哪些角色可见。
 * unique 约束: (endpoint, field)
 */
class FieldMask extends Model
{
    protected $table = 'field_masks';

    protected $fillable = [
        'endpoint', 'field', 'allowed_roles', 'description', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * 把 allowed_roles 字符串 (逗号分隔) 转数组
     */
    public function getAllowedRolesArrayAttribute(): array
    {
        return array_filter(array_map('trim', explode(',', $this->allowed_roles ?? '')));
    }
}
