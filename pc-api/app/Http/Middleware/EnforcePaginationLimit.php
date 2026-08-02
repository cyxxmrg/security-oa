<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * V1.2.7 P2-3 性能优化中间件 — 分页上限
 *
 * 自动修正 per_page / page_size 参数, 防止客户端恶意传 999999 一次性把数据库拖死
 *
 * 规则:
 *   - per_page 默认 15, 强制范围 [1, MAX_PER_PAGE]
 *   - page_size 默认 20, 强制范围 [1, MAX_PER_PAGE]
 *   - max 由 env OA_MAX_PER_PAGE 控制, 默认 200
 *   - 同时支持 query 和 post body
 *
 * 改造点 (vs 之前的 max(1, min($perPage, 200)) 散落各处):
 *   - 统一一处, 减少代码重复
 *   - 避免忘记 max 的 controller 被刷爆
 *   - 自动注入到 request, controller 不需要再处理
 */
class EnforcePaginationLimit
{
    /**
     * 默认每页大小
     */
    public const DEFAULT_PER_PAGE = 15;

    /**
     * 最大每页 (env 可调, 默认 200)
     * 超过这个数会触发 SQL 慢 + 内存爆 + 接口超时
     */
    private int $maxPerPage;

    public function __construct()
    {
        $this->maxPerPage = (int) config('oa.max_per_page', 200); // V1.2.10 走 config
        // 兜底: 即使 env 配错也不能过大
        if ($this->maxPerPage < 1 || $this->maxPerPage > 1000) {
            $this->maxPerPage = 200;
        }
    }

    public function handle(Request $request, Closure $next): Response
    {
        // V1.2.7 P2-3 fix: 总是覆盖 per_page 和 page_size, 不管是否传入
        // 原因: per_page 是 controller 必读的 (默认 15), 即使前端没传也要保证存在
        // 之前的 has() 判断会让 controller 还得 fallback 处理 null
        $request->merge([
            'per_page'  => $this->sanitize($request->input('per_page')),
            'page_size' => $this->sanitize($request->input('page_size')),
        ]);

        return $next($request);
    }

    /**
     * 清洗每页数:
     *   - null / 空 / 非数字 → 默认 15
     *   - 小于 1 → 1
     *   - 大于 max → max
     *   - 其他 → 原值
     */
    private function sanitize(mixed $value): int
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_PER_PAGE;
        }
        if (! is_numeric($value)) {
            return self::DEFAULT_PER_PAGE;
        }
        $n = (int) $value;
        if ($n < 1) {
            return 1;
        }
        if ($n > $this->maxPerPage) {
            return $this->maxPerPage;
        }
        return $n;
    }

    /**
     * 给定 controller 用的最大每页 (公开方法, 业务代码可同步)
     */
    public static function maxPerPage(): int
    {
        return (int) config('oa.max_per_page', 200); // V1.2.10 走 config
    }
}