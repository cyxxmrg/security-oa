<?php
/**
 * V0.5.1 - 字段级脱敏中间件
 *
 * 在 L3 CheckPermission 之后挂, 自动对 response()->json 的 data 做敏感字段 mask
 *  - 仅作用于成功响应 (code === 0)
 *  - 仅作用于带 'data' 字段的响应
 *  - 不动 file/upload/raw response
 *
 * 用法: 在路由组后挂 ->middleware('field_mask')
 */

namespace App\Http\Middleware;

use App\Support\FieldMask;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyFieldMask
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 只处理 JSON 响应
        if (!str_contains($response->headers->get('Content-Type', ''), 'json')) {
            return $response;
        }

        $content = $response->getContent();
        if (!$content) {
            return $response;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['code'])) {
            return $response;
        }
        // 仅对 code=0 成功响应做脱敏
        if ($decoded['code'] !== 0) {
            return $response;
        }

        $user = $request->user();
        $endpoint = '/' . ltrim($request->path(), '/');

        // 在 data 上递归脱敏 (不只顶层)
        if (isset($decoded['data'])) {
            $decoded['data'] = self::recursiveMask($decoded['data'], $user, $endpoint);
        }

        $response->setContent(json_encode($decoded, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    /**
     * 递归遍历 data, 命中 FieldMask 规则就脱敏
     * 注意: 浅递归, 只处理 1-2 层 (避免性能)
     */
    private static function recursiveMask($data, $user, string $endpoint)
    {
        // 顶层命中: 单条 / 列表 / 分页
        if (is_array($data)) {
            $data = FieldMask::apply($data, $user, $endpoint);
            // 分页结构: data.data 列表
            if (isset($data['data']) && is_array($data['data']) && array_is_list($data['data'])) {
                $endpointList = preg_replace('#/[^/]+$#', '', $endpoint) ?: $endpoint;
                $data['data'] = FieldMask::apply($data['data'], $user, $endpointList);
            }
        }
        return $data;
    }
}
