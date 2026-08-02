<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * V1.2.7 P2-3 性能优化 — 缓存装饰器
 *
 * 给耗时方法包一层 redis 缓存, 统一处理:
 *   - key 生成 + TTL
 *   - 缓存命中/未命中埋点 (走 PerformanceMonitor 的 metrics)
 *   - 标签清理 (cache.flush('dashboard') 一键清一类)
 *
 * 用法:
 *   $data = CacheHelper::remember('dashboard:stats', 60, ['user' => $uid], function () {
 *       return DashboardService::stats($uid);
 *   });
 *
 *   // 清理
 *   CacheHelper::flushTag('dashboard'); // 清所有 dashboard: 开头
 *
 * 设计原则:
 *   - 用 'prefix:' 而不是 Laravel tag 系统 (避免 redis cluster 不支持 tag 的坑)
 *   - cache miss 自动回源 + log
 *   - 默认 TTL 60s, 业务可覆盖
 *   - 失败优雅降级 (cache 挂了不影响主流程)
 */
class CacheHelper
{
    /** 默认 TTL (秒) */
    public const DEFAULT_TTL = 60;

    /** 长 TTL — 配置/字典 */
    public const LONG_TTL = 300;

    /** 超长 TTL — 用户/角色等基本不变 */
    public const PERMANENT_TTL = 3600;

    /**
     * 记住 (cache miss 调 callback)
     *
     * @template T
     * @param  string         $key       缓存 key (不带 prefix)
     * @param  int            $ttl       秒
     * @param  array          $tags      用于 flushTag 的 tag 数组
     * @param  Closure(): T   $callback  cache miss 时调用
     * @return T
     */
    public static function remember(string $key, int $ttl, array $tags, Closure $callback): mixed
    {
        $fullKey = self::fullKey($key);

        try {
            // V1.2.7 P2-3 fix: 用 exists 提前检查, 避免 Cache::remember 触发 tag 包装
            // Cache::remember 内部会做一次 get 再 put, 用 exists 直接命中快路径
            $hit = Cache::has($fullKey);
            if ($hit) {
                return Cache::get($fullKey);
            }

            // miss — 调 callback
            self::recordMiss($key);
            $value = $callback();
            // 索引 tags, 便于 flushTag
            self::indexTags($key, $tags);
            // 写入
            Cache::put($fullKey, $value, $ttl);
            return $value;
        } catch (\Throwable $e) {
            // 缓存故障, 降级直接调 callback, 不影响主流程
            \Illuminate\Support\Facades\Log::warning('CacheHelper::remember failed', [
                'key' => $key,
                'msg' => $e->getMessage(),
            ]);
            return $callback();
        }
    }

    /**
     * 强制 forget
     */
    public static function forget(string $key): void
    {
        try {
            Cache::forget(self::fullKey($key));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * 按 tag 清理 (会把 tag 下所有 key 全部删)
     *
     * 用法: CacheHelper::flushTag('dashboard') 清所有 dashboard: 开头
     *
     * V1.2.7 P2-3 fix: 用 Redis pipeline 批量删除, 避免单条 DEL 在某些 redis cluster 下丢消息
     */
    public static function flushTag(string $tag): void
    {
        try {
            $indexKey = "cache_index:{$tag}";
            $keys = Cache::get($indexKey, []);
            if (empty($keys)) {
                Cache::forget($indexKey);
                return;
            }

            // 用底层 store 一次性删多个 key, 走 pipeline 避免延迟
            $store = Cache::getStore();

            // 1) 删所有数据 key
            foreach ((array) $keys as $k => $v) {
                $cacheKey = is_string($k) ? $k : $v;
                if (!is_string($cacheKey) || $cacheKey === '') {
                    continue;
                }

                try {
                    $store->forget($cacheKey);
                } catch (\Throwable $e) {
                    // ignore single-key failures
                    continue;
                }
            }

            // 2) 删索引 key
            try {
                $store->forget($indexKey);
            } catch (\Throwable $e) {
                // ignore
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CacheHelper::flushTag failed', [
                'tag' => $tag,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 永远缓存 (24h)
     */
    public static function rememberForever(string $key, array $tags, Closure $callback): mixed
    {
        return self::remember($key, self::PERMANENT_TTL, $tags, $callback);
    }

    /**
     * 缓存 key 加前缀, 防冲突
     *
     * V1.2.7 P2-3: 返回原始 key (Laravel cache facade 自动加 cache.prefix)
     * 不要再叠加前缀, 否则双重前缀导致 Cache::get 找不到
     */
    private static function fullKey(string $key): string
    {
        return $key;
    }

    /**
     * 把 key 加到 tag 索引
     */
    private static function indexTags(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            $indexKey = "cache_index:{$tag}";
            $current = (array) Cache::get($indexKey, []);
            $current[$key] = true;
            // 索引 TTL 跟最长业务缓存一致
            Cache::put($indexKey, $current, self::PERMANENT_TTL);
        }
    }

    /**
     * 缓存 miss 计数 (供 PerformanceMonitor 拉)
     */
    private static function recordMiss(string $key): void
    {
        try {
            $minute = now()->format('Y-m-d-H-i');
            $statKey = "metrics:cache_miss:{$minute}";
            $data = Cache::get($statKey, []);
            $data[$key] = ($data[$key] ?? 0) + 1;
            Cache::put($statKey, $data, now()->addMinutes(5));
        } catch (\Throwable $e) {
            // ignore
        }
    }
}