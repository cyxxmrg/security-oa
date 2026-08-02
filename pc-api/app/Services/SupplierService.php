<?php

namespace App\Services;

use App\Concerns\GeneratesUniqueCode;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * V0.4.2 供应商服务
 *
 * 关键能力:
 *  - createSupplierWithAccount: 创建供应商 + 自动开供应商账号 (users.type='supplier')
 *  - 业务封装：CRUD、状态变更、联系人维护
 */
class SupplierService
{
    use GeneratesUniqueCode;

    /**
     * 生成供应商编号 SUP-YYYY-NNN
     */
    public function generateCode(): string
    {
        return self::uniqueCode('SUP');
    }

    /**
     * 创建供应商 + 自动开账号
     *
     * @param  array  $data 供应商主表字段
     * @param  array  $contacts [{name, phone, email, is_primary, position, ...}]
     * @param  int    $userId 创建人
     * @param  array  $account [username, password, allowed_modules]
     */
    public function createSupplierWithAccount(
        array $data,
        array $contacts,
        int $userId,
        ?array $account = null
    ): Supplier {
        return DB::transaction(function () use ($data, $contacts, $userId, $account) {
            // 1) 创建 supplier
            // V0.6.x 兼容: 前端可能发 'remark' 或 'notes',DB 列名是 notes
            $notes = $data['notes'] ?? $data['remark'] ?? null;
            $createData = array_merge($data, [
                'code'       => $this->generateCode(),
                'created_by' => $userId,
                'status'     => $data['status'] ?? Supplier::STATUS_ACTIVE,
                'notes'      => $notes,
            ]);
            unset($createData['remark']);  // 表里没这列,清掉避免 SQL 错误
            $supplier = Supplier::create($createData);

            // 2) 联系人
            $this->syncContacts($supplier, $contacts);

            // 3) 自动开供应商账号（默认行为：未禁用则开）
            if ($account !== null && ($account['enabled'] ?? true) === true) {
                $this->createSupplierUser($supplier, $account);
            }

            return $supplier->fresh(['contacts']);
        });
    }

    /**
     * 给指定 supplier 创建 users 记录 (type=supplier, supplier_id=...)
     *
     * 默认 username=sup_{code}  password=随机 12 位
     */
    public function createSupplierUser(Supplier $supplier, ?array $account = null): User
    {
        $username = $account['username'] ?? 'sup_' . Str::lower($supplier->code);
        $password = $account['password'] ?? Str::random(12);
        $modules  = $account['allowed_modules'] ?? ['supplier:portal', 'external-quote:submit'];

        // 校验 username 唯一
        if (User::where('username', $username)->exists()) {
            throw new \RuntimeException("供应商账号用户名 {$username} 已存在");
        }

        return User::create([
            'name'            => $supplier->name,
            'username'        => $username,
            'email'           => $supplier->email ?: ($username . '@supplier.local'),
            'phone'           => $supplier->phone ?: ('00000000000'),
            'password'        => Hash::make($password),
            'type'            => 'supplier',
            'supplier_id'     => $supplier->id,
            'allowed_modules' => json_encode($modules, JSON_UNESCAPED_UNICODE),
            'status'          => 'active',
        ]);
    }

    /**
     * 同步联系人（全删全插）
     */
    public function syncContacts(Supplier $supplier, array $contacts): void
    {
        $supplier->contacts()->delete();

        foreach ($contacts as $idx => $row) {
            SupplierContact::create([
                'supplier_id' => $supplier->id,
                'name'        => $row['name'] ?? '',
                'position'    => $row['position'] ?? null,
                'phone'       => $row['phone'] ?? null,
                'tel'         => $row['tel'] ?? null,
                'email'       => $row['email'] ?? null,
                'wechat'      => $row['wechat'] ?? null,
                'is_primary'  => (bool) ($row['is_primary'] ?? $idx === 0),
                'remark'      => $row['remark'] ?? null,
            ]);
        }
    }

    /**
     * 更新供应商 + 联系人
     */
    public function updateSupplier(Supplier $supplier, array $data, ?array $contacts = null): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $contacts) {
            // V0.6.x 兼容: 前端可能发 'remark',DB 列名是 notes
            if (isset($data['remark'])) {
                $data['notes'] = $data['notes'] ?? $data['remark'];
                unset($data['remark']);
            }
            $supplier->update($data);
            if ($contacts !== null) {
                $this->syncContacts($supplier, $contacts);
            }
            return $supplier->fresh('contacts');
        });
    }

    /**
     * 状态变更（封禁/解封）
     */
    public function changeStatus(Supplier $supplier, string $status): Supplier
    {
        if (!in_array($status, [
            Supplier::STATUS_ACTIVE,
            Supplier::STATUS_PAUSED,
            Supplier::STATUS_BLACKLIST,
        ], true)) {
            throw new \InvalidArgumentException("非法状态: {$status}");
        }
        $supplier->update(['status' => $status]);
        return $supplier->fresh();
    }

    /**
     * 供应商列表（带统计）
     *
     * @return array{items: \Illuminate\Support\Collection, total: int}
     */
    public function listSuppliers(array $filters = []): array
    {
        $q = Supplier::query()->with(['contacts', 'creator:id,name']);

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $q->where(function ($w) use ($kw) {
                $w->where('name', 'like', "%{$kw}%")
                  ->orWhere('code', 'like', "%{$kw}%")
                  ->orWhere('contact_person', 'like', "%{$kw}%")
                  ->orWhere('phone', 'like', "%{$kw}%");
            });
        }
        if (!empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        $total = (clone $q)->count();
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $size  = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $items = $q->orderByDesc('id')->skip(($page - 1) * $size)->take($size)->get();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * 供应商详情（带聚合金额）
     */
    public function getDetail(int $supplierId): array
    {
        $supplier = Supplier::with([
            'contacts',
            'creator:id,name',
        ])->findOrFail($supplierId);

        $payableTotal  = (float) $supplier->payables()->sum('amount');
        $paidTotal     = (float) $supplier->payables()->sum('paid_amount');
        $balanceTotal  = (float) $supplier->payables()->sum('balance');
        $quoteCount    = $supplier->quotes()->count();
        $avgRating     = round((float) $supplier->evaluations()->avg('overall_score'), 1);

        return [
            'supplier'      => $supplier,
            'payable_total' => $payableTotal,
            'paid_total'    => $paidTotal,
            'balance_total' => $balanceTotal,
            'quote_count'   => $quoteCount,
            'avg_rating'    => $avgRating,
        ];
    }
}
