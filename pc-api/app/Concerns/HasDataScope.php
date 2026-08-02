<?php

namespace App\Concerns;

use App\Scopes\DataScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * V0.4.6 B 数据权限 trait
 *
 * 用法 (Model 内):
 *   use HasFactory, HasDataScope;
 *   // 默认: booted() 自动注册 DataScope
 *
 * 旁路 (bypass):
 *   Model::withoutGlobalScope(DataScope::class)->...
 *   Project::withoutScope(DataScope::class)->find($id);
 */
trait HasDataScope
{
    public static function bootHasDataScope()
    {
        static::addGlobalScope(new DataScope());
    }

    /**
     * 取不带 data scope 的 builder (供 Service/Controller 显式调用)
     */
    public static function allData(): Builder
    {
        return static::query()->withoutGlobalScope(DataScope::class);
    }
}
