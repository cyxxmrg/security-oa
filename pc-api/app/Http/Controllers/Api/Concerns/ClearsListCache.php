<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * V1.3.2 列表缓存失效 Trait
 *
 * 用于: 控制器给 index 加了 Redis JSON 缓存后,
 * 在 store/update/destroy 等写操作后清除对应前缀的列表缓存,
 * 避免"新增/修改后列表 30s 不刷新"。
 *
 * 用法:
 *   use ClearsListCache;
 *   // 写操作后:
 *   $this->clearListCache('customers:index');
 *
 * 注意 (phpredis 踩坑):
 *   - 连接配置了 OPT_PREFIX (如 'oa_database_'), keys() 返回带前缀的真实 key
 *   - del() 会自动加前缀, 因此必须传入"剥离前缀后"的 key
 *   - 参考: 本地开发为 predis, 服务器为 phpredis, 两套行为不同
 */
trait ClearsListCache
{
    /**
     * 清除指定前缀的所有列表缓存
     *
     * @param string $prefix 例如 'customers:index' / 'projects:index'
     */
    protected function clearListCache(string $prefix): void
    {
        try {
            $connection = Cache::store('redis')->getStore()->connection();

            // phpredis: 需要剥离 OPT_PREFIX 后 del
            if (method_exists($connection, 'client')) {
                $client = $connection->client();
                $optPrefix = defined('\Redis::OPT_PREFIX') ? $client->getOption(\Redis::OPT_PREFIX) : '';
                $optPrefix = (string) $optPrefix;
                $keys = $connection->keys('*' . $prefix . ':*');
                foreach ($keys as $k) {
                    $bare = $optPrefix !== '' && str_starts_with($k, $optPrefix)
                        ? substr($k, strlen($optPrefix))
                        : $k;
                    $connection->del($bare);
                }
                return;
            }

            // predis (本地开发): scan 返回 [cursor, keys] 元组, del 接受数组
            $cursor = null;
            do {
                [$cursor, $keys] = $connection->scan($cursor, ['match' => $prefix . ':*', 'count' => 100]);
                if (!empty($keys)) {
                    $connection->del($keys);
                }
            } while ($cursor !== 0 && $cursor !== '0');
        } catch (\Throwable $e) {
            Log::warning("clearListCache({$prefix}) 失败", ['msg' => $e->getMessage()]);
        }
    }
}
