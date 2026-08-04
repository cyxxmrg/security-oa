<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\TenderPortalInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 供应商门户短期访问 token 管理。
 *
 * 设计目标:
 *  - 使用手机号 + 供应商编号签发。
 *  - token 绑定 supplier_id，服务端仅保存 SHA-256 摘要。
 *  - 30 分钟 TTL, 服务端仅保存 token 摘要, used_at 用于主动撤销
 *  - 不暴露 supplier_id 给未认证的查询 (supplierInfo / invitations)
 *
 * 关键方法:
 *  - issue(Supplier, phoneSuffix): 生成并持久化新 token。
 *  - verify(Request, supplierId, phoneSuffix): 校验 token 及其绑定的供应商。
 *  - resolveAccess(Request): 从 Bearer Token 解析供应商身份。
 */
class PortalInviteService
{
    /** Token 默认 TTL (分钟) */
    public const DEFAULT_TTL_MINUTES = 30;

    /**
     * 给一个供应商签发一次性 token
     *
     * @return array{token: string, expires_at: Carbon, ttl_minutes: int}
     */
    public function issue(Supplier $supplier, string $phoneSuffix, ?Request $request = null, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): array
    {
        $plain = Str::random(64);
        $hash  = $this->hashSuffix($phoneSuffix);

        $invite = TenderPortalInvite::create([
            'supplier_id'       => $supplier->id,
            'token'             => $this->tokenDigest($plain),
            'phone_suffix_hash' => $hash,
            'ip'                => $request?->ip(),
            'user_agent'        => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            'expires_at'        => now()->addMinutes($ttlMinutes),
        ]);

        return [
            'token'       => $plain,
            'expires_at'  => $invite->expires_at,
            'ttl_minutes' => $ttlMinutes,
        ];
    }

    /**
     * 校验短期访问 token
     *
     * 校验失败返回 null; 成功返回 Supplier 实例, 并已标记 used_at。
     *
     * @param  string  $supplierId  客户端声明的 supplier_id (与 token 绑定值一致才放行)
     * @param  string  $phoneSuffix 重新校验后 4 位, 防止 token 被转发
     */
    public function verify(Request $request, string $supplierId, string $phoneSuffix): ?Supplier
    {
        $token = $this->tokenFromRequest($request);
        if ($token === '') {
            return null;
        }

        $invite = TenderPortalInvite::where('token', $this->tokenDigest($token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invite) {
            return null;
        }

        // token 必须绑定到客户端声明的 supplier_id
        if ((string) $invite->supplier_id !== (string) $supplierId) {
            return null;
        }

        // 重放防护: 必须再传 phone_suffix, 且 hash 匹配
        if (!hash_equals((string) $invite->phone_suffix_hash, $this->hashSuffix($phoneSuffix))) {
            return null;
        }

        return Supplier::find($invite->supplier_id);
    }

    /**
     * 只读校验 (不烧 token): 用于 supplierInfo / invitations 详情查看
     *
     * 返回 Supplier|null — 通过则不烧 token, 调用方自行决定是否再 verify。
     */
    public function checkAccess(Request $request, string $supplierId): ?Supplier
    {
        $token = $this->tokenFromRequest($request);
        if ($token === '') {
            return null;
        }

        $invite = TenderPortalInvite::where('token', $this->tokenDigest($token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invite) {
            return null;
        }
        if ((string) $invite->supplier_id !== (string) $supplierId) {
            return null;
        }
        return Supplier::find($invite->supplier_id);
    }

    public function resolveAccess(Request $request): ?Supplier
    {
        $token = $this->tokenFromRequest($request);
        if ($token === '') {
            return null;
        }

        $invite = TenderPortalInvite::where('token', $this->tokenDigest($token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        return $invite ? Supplier::find($invite->supplier_id) : null;
    }

    /**
     * 暴露给前端: 供应商凭手机号 + 后 4 位申请 token
     *
     * 校验逻辑:
     *  - supplier 表里的 phone 后 4 位 与 入参 phoneSuffix 一致 → 签发 token
     *  - 失败: 返回 null (调用方 abort 401)
     */
    public function issueByCredentials(string $phone, string $supplierCode, ?Request $request = null): ?array
    {
        $supplier = Supplier::where('phone', $phone)->first();
        if (!$supplier || $supplierCode === '' || !hash_equals((string) $supplier->code, $supplierCode)) {
            return null;
        }
        $phoneDigits = preg_replace('/[^0-9]/', '', (string) $supplier->phone);
        if (strlen($phoneDigits) < 4) {
            return null;
        }
        return $this->issue($supplier, substr($phoneDigits, -4), $request) + ['supplier_id' => $supplier->id];
    }

    /**
     * 哈希后缀 + 全局 salt, 不存明文
     */
    private function hashSuffix(string $suffix): string
    {
        return hash_hmac('sha256', $suffix, (string) config('app.key', 'oa-portal-salt-fallback'));
    }

    private function tokenDigest(string $token): string
    {
        return hash('sha256', $token);
    }

    protected function tokenFromRequest(Request $request): string
    {
        // Body fallback keeps old clients working while new clients avoid leaking tokens in URLs.
        return (string) ($request->bearerToken() ?: $request->input('access_token', ''));
    }
}
