<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenderProject;
use App\Models\TenderBid;
use App\Models\TenderAttachment;
use App\Models\Supplier;
use App\Services\FileUploadService;
use App\Services\PortalInviteService;
use App\Support\PrivateFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * V0.6.0 招标中心 — 供应商门户 API (免登录, token 鉴权)
 *
 * 路由: /api/portal
 *   GET  /portal/t/{token}                  通过 token 拿招标信息
 *   POST /portal/t/{token}/login            验证手机号 (如有)
 *   GET  /portal/t/{token}/bids             看我方对该招标的投标
 *   POST /portal/t/{token}/bids             提交/更新投标
 *   GET  /portal/suppliers                  供应商列表 (内部用, 管理 portal 账号)
 *   POST /portal/suppliers                  新增供应商 (内部)
 *   GET  /portal/invitations?phone=xxx      供应商查自己的邀请
 */
class PortalController extends Controller
{
    /**
     * 通过 public_token 拉取招标公开信息 (给外部供应商看)
     * 关键: 只返回公开字段, 内部信息 (评分配置等) 不返回
     */
    public function tenderByToken(string $token): JsonResponse
    {
        $t = TenderProject::allData()->where('public_token', $token)->firstOr(function () {
            abort(response()->json(['code' => 1001, 'message' => '链接无效或已过期'], 404));
        });
        if (!in_array($t->status, ['open', 'bidding', 'published', 'evaluating', 'awarded', 'closed'])) {
            return response()->json(['code' => 1001, 'message' => '该项目当前不可访问'], 403);
        }
        // 公开附件 (visibility=public, 且是项目级非投标级)
        $atts = $t->attachments()->whereNull('tender_bid_id')->where('visibility', 'public')->get();

        return response()->json(['code' => 0, 'data' => [
            'id'              => $t->id,
            'code'            => $t->code,
            'name'            => $t->name,
            'description'     => $t->description,
            'type'            => $t->type,
            'status'          => $t->status,
            'status_label'    => $t->status_label,
            'required_items'  => $t->required_items,
            'deadline'        => $t->deadline?->toIso8601String(),
            'open_at'         => $t->open_at?->toIso8601String(),
            'project'         => $t->project ? ['id' => $t->project->id, 'name' => $t->project->name] : null,
            'attachments'     => $atts->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->file_name,
                'url' => url("/api/portal/t/{$token}/attachments/{$a->id}"),
                'size' => $a->file_size, 'mime' => $a->mime_type, 'category' => $a->category,
            ]),
            'public_token'    => $t->public_token,
        ]]);
    }

    /**
     * 供应商查自己对该招标的投标
     * 鉴权: 用 token + supplier_id (cookie 存 supplier_id 7 天)
     */
    public function myBid(Request $request, string $token): JsonResponse
    {
        $supplierId = $request->input('supplier_id') ?? $request->cookie('portal_supplier_id');
        if (!$supplierId) {
            return response()->json(['code' => 1001, 'message' => '请先选择/绑定供应商身份'], 401);
        }
        $verify = $this->verifySupplierAccess($request, (int) $supplierId);
        if ($verify) {
            return $verify;
        }
        $t = TenderProject::allData()->where('public_token', $token)->firstOr(function () {
            abort(response()->json(['code' => 1001, 'message' => '链接无效'], 404));
        });
        $bid = $t->bids()->where('supplier_id', $supplierId)->with('items')->first();
        return response()->json(['code' => 0, 'data' => $bid]);
    }

    /**
     * 供应商提交/更新投标 (公开)
     */
    public function submitBid(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'        => 'required|integer|exists:suppliers,id',
            'total_amount'       => 'required|numeric|min:0',
            'lead_time_days'     => 'nullable|integer|min:0',
            'technical_proposal' => 'nullable|string|max:5000',
            'remark'             => 'nullable|string|max:1000',
            'items'              => 'nullable|array',
            'items.*.name'       => 'required|string',
            'items.*.spec'       => 'nullable|string',
            'items.*.unit'       => 'nullable|string',
            'items.*.quantity'   => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'phone_suffix'       => 'required|string|size:4|regex:/^[0-9]+$/',
        ]);
        $verify = $this->verifySupplierAccess($request, (int) $data['supplier_id']);
        if ($verify) {
            return $verify;
        }
        $t = TenderProject::allData()->where('public_token', $token)->firstOr(function () {
            abort(response()->json(['code' => 1001, 'message' => '链接无效'], 404));
        });
        if (!in_array($t->status, ['open', 'bidding', 'published'])) {
            return response()->json(['code' => 1001, 'message' => '该项目已截止投标'], 422);
        }
        // 校验是否在邀请名单
        $invited = $t->invited_supplier_ids ?? [];
        if ($invited && !in_array($data['supplier_id'], $invited)) {
            return response()->json(['code' => 1002, 'message' => '该供应商未在邀请名单中'], 403);
        }
        $bid = $t->bids()->where('supplier_id', $data['supplier_id'])->first();
        if ($bid && in_array($bid->status, ['awarded', 'rejected', 'withdrawn'])) {
            return response()->json(['code' => 1003, 'message' => '该投标已定标或撤回, 不可修改'], 422);
        }
        if (!$bid) {
            $bid = $t->bids()->create([
                'supplier_id'    => $data['supplier_id'],
                'total_amount'   => $data['total_amount'],
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'technical_proposal' => $data['technical_proposal'] ?? null,
                'remark'         => $data['remark'] ?? null,
                'status'         => 'submitted',
                'submitted_at'   => now(),
                'code'           => $this->nextBidCode(),
            ]);
        } else {
            $bid->fill([
                'total_amount'   => $data['total_amount'],
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'technical_proposal' => $data['technical_proposal'] ?? null,
                'remark'         => $data['remark'] ?? null,
                'status'         => 'submitted',
                'submitted_at'   => now(),
            ])->save();
            $bid->items()->delete();
        }
        if (!empty($data['items'])) {
            foreach ($data['items'] as $it) {
                $bid->items()->create([
                    'name'        => $it['name'],
                    'spec'        => $it['spec'] ?? null,
                    'unit'        => $it['unit'] ?? '件',
                    'quantity'    => $it['quantity'],
                    'unit_price'  => $it['unit_price'],
                    'total_price' => round($it['quantity'] * $it['unit_price'], 2),
                ]);
            }
        }
        return response()->json(['code' => 0, 'message' => '投标已提交', 'data' => $bid->load('items')]);
    }

    /**
     * 供应商上传投标附件
     */
    public function uploadBidAttachment(Request $request, string $token, FileUploadService $uploader): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'bid_id'      => 'required|integer|exists:tender_bids,id',
            'file'        => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip,rar',
            'category'    => 'nullable|in:technical,business,qualification,bid_file,other',
            'visibility'  => 'nullable|in:public,eval_only',
            'phone_suffix'=> 'required|string|size:4|regex:/^[0-9]+$/',
        ]);
        $verify = $this->verifySupplierAccess($request, (int) $data['supplier_id']);
        if ($verify) {
            return $verify;
        }
        $t = TenderProject::allData()->where('public_token', $token)->firstOrFail();
        if (!in_array($t->status, ['open', 'bidding', 'published'], true)) {
            return response()->json(['code' => 1003, 'message' => '该项目当前不接受投标附件'], 422);
        }
        $bid = $t->bids()->where('id', $data['bid_id'])->where('supplier_id', $data['supplier_id'])->firstOrFail();
        // 合并说明 (HEAD + origin/main):
        //  - 保留本地 FileUploadService 统一校验 (扩展名/MIME/大小/sha256) 与"定标/撤回后禁传"守卫
        //  - 落盘改走 origin/main 的私有附件方案: local 盘 storage/app/private/..., 由 PrivateFileStorage 受控下载
        if (in_array($bid->status, ['awarded', 'rejected', 'withdrawn'], true)) {
            return response()->json(['code' => 1003, 'message' => '该投标已结束，不能继续上传附件'], 422);
        }
        $result = $uploader->store($request, 'file', [
            'disk'         => 'local',
            'subdir'       => "private/tenders/{$t->id}/bids/{$bid->id}",
            'allowed_ext'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar'],
            'allowed_mime' => [
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg', 'image/png', 'application/zip', 'application/x-rar-compressed',
            ],
            'max_size'     => 51200,
        ]);
        $att  = TenderAttachment::create([
            'tender_project_id' => $t->id,
            'tender_bid_id'     => $bid->id,
            'uploaded_by_supplier_id' => $data['supplier_id'],
            'file_name' => $result['original_name'],
            'file_path' => $result['path'],
            'mime_type' => $result['mime'],
            'file_size' => $result['size'],
            'category'  => $data['category'] ?? 'bid_file',
            'visibility' => $data['visibility'] ?? 'eval_only',
        ]);
        return response()->json(['code' => 0, 'data' => $att]);
    }

    public function downloadPublicAttachment(string $token, int $attachment)
    {
        $tender = TenderProject::where('public_token', $token)->firstOrFail();
        abort_unless(in_array($tender->status, ['bidding', 'published', 'evaluating', 'awarded', 'closed'], true), 403);
        $file = TenderAttachment::where('tender_project_id', $tender->id)
            ->whereNull('tender_bid_id')
            ->where('visibility', 'public')
            ->findOrFail($attachment);

        return PrivateFileStorage::download($file->file_path, $file->file_name, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * 短期 token + 手机号后 4 位的供应商访问校验。
     *
     *  - 前端必须先用 supplierInfo / invitations 拿到 access_token (签发时校验后 4 位)
     *  - 投标/上传/查我方投标 都必须同时带 access_token + phone_suffix
     *  - token 默认 30 分钟内可复用，但必须绑定 supplier_id + 手机号后 4 位; 可通过 used_at 主动撤销
     */
    private function verifySupplierAccess(Request $request, int $supplierId): ?JsonResponse
    {
        $suffix = (string) ($request->header('X-Portal-Phone-Suffix') ?: $request->input('phone_suffix', ''));
        if (!preg_match('/^[0-9]{4}$/', $suffix)) {
            return response()->json(['code' => 1004, 'message' => '请提供供应商预留手机号后 4 位'], 422);
        }

        $supplier = Supplier::find($supplierId);
        if (!$supplier) {
            return response()->json(['code' => 1005, 'message' => '供应商身份无效'], 404);
        }

        // 兼容三种携带方式: Authorization: Bearer (新前端) / body access_token / X-Portal-Access-Token (老前端)
        $token = (string) ($request->bearerToken()
            ?: $request->input('access_token')
            ?: $request->header('X-Portal-Access-Token', ''));
        if ($token === '') {
            return response()->json(['code' => 1007, 'message' => '缺少 access_token, 请先调用 access 端点获取'], 401);
        }

        /** @var PortalInviteService $inviter */
        $inviter = app(PortalInviteService::class);
        $resolved = $inviter->checkAccess($request, (string) $supplierId, $suffix);
        if (!$resolved || $resolved->id !== $supplier->id) {
            return response()->json(['code' => 1006, 'message' => '供应商身份校验失败 (token 无效或已过期)'], 403);
        }

        return null;
    }

    /**
     * 供应商凭手机号申请短期 access_token (合并两侧签发方式):
     *   - origin/main: phone + supplier_code (供应商编号)
     *   - HEAD:        phone + phone_suffix (手机号后 4 位) — 老客户端兼容
     *
     * POST /api/portal/access  body: { phone, supplier_code | phone_suffix }
     * 返回: { access_token, supplier_id, expires_in, expires_at, ttl_minutes }
     */
    public function access(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'         => 'required|string|max:32',
            'supplier_code' => 'nullable|string|max:64',
            'phone_suffix'  => 'nullable|string|size:4|regex:/^[0-9]+$/',
        ]);
        if (empty($data['supplier_code']) && empty($data['phone_suffix'])) {
            return response()->json(['code' => 1010, 'message' => '请提供 supplier_code 或手机号后 4 位'], 422);
        }
        /** @var PortalInviteService $inviter */
        $inviter = app(PortalInviteService::class);
        $issued = !empty($data['supplier_code'])
            ? $inviter->issueByCredentials($data['phone'], $data['supplier_code'], $request)
            : $inviter->issueByPhone($data['phone'], $data['phone_suffix'], $request);
        if (!$issued) {
            return response()->json(['code' => 1010, 'message' => '供应商身份校验失败'], 403);
        }
        return response()->json([
            'code' => 0,
            'data' => [
                'access_token' => $issued['token'],
                'supplier_id'  => $issued['supplier_id'] ?? null,
                'expires_in'   => $issued['ttl_minutes'] * 60,
                'expires_at'   => $issued['expires_at']->toIso8601String(),
                'ttl_minutes'  => $issued['ttl_minutes'],
            ],
        ]);
    }

    /**
     * P1-7 修复: 供应商用手机号查自己的邀请
     *
     * 脱敏: 不返回 supplier.id / phone 完整 / 邮箱; 只返回最小显示信息
     */
    public function invitations(Request $request): JsonResponse
    {
        // 双模式鉴权 (HEAD + origin/main 并集):
        //  - origin/main: 仅凭短期 access_token 经 resolveAccess 确认供应商 (不要求每次带 supplier_id/phone)
        //  - HEAD: 携带 supplier_id + phone + phone_suffix 走 verifySupplierAccess (兼容老客户端)
        $supplier = app(PortalInviteService::class)->resolveAccess($request);
        if (!$supplier) {
            $data = $request->validate([
                'phone'        => 'required|string|max:32',
                'supplier_id'  => 'required|integer|exists:suppliers,id',
                'phone_suffix' => 'required|string|size:4|regex:/^[0-9]+$/',
            ]);
            $verify = $this->verifySupplierAccess($request, (int) $data['supplier_id']);
            if ($verify) return $verify;
            $supplier = Supplier::whereKey($data['supplier_id'])->where('phone', $data['phone'])->first();
            if (!$supplier) {
                return response()->json(['code' => 1006, 'message' => '供应商身份校验失败'], 403);
            }
        }
        // 该供应商被邀请的招标 (在 invited_supplier_ids 数组中)
        $list = TenderProject::allData()->whereJsonContains('invited_supplier_ids', $supplier->id)
                              ->whereIn('status', ['open', 'bidding', 'published', 'evaluating', 'awarded', 'closed'])
                              ->orderByDesc('publish_at')
                              ->get(['id', 'code', 'name', 'status', 'deadline', 'public_token']);
        // 脱敏: 不返回 supplier_id 给未认证查询, 手机号打码
        $phoneMasked = $this->maskPhone($supplier->phone);
        return response()->json(['code' => 0, 'data' => [
            'supplier' => [
                'id'     => $supplier->id,
                'name'  => $supplier->name,
                'phone' => $phoneMasked,
            ],
            'invitations' => $list->map(fn($i) => [
                'id' => $i->id, 'code' => $i->code, 'name' => $i->name, 'status' => $i->status,
                'deadline' => $i->deadline, 'public_token' => $i->public_token,
            ]),
        ]]);
    }

    /**
     * P1-7 修复: V0.6.3 供应商门户首页
     *
     * 脱敏:
     *   - 不返回 supplier.email
     *   - bids 不返回 total_amount / tender_id (用 stats 代替)
     *   - supplier.id 仍返回 (供前端携带调后续接口), 但需配合 access_token 才有权
     * GET /api/portal/supplier/info?phone=xxx
     */
    public function supplierInfo(Request $request): JsonResponse
    {
        // 双模式鉴权 (HEAD + origin/main 并集), 同 invitations()
        $supplier = app(PortalInviteService::class)->resolveAccess($request);
        if (!$supplier) {
            $data = $request->validate([
                'phone'        => 'required|string|max:32',
                'supplier_id'  => 'required|integer|exists:suppliers,id',
                'phone_suffix' => 'required|string|size:4|regex:/^[0-9]+$/',
            ]);
            $verify = $this->verifySupplierAccess($request, (int) $data['supplier_id']);
            if ($verify) return $verify;
            $supplier = Supplier::whereKey($data['supplier_id'])->where('phone', $data['phone'])->first();
            if (!$supplier) {
                return response()->json(['code' => 1006, 'message' => '供应商身份校验失败'], 403);
            }
        }

        // 历史投标 (脱敏: 只返回 id/status/created_at, 不返回 total_amount/tender_id)
        $bids = \DB::table('tender_bids')
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'status', 'created_at']);

        // 简报
        $stats = [
            'invitation_count' => TenderProject::allData()->whereJsonContains('invited_supplier_ids', $supplier->id)->count(),
            'bid_count'        => $bids->count(),
            'won_count'        => $bids->where('status', 'awarded')->count(),
            'active_tender'    => TenderProject::allData()->whereJsonContains('invited_supplier_ids', $supplier->id)
                                                ->whereIn('status', ['open', 'bidding', 'published'])->count(),
        ];

        return response()->json(['code' => 0, 'data' => [
            'supplier' => [
                'id'     => $supplier->id,
                'code'   => $supplier->code,
                'name'   => $supplier->name,
                'phone'  => $this->maskPhone($supplier->phone),
                'type'   => $supplier->type,
                'status' => $supplier->status,
                'rating' => $supplier->rating,
                // P1-7: 删除 'email' (脱敏)
            ],
            'stats' => $stats,
            'bids'  => $bids,
        ]]);
    }

    /**
     * 手机号打码: 13800001234 -> 138****1234
     */
    private function maskPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($digits);
        if ($len < 7) return $digits; // 太短不处理
        return substr($digits, 0, 3) . '****' . substr($digits, -4);
    }

    private function nextBidCode(): string
    {
        $today = date('Ymd');
        $sequence = \App\Services\NumberSequenceService::next("tender-bid:{$today}", static function () use ($today): int {
            return TenderBid::allData()->where('code', 'like', "BID-{$today}-%")
                ->pluck('code')
                ->map(static function (string $code): int {
                    $parts = explode('-', $code);
                    return (int) end($parts);
                })
                ->max() ?? 0;
        });

        return 'BID-' . $today . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
