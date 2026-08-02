<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\TenderPortalInvite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * P1-7 修复: 供应商门户一次性邀请 token 管理
 *
 * 设计目标:
 *  - 用 token 替代 "供应商 portal_supplier_id + 手机号后 4 位" 的单因子弱认证
 *  - token 由 mobile (phone 后 4 位) 签发, 绑定到 supplier_id
 *  - 30 分钟 TTL, 用完即焚 (used_at 一旦写入则永久失效)
 *  - 不暴露 supplier_id 给未认证的查询 (supplierInfo / invitations)
 *
 * 关键方法:
 *  - issue(Supplier, phoneSuffix): 生成并持久化新 token, 返回明文 token + 元数据
 *  - verify(Request, supplierId, phoneSuffix): 校验 token 有效并烧掉, 返回 Supplier|null
 *  - checkAccess(Request, supplierId, phoneSuffix): 不烧 token 的可读检查 (用于 supplierInfo)
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
            'token'             => $plain,
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
     * 校验 token + 烧掉 (consume-once)
     *
     * 校验失败返回 null; 成功返回 Supplier 实例, 并已标记 used_at。
     *
     * @param  string  $supplierId  客户端声明的 supplier_id (与 token 绑定值一致才放行)
     * @param  string  $phoneSuffix 重新校验后 4 位, 防止 token 被转发
     */
    public function verify(Request $request, string $supplierId, string $phoneSuffix): ?Supplier
    {
        $token = (string) ($request->input('access_token') ?? '');
        if ($token === '') {
            return null;
        }

        $invite = TenderPortalInvite::where('token', $token)
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

        // 标记烧掉
        $invite->forceFill(['used_at' => now()])->save();

        return Supplier::find($invite->supplier_id);
    }

    /**
     * 只读校验 (不烧 token): 用于 supplierInfo / invitations 详情查看
     *
     * 返回 Supplier|null — 通过则不烧 token, 调用方自行决定是否再 verify。
     */
    public function checkAccess(Request $request, string $supplierId): ?Supplier
    {
        $token = (string) ($request->input('access_token') ?? '');
        if ($token === '') {
            return null;
        }

        $invite = TenderPortalInvite::where('token', $token)
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

    /**
     * 暴露给前端: 供应商凭手机号 + 后 4 位申请 token
     *
     * 校验逻辑:
     *  - supplier 表里的 phone 后 4 位 与 入参 phoneSuffix 一致 → 签发 token
     *  - 失败: 返回 null (调用方 abort 401)
     */
    public function issueByPhone(string $phone, string $phoneSuffix, ?Request $request = null): ?array
    {
        if (!preg_match('/^[0-9]{4}$/', $phoneSuffix)) {
            return null;
        }
        $supplier = Supplier::where('phone', $phone)->first();
        if (!$supplier) {
            return null;
        }
        $phoneDigits = preg_replace('/[^0-9]/', '', (string) $supplier->phone);
        if ($phoneDigits === '' || substr($phoneDigits, -4) !== $phoneSuffix) {
            return null;
        }
        return $this->issue($supplier, $phoneSuffix, $request);
    }

    /**
     * 哈希后缀 + 全局 salt, 不存明文
     */
    private function hashSuffix(string $suffix): string
    {
        return hash_hmac('sha256', $suffix, (string) config('app.key', 'oa-portal-salt-fallback'));
    }
}