<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FollowUpRecord;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\Receivable;
use App\Models\ServiceOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 客户业务服务 — V1.2.7d 拆自 CustomerController
 *
 * 业务域：
 *   - Customer 客户档案 CRUD
 *   - 联系人 Contact
 *   - 开票信息 InvoiceInfo
 *   - 跟进 FollowUpRecord
 *   - 统计 + 健康度
 */
class CustomerService
{
    // ============================================================
    // === 客户 Customer ===
    // ============================================================

    public function paginateCustomers(Request $request)
    {
        $perPage = max(1, min((int)($request->per_page ?? 15), 200));

        $query = Customer::with(['primaryContact', 'contacts', 'assignedUser'])
            ->withCount(['projects', 'followUps']);

        if ($request->filled('keyword')) $query->where('name', 'like', "%{$request->keyword}%");
        if ($request->filled('category')) $query->where('category', $this->normalizeCategory($request->category));
        if ($request->filled('industry')) $query->where('industry', 'like', "%{$request->industry}%");
        if ($request->filled('tag')) {
            $tag = $request->tag;
            $query->where(function ($q) use ($tag) {
                $q->where('tags', 'like', '%"' . $tag . '"%')
                  ->orWhere('tags', 'like', '%' . $tag . '%');
            });
        }

        $lastFollowSub = DB::table('follow_up_records')
            ->selectRaw('customer_id, MAX(created_at) as last_at')
            ->groupBy('customer_id');

        $query->addSelect(['customers.*', 'lf.last_at as last_follow_at'])
            ->leftJoinSub($lastFollowSub, 'lf', 'lf.customer_id', '=', 'customers.id');

        $list = $query->orderBy('customers.created_at', 'desc')->paginate($perPage);

        $list->getCollection()->transform(function ($c) {
            $c->project_count = $c->projects_count;
            $c->last_follow_at = $c->last_follow_at ? Carbon::parse($c->last_follow_at)->format('Y-m-d H:i') : null;
            $c->contact = $c->primaryContact?->name ?? ($c->contacts->first()?->name ?? '');
            $c->phone   = $c->primaryContact?->phone ?? ($c->contacts->first()?->phone ?? '');
            $c->health_score = $this->calcScore($c);
            $c->health_level = $this->toLevel($c->health_score);
            return $c;
        });
        return $list;
    }

    public function showCustomer(Customer $customer): Customer
    {
        $customer->load(['primaryContact', 'contacts', 'invoiceInfos', 'assignedUser', 'followUps.user']);
        // V1.2.10: 显式 setAttribute 注入 camelCase 关系数据
        // (Eloquent 默认 toArray 把关系名转 snake_case, 前端要 primaryContact)
        $customer->setAttribute('primaryContact', $customer->getRelation('primaryContact'));
        return $customer;
    }

    public function customerProfile(Customer $customer): Customer
    {
        $customer->load(['projects', 'followUps', 'serviceOrders', 'receivables']);
        return $customer;
    }

    public function createCustomer(Request $request): Customer
    {
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'short_name'   => 'nullable|string|max:100',
            'category'     => 'nullable|string|in:normal,vip,strategic,inactive,potential',
            'industry'     => 'nullable|string|max:100',
            'scale'        => 'nullable|string|max:50',
            'level'        => 'nullable|string|in:A,B,C,D',
            'province'     => 'nullable|string|max:50',
            'city'         => 'nullable|string|max:50',
            'district'     => 'nullable|string|max:50',
            'address'      => 'nullable|string|max:500',
            'tags'         => 'nullable|array',
            'assigned_to'  => 'nullable|integer|exists:users,id',
            'remark'       => 'nullable|string',
            // V1.2.10: 联系人信息 (前端 dialog 用 contact/phone 简化字段)
            'contact'      => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['category']   = $data['category'] ?? 'normal';

        // V1.2.10: 自动创建主联系人 (customer_contacts 表)
        $contactName = $data['contact'] ?? null;
        $contactPhone = $data['phone'] ?? null;
        unset($data['contact']); // contact 不存 customers 表

        $customer = Customer::create($data);

        if ($contactName || $contactPhone) {
            $customer->contacts()->create([
                'name'       => $contactName ?: '主联系人',
                'phone'      => $contactPhone ?: '-',
                'is_primary' => true,
            ]);
        }

        return $customer;
    }

    public function updateCustomer(Request $request, Customer $customer): Customer
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:200',
            'short_name'   => 'nullable|string|max:100',
            'category'     => 'nullable|string|in:normal,vip,strategic,inactive,potential',
            'industry'     => 'nullable|string|max:100',
            'scale'        => 'nullable|string|max:50',
            'level'        => 'nullable|string|in:A,B,C,D',
            'province'     => 'nullable|string|max:50',
            'city'         => 'nullable|string|max:50',
            'district'     => 'nullable|string|max:50',
            'address'      => 'nullable|string|max:500',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:200',
            'website'      => 'nullable|string|max:200',
            'tags'         => 'nullable|array',
            'assigned_to'  => 'nullable|integer|exists:users,id',
            'remark'       => 'nullable|string',
            'contact'      => 'nullable|string|max:100',
        ]);
        $customer->update($data);
        return $customer->fresh();
    }

    public function destroyCustomer(Customer $customer): void
    {
        if (Project::where('customer_id', $customer->id)->exists()) {
            throw new RuntimeException('客户有项目，不可删除');
        }
        $customer->delete();
    }

    // ============================================================
    // === 联系人 / 开票信息 / 跟进 / 设备 ===
    // ============================================================

    public function listContacts(Customer $customer)
    {
        return $customer->contacts()->orderByDesc('is_primary')->get();
    }

    public function createContact(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:50',
            'phone'    => 'required|string|max:20',
            'email'    => 'nullable|email|max:200',
            'title'    => 'nullable|string|max:50',
            'gender'   => 'nullable|in:male,female,other',
            'birthday' => 'nullable|date',
            'is_primary' => 'nullable|boolean',
            'wechat'   => 'nullable|string|max:100',
            'remark'   => 'nullable|string',
        ]);
        $data['customer_id'] = $customer->id;
        if (!empty($data['is_primary'])) {
            $customer->contacts()->update(['is_primary' => false]);
            $data['is_primary'] = true;
        }
        return $customer->contacts()->create($data);
    }

    public function updateContact(Request $request, Customer $customer, $contactId)
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:50',
            'phone'    => 'sometimes|string|max:20',
            'email'    => 'nullable|email|max:200',
            'title'    => 'nullable|string|max:50',
            'gender'   => 'nullable|in:male,female,other',
            'birthday' => 'nullable|date',
            'is_primary' => 'nullable|boolean',
            'wechat'   => 'nullable|string|max:100',
            'remark'   => 'nullable|string',
        ]);
        $contact = $customer->contacts()->findOrFail($contactId);
        if (!empty($data['is_primary'])) {
            $customer->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
            $data['is_primary'] = true;
        }
        $contact->update($data);
        return $contact->fresh();
    }

    public function destroyContact(Customer $customer, $contactId): void
    {
        $customer->contacts()->findOrFail($contactId)->delete();
    }

    public function listInvoiceInfos(Customer $customer)
    {
        return $customer->invoiceInfos()->orderByDesc('is_default')->get();
    }

    public function createInvoiceInfo(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'invoice_type'   => 'required|in:normal,special',
            'title'          => 'required|string|max:200',
            'tax_no'         => 'required|string|max:50',
            'address'        => 'nullable|string|max:200',
            'phone'          => 'nullable|string|max:20',
            'bank_name'      => 'nullable|string|max:100',
            'bank_account'   => 'nullable|string|max:50',
            'is_default'     => 'nullable|boolean',
        ]);
        $data['customer_id'] = $customer->id;
        if (!empty($data['is_default'])) {
            $customer->invoiceInfos()->update(['is_default' => false]);
            $data['is_default'] = true;
        }
        return $customer->invoiceInfos()->create($data);
    }

    public function updateInvoiceInfo(Request $request, Customer $customer, $infoId)
    {
        $data = $request->validate([
            'invoice_type'   => 'sometimes|in:normal,special',
            'title'          => 'sometimes|string|max:200',
            'tax_no'         => 'sometimes|string|max:50',
            'address'        => 'nullable|string|max:200',
            'phone'          => 'nullable|string|max:20',
            'bank_name'      => 'nullable|string|max:100',
            'bank_account'   => 'nullable|string|max:50',
            'is_default'     => 'nullable|boolean',
        ]);
        $info = $customer->invoiceInfos()->findOrFail($infoId);
        if (!empty($data['is_default'])) {
            $customer->invoiceInfos()->where('id', '!=', $info->id)->update(['is_default' => false]);
            $data['is_default'] = true;
        }
        $info->update($data);
        return $info->fresh();
    }

    public function destroyInvoiceInfo(Customer $customer, $infoId): void
    {
        $customer->invoiceInfos()->findOrFail($infoId)->delete();
    }

    public function followUps(Request $request, Customer $customer)
    {
        $query = $customer->followUps()->with('user:id,name');
        if ($request->filled('type')) $query->where('type', $request->type);
        return $query->orderByDesc('created_at')->paginate($request->per_page ?? 15);
    }

    public function createFollowUp(Request $request, Customer $customer): FollowUpRecord
    {
        $data = $request->validate([
            'type'        => 'required|string|in:visit,call,phone,wechat,email,meeting,other',
            'content'     => 'required|string',
            'next_action' => 'nullable|string',
            'next_date'   => 'nullable|date',
        ]);
        $data['customer_id'] = $customer->id;
        $data['user_id']     = $request->user()->id;
        return FollowUpRecord::create($data)->load('user:id,name');
    }

    public function customerDevices(Request $request, Customer $customer)
    {
        return ServiceOrder::with('device:id,name,code,brand,model')
            ->where('customer_id', $customer->id)
            ->whereNotNull('device_id')
            ->select('id', 'device_id', 'order_no', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ============================================================
    // === 统计 + 健康度 + 地图 ===
    // ============================================================

    public function stats(Request $request): array
    {
        return Cache::remember('customers:stats', 300, function () {
            $total         = Customer::count();
            $vip           = Customer::where('category', 'vip')->count();
            $project_total = Customer::withCount('projects')->get()->sum('projects_count');
            $new_this_month = Customer::where('created_at', '>=', now()->startOfMonth())->count();
            return compact('total', 'vip', 'project_total', 'new_this_month');
        });
    }

    public function health(Request $request): array
    {
        $customers = Customer::orderBy('id')->get(['id', 'name', 'category']);
        if ($customers->isEmpty()) {
            return [
                'list'    => [],
                'summary' => ['total' => 0, 'healthy' => 0, 'good' => 0, 'average' => 0, 'warning' => 0, 'avg_score' => 0, 'needs_attention' => []],
            ];
        }

        $ids = $customers->pluck('id')->all();

        // 单 query 拿每个客户的最近跟进
        $lastFollowMap = DB::table('follow_up_records')
            ->selectRaw('customer_id, MAX(created_at) as last_at')
            ->whereIn('customer_id', $ids)
            ->groupBy('customer_id')
            ->pluck('last_at', 'customer_id');

        // 合同总额
        $contractMap = DB::table('project_contracts as pc')
            ->join('projects as p', 'p.id', '=', 'pc.project_id')
            ->selectRaw('p.customer_id, COALESCE(SUM(pc.contract_amount), 0) as total')
            ->whereIn('p.customer_id', $ids)
            ->groupBy('p.customer_id')
            ->pluck('total', 'customer_id');

        // 应收
        $receivableMap = DB::table('receivables')
            ->selectRaw('customer_id, COALESCE(SUM(amount), 0) as total, COALESCE(SUM(received_amount), 0) as received')
            ->whereIn('customer_id', $ids)
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        // 活跃项目
        $activeProjectMap = DB::table('projects')
            ->selectRaw('customer_id, COUNT(*) as cnt')
            ->whereIn('customer_id', $ids)
            ->whereNotIn('status', ['completed', 'cancelled', 'done'])
            ->groupBy('customer_id')
            ->pluck('cnt', 'customer_id');

        $list = [];
        $summary = ['total' => 0, 'healthy' => 0, 'good' => 0, 'average' => 0, 'warning' => 0, 'avg_score' => 0, 'needs_attention' => []];
        $scoreTotal = 0;

        foreach ($customers as $c) {
            // 1) 跟进活跃度 30
            $lastAt = $lastFollowMap->get($c->id);
            $days = $lastAt ? (int) Carbon::parse($lastAt)->diffInDays(now()) : null;
            $follow = match (true) {
                $days === null || $days > 90 => 0,
                $days <= 7  => 30,
                $days <= 30 => 20,
                default     => 10,
            };

            // 2) 合同价值 25
            $contractAmount = (float) ($contractMap->get($c->id) ?? 0);
            $contract = match (true) {
                $contractAmount >= 1_000_000 => 25,
                $contractAmount >= 500_000   => 20,
                $contractAmount >= 100_000   => 15,
                $contractAmount >= 10_000    => 8,
                default                       => 2,
            };

            // 3) 回款 20
            $recvRow = $receivableMap->get($c->id);
            $totalReceivable = $recvRow ? (float) $recvRow->total : 0.0;
            $totalReceived   = $recvRow ? (float) $recvRow->received : 0.0;
            if ($totalReceivable <= 0) {
                $payment = 15;
            } else {
                $ratio = $totalReceivable / max($totalReceived, 1.0);
                $payment = match (true) {
                    $ratio < 0.3 => 20,
                    $ratio < 0.6 => 15,
                    $ratio < 0.9 => 10,
                    default      => 5,
                };
            }

            // 4) 等级 15
            $cat = $c->category ?: 'normal';
            $level = match ($cat) {
                'vip'       => 15,
                'strategic' => 12,
                'normal'    => 8,
                'inactive'  => 0,
                default     => 5,
            };

            // 5) 项目 10
            $activeProjects = (int) ($activeProjectMap->get($c->id) ?? 0);
            $projects = match (true) {
                $activeProjects >= 3 => 10,
                $activeProjects >= 1 => 7,
                default              => 2,
            };

            $score = $follow + $contract + $payment + $level + $projects;
            $lv = $this->scoreToLevel($score);
            $list[] = [
                'id'           => $c->id,
                'name'         => $c->name,
                'category'     => $cat,
                'score'        => $score,
                'level'        => $lv,
                'last_follow'  => $lastAt ? Carbon::parse($lastAt)->format('Y-m-d') : null,
            ];
            $scoreTotal += $score;
            $summary['total']++;
            $summary[$lv] = ($summary[$lv] ?? 0) + 1;
            if ($score < 30) {
                $summary['needs_attention'][] = ['id' => $c->id, 'name' => $c->name, 'score' => $score];
            }
        }
        $summary['avg_score'] = $summary['total'] > 0 ? round($scoreTotal / $summary['total'], 1) : 0;
        return ['list' => $list, 'summary' => $summary];
    }

    public function mapData(Request $request): array
    {
        try {
            $customers = Customer::orderBy('id')
                ->whereNotNull('province')
                ->get();
        } catch (\Throwable $e) {
            Log::error('CustomerService::mapData', ['msg' => $e->getMessage()]);
            $customers = Customer::whereNotNull('province')->get();
        }

        $list = $customers->map(function ($c) {
            return [
                'id'       => $c->id,
                'name'     => $c->name,
                'region'   => trim(($c->province ?? '') . ' ' . ($c->city ?? '') . ' ' . ($c->district ?? '')),
                'province' => $c->province,
                'city'     => $c->city,
                'address'  => $c->address,
                'industry' => $c->industry ?? null,
                'level'    => $c->level ?? null,
                'status'   => $c->status ?? null,
            ];
        });

        $byProvince = $list->groupBy('province')->map(fn($g, $k) => [
            'province' => $k,
            'count'    => $g->count(),
            'customers' => $g->values(),
        ])->values();

        return [
            'total'      => $list->count(),
            'by_province' => $byProvince,
            'list'       => $list,
        ];
    }

    /**
     * 行业列表 = DB 现有值 + 默认常用列表（确保新客户也能选到常见行业）
     */
    public function industries(): array
    {
        $dbValues = DB::table('customers')
            ->whereNotNull('industry')
            ->where('industry', '<>', '')
            ->distinct()
            ->orderBy('industry')
            ->pluck('industry')
            ->toArray();

        $defaults = ['教育', '医疗', '金融', '地产', '互联网', '制造业', '零售', '政府', '安防工程'];
        $merged = array_unique(array_merge($defaults, $dbValues));
        sort($merged);
        return array_values($merged);
    }

    // ============================================================
    // === 内部 ===
    // ============================================================

    private function normalizeCategory(string $cat): string
    {
        $map = [
            '重点客户' => 'vip',
            '战略客户' => 'strategic',
            '普通客户' => 'normal',
            '休眠客户' => 'inactive',
        ];
        return $map[$cat] ?? $cat;
    }

    /**
     * 单客户健康度（用于 index 列表）
     */
    public function calcScore(Customer $c): int
    {
        $score = 0;
        // 简化版：只算可见字段
        $score += match ($c->category ?? 'normal') {
            'vip'       => 15,
            'strategic' => 12,
            'normal'    => 8,
            'inactive'  => 0,
            default     => 5,
        };
        if ($c->projects_count >= 3) $score += 10;
        elseif ($c->projects_count >= 1) $score += 7;
        else $score += 2;

        if ($c->last_follow_at) {
            $days = (int) Carbon::parse($c->last_follow_at)->diffInDays(now());
            $score += match (true) {
                $days <= 7  => 30,
                $days <= 30 => 20,
                $days <= 90 => 10,
                default     => 0,
            };
        }
        return $score;
    }

    public function toLevel(int $score): string
    {
        return $this->scoreToLevel($score);
    }

    private function scoreToLevel(int $score): string
    {
        return match (true) {
            $score >= 70 => 'healthy',
            $score >= 50 => 'good',
            $score >= 30 => 'average',
            default      => 'warning',
        };
    }
}
