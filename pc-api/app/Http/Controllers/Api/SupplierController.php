<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Services\SupplierService;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.2 供应商控制器
 *
 * 路由前缀 /api/suppliers (v0.4.2 路由文件配置)
 *  1. GET    /suppliers                    列表
 *  2. POST   /suppliers                    新建（含账号）
 *  3. GET    /suppliers/{id}               详情
 *  4. PUT    /suppliers/{id}               更新
 *  5. DELETE /suppliers/{id}               删除
 *  6. POST   /suppliers/{id}/status        状态变更
 *  7. POST   /suppliers/{id}/contacts      同步联系人
 *  8. GET    /suppliers/{id}/evaluations   评价列表
 *  9. POST   /suppliers/{id}/evaluations   新增评价
 *  10. POST  /suppliers/{id}/reset-account 重置供应商账号密码
 */
class SupplierController extends Controller
{
    public function __construct(private SupplierService $service) {}

    /** 1. 列表 */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['keyword', 'type', 'status', 'page', 'per_page']);
        $result  = $this->service->listSuppliers($filters);

        return response()->json([
            'code' => 0,
            'data' => [
                'items' => $result['items'],
                'total' => $result['total'],
            ],
        ]);
    }

    /** 2. 新建 */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'type'              => ['required', Rule::in(['material', 'labor', 'outsource', 'service'])],
            'contact_person'    => ['nullable', 'string', 'max:50'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:100'],
            'address'           => ['nullable', 'string', 'max:255'],
            'category'          => ['nullable', 'string', 'max:100'],
            'business_license'  => ['nullable', 'string', 'max:50'],
            'legal_person'      => ['nullable', 'string', 'max:50'],
            'registered_capital' => ['nullable', 'numeric', 'min:0'],
            'website'           => ['nullable', 'string', 'max:200'],
            'bank_name'         => ['nullable', 'string', 'max:100'],
            'bank_account'      => ['nullable', 'string', 'max:50'],
            'account_name'      => ['nullable', 'string', 'max:100'],
            'tax_no'            => ['nullable', 'string', 'max:50'],
            'payment_terms'     => ['nullable', Rule::in(['cash', '30days', '60days', '90days'])],
            'rating'            => ['nullable', 'integer', 'min:1', 'max:5'],
            'status'            => ['nullable', Rule::in(['active', 'paused', 'blacklist'])],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'remark'            => ['nullable', 'string', 'max:2000'],

            'contacts'                       => ['nullable', 'array'],
            'contacts.*.name'                => ['required_with:contacts', 'string', 'max:50'],
            'contacts.*.phone'               => ['nullable', 'string', 'max:20'],
            'contacts.*.position'            => ['nullable', 'string', 'max:50'],
            'contacts.*.email'               => ['nullable', 'email', 'max:100'],
            'contacts.*.is_primary'          => ['nullable', 'boolean'],

            'account'                        => ['nullable', 'array'],
            'account.enabled'                => ['nullable', 'boolean'],
            'account.username'               => ['nullable', 'string', 'max:50'],
            'account.password'               => ['nullable', 'string', 'min:6', 'max:50'],
            'account.allowed_modules'        => ['nullable', 'array'],
        ]);

        $supplier = $this->service->createSupplierWithAccount(
            data:     $validated,
            contacts: $validated['contacts'] ?? [],
            userId:   $request->user()->id,
            account:  $validated['account'] ?? null,
        );

        return response()->json(['code' => 0, 'data' => $supplier->load('contacts')], 201);
    }

    /** 3. 详情 */
    public function show(int $id): JsonResponse
    {
        $detail = $this->service->getDetail($id);
        return response()->json(['code' => 0, 'data' => $detail]);
    }

    /** 4. 更新 */
    public function update(StoreSupplierRequest $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $validated = $request->validated();

        $supplier = $this->service->updateSupplier(
            $supplier,
            array_intersect_key($validated, array_flip([
                'name', 'type', 'contact_person', 'phone', 'email', 'address',
                'category', 'business_license', 'legal_person', 'registered_capital',
                'website', 'bank_name', 'bank_account', 'account_name', 'tax_no',
                'payment_terms', 'rating', 'status', 'remark',
            ])),
            $validated['contacts'] ?? null,
        );

        return response()->json(['code' => 0, 'data' => $supplier]);
    }

    /** 5. 删除 */
    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        // 简单保护：未付清款的不允许删
        $balance = (float) $supplier->payables()->sum('balance');
        if ($balance > 0) {
            return response()->json([
                'code' => 1,
                'msg'  => "供应商存在未结清款 ¥{$balance}，请先结清后再删",
            ], 422);
        }
        $supplier->delete();
        return response()->json(['code' => 0]);
    }

    /** 6. 状态变更 */
    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'paused', 'blacklist'])],
        ]);
        $supplier = Supplier::findOrFail($id);
        $supplier = $this->service->changeStatus($supplier, $validated['status']);
        return response()->json(['code' => 0, 'data' => $supplier]);
    }

    /** 7. 同步联系人 */
    public function syncContacts(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $validated = $request->validate([
            'contacts'              => ['required', 'array', 'min:1'],
            'contacts.*.name'       => ['required', 'string', 'max:50'],
            'contacts.*.phone'      => ['nullable', 'string', 'max:20'],
            'contacts.*.position'   => ['nullable', 'string', 'max:50'],
            'contacts.*.email'      => ['nullable', 'email', 'max:100'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
        ]);
        $this->service->syncContacts($supplier, $validated['contacts']);
        return response()->json(['code' => 0, 'data' => $supplier->fresh('contacts')]);
    }

    /** 8. 评价列表 */
    public function evaluations(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $list = SupplierEvaluation::where('supplier_id', $id)
            ->with(['project:id,name', 'evaluator:id,name'])
            ->orderByDesc('eval_date')
            ->paginate(20);
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /** 9. 新增评价 */
    public function addEvaluation(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $validated = $request->validate([
            'project_id'      => ['nullable', 'integer'],
            'quality_score'   => ['required', 'integer', 'min:1', 'max:5'],
            'delivery_score'  => ['required', 'integer', 'min:1', 'max:5'],
            'service_score'   => ['required', 'integer', 'min:1', 'max:5'],
            'price_score'     => ['required', 'integer', 'min:1', 'max:5'],
            'pros'            => ['nullable', 'string', 'max:1000'],
            'cons'            => ['nullable', 'string', 'max:1000'],
            'eval_date'       => ['required', 'date'],
        ]);

        $overall = round((
            $validated['quality_score']  +
            $validated['delivery_score'] +
            $validated['service_score']  +
            $validated['price_score']
        ) / 4, 1);

        $eval = SupplierEvaluation::create(array_merge($validated, [
            'supplier_id'  => $id,
            'overall_score' => $overall,
            'evaluator_id' => $request->user()->id,
        ]));

        return response()->json(['code' => 0, 'data' => $eval], 201);
    }

    /** 10. 重置供应商账号密码 */
    public function resetAccount(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:6', 'max:50'],
        ]);
        $user = \App\Models\User::where('type', 'supplier')
            ->where('supplier_id', $supplier->id)
            ->first();
        if (!$user) {
            return response()->json(['code' => 1, 'msg' => '该供应商未开通账号'], 404);
        }
        $newPwd = $validated['password'] ?? \Illuminate\Support\Str::random(12);
        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($newPwd)]);
        return response()->json([
            'code' => 0,
            'data' => [
                'user_id'      => $user->id,
                'username'     => $user->username,
                'new_password' => $newPwd,
            ],
        ]);
    }
}
