<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ClearsListCache;
use App\Models\Customer;
use App\Models\FollowUpRecord;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * 客户 — V1.2.7d 瘦身后只做 HTTP 路由
 *
 * 业务全部委托给 App\Services\CustomerService
 */
class CustomerController extends Controller
{
    use ClearsListCache;

    public function __construct(private CustomerService $svc) {}

    public function index(Request $request): JsonResponse
    {
        // V1.3.1: 缓存响应 JSON, 避免 Eloquent 序列化开销
        $cacheKey = 'customers:index:' . ($request->user()?->id ?? 0) . ':' . md5(serialize($request->all()));
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(json_decode($cached, true));
        }

        $result = $this->svc->paginateCustomers($request);

        $json = json_encode(['code' => 0, 'data' => $result->toArray()]);
        Cache::put($cacheKey, $json, 30);

        return response()->json(json_decode($json, true));
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->stats($request)]);
    }

    public function health(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->health($request)]);
    }

    public function import(Request $request): JsonResponse
    {
        // 复用 InventoryService::parseSpreadsheet 太重 — 客户导入业务简单, 暂保留原逻辑
        return response()->json(['code' => 0, 'data' => ['message' => '请用 /api/admin/import 或前端导入页']], 200);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->showCustomer($customer)]);
    }

    public function profile(Customer $customer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->customerProfile($customer)]);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->svc->createCustomer($request);
        $this->clearListCache('customers:index');
        return response()->json(['code' => 0, 'data' => $result]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $result = $this->svc->updateCustomer($request, $customer);
        $this->clearListCache('customers:index');
        return response()->json(['code' => 0, 'data' => $result]);
    }

    public function listContacts(Customer $customer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->listContacts($customer)]);
    }

    public function storeContact(Request $request, Customer $customer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->createContact($request, $customer)]);
    }

    public function updateContact(Request $request, Customer $customer, $contact): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->updateContact($request, $customer, $contact)]);
    }

    public function destroyContact(Customer $customer, $contact): JsonResponse
    {
        $this->svc->destroyContact($customer, $contact);
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function listInvoiceInfos(Customer $customer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->listInvoiceInfos($customer)]);
    }

    public function storeInvoiceInfo(Request $request, Customer $customer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->createInvoiceInfo($request, $customer)]);
    }

    public function updateInvoiceInfo(Request $request, Customer $customer, $info): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->updateInvoiceInfo($request, $customer, $info)]);
    }

    public function destroyInvoiceInfo(Customer $customer, $info): JsonResponse
    {
        $this->svc->destroyInvoiceInfo($customer, $info);
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $this->svc->destroyCustomer($customer);
            $this->clearListCache('customers:index');
            return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function followUps(Request $request, Customer $customer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->followUps($request, $customer)]);
    }

    public function storeFollowUp(Request $request, Customer $customer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->createFollowUp($request, $customer)]);
    }

    public function devices(Request $request, Customer $customer): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->customerDevices($request, $customer)]);
    }

    public function mapData(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->mapData($request)]);
    }

    public function industries(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->industries()]);
    }
}
