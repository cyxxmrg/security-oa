<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleInsurance;
use App\Models\VehicleMaintenanceRecord;
use App\Models\VehicleUsageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Cache::remember('vehicles:all', 300, function () {
            return Vehicle::with(['department', 'responsibleUser'])->get();
        });
        return response()->json(['code' => 0, 'data' => $data]);
    }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plate_no' => 'required|string|max:20',
            'brand' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'department_id' => 'nullable|integer',
            'responsible_user_id' => 'nullable|integer',
            'purchase_date' => 'nullable|date',
            'mileage' => 'nullable|integer|min:0',
            'status' => 'nullable|string',
        ]);
        Cache::forget('vehicles:all');
        try {
            return response()->json(['code' => 0, 'data' => Vehicle::create($data)]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // V1.2.10: UNIQUE 冲突转 422, 不暴露 500
            return response()->json(['code' => 422, 'message' => '车牌号已存在', 'errors' => ['plate_no' => ['该车牌号已被登记']]], 422);
        }
    }
    public function usageRequests(Request $request): JsonResponse { return response()->json(['code' => 0, 'data' => VehicleUsageRequest::with(['applicant', 'vehicle', 'approver'])->orderBy('usage_date', 'desc')->paginate()]); }
    public function storeUsageRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'usage_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'destination' => 'required|string',
            'purpose' => 'required|string',
            'passengers' => 'nullable|integer',
            'self_drive' => 'nullable|boolean',
            'vehicle_id' => 'nullable|exists:vehicles,id',
        ]);
        $data['applicant_id'] = $request->user()->id;
        $data['status'] = 'pending';
        return response()->json(['code' => 0, 'data' => VehicleUsageRequest::create($data)]);
    }
    public function dispatchVehicle(Request $request, VehicleUsageRequest $usageRequest): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:approved,rejected,using,returned',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'reason' => 'nullable|string',
        ]);
        $update = [
            'status' => $data['action'],
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
        ];
        if (!empty($data['vehicle_id'])) {
            $update['vehicle_id'] = $data['vehicle_id'];
        }
        $usageRequest->update($update);
        return response()->json(['code' => 0, 'message' => '操作完成']);
    }

    public function updateUsageRequest(Request $request, VehicleUsageRequest $usageRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => 'nullable|in:pending,approved,rejected,using,returned,cancelled',
            'start_mileage' => 'nullable|integer|min:0',
            'end_mileage' => 'nullable|integer|min:0',
            'actual_fuel' => 'nullable|numeric|min:0',
            'actual_mileage' => 'nullable|integer|min:0',
        ]);
        $usageRequest->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $usageRequest]);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        $vehicle->load(['department', 'responsibleUser']);
        return response()->json(['code' => 0, 'data' => $vehicle]);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'plate_no' => 'sometimes|string|max:20',
            'brand' => 'sometimes|string|max:50',
            'model' => 'sometimes|string|max:50',
            'department_id' => 'nullable|integer',
            'responsible_user_id' => 'nullable|integer',
            'status' => 'nullable|string',
            'mileage' => 'nullable|integer|min:0',
        ]);
        Cache::forget('vehicles:all');
        $vehicle->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $vehicle]);
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        // 业务规则：当前有未完结用车的车辆不允许删除
        if (VehicleUsageRequest::where('vehicle_id', $vehicle->id)
            ->whereIn('status', ['pending', 'approved', 'in_progress'])
            ->exists()) {
            return response()->json(['code' => 1001, 'message' => '该车辆有未完成的用车申请，不允许删除'], 422);
        }
        Cache::forget('vehicles:all');
        $vehicle->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    public function stats(): JsonResponse
    {
        $total = Vehicle::count();
        $available = Vehicle::where('status', 'available')->count();
        $inUse = Vehicle::where('status', 'in_use')->count();
        $maintenance = Vehicle::where('status', 'maintenance')->count();
        $retired = Vehicle::where('status', 'retired')->count();
        $pending = VehicleUsageRequest::where('status', 'pending')->count();
        $monthRequests = VehicleUsageRequest::where('usage_date', '>=', now()->subDays(30))->count();
        // 自动归档过期保险
        VehicleInsurance::where('end_date', '<', now()->toDateString())
            ->where('status', 'active')->update(['status' => 'expired']);
        $activeInsurance = VehicleInsurance::where('status', 'active')->count();
        $expiringSoon = VehicleInsurance::where('status', 'active')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count();
        $expiredInsurance = VehicleInsurance::where('status', 'expired')->count();
        $monthMaintenanceCost = VehicleMaintenanceRecord::where('maintenance_date', '>=', now()->subDays(30))->sum('cost');
        return response()->json(['code' => 0, 'data' => compact(
            'total', 'available', 'inUse', 'maintenance', 'retired', 'pending', 'monthRequests',
            'activeInsurance', 'expiringSoon', 'expiredInsurance', 'monthMaintenanceCost'
        )]);
    }

    // ========== 保险记录 ==========

    public function insurances(Request $request): JsonResponse
    {
        $query = VehicleInsurance::with('vehicle');
        if ($request->filled('vehicle_id')) $query->where('vehicle_id', $request->vehicle_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('keyword')) $query->where(function ($q) use ($request) {
            $q->where('policy_no', 'like', "%{$request->keyword}%")
              ->orWhere('insurance_company', 'like', "%{$request->keyword}%");
        });
        // 自动归档
        VehicleInsurance::where('end_date', '<', now()->toDateString())
            ->where('status', 'active')->update(['status' => 'expired']);
        $perPage = $request->per_page ?? 15;
        return response()->json(['code' => 0, 'data' => $query->orderBy('end_date', 'desc')->paginate($perPage)]);
    }

    public function storeInsurance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'insurance_company' => 'required|string|max:100',
            'policy_no' => 'required|string|max:100',
            'type' => 'required|in:compulsory,commercial',
            'premium' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'notes' => 'nullable|string',
        ]);
        $data['status'] = $data['end_date'] >= now()->toDateString() ? 'active' : 'expired';
        Cache::forget('vehicles:all');
        $row = VehicleInsurance::create($data);
        return response()->json(['code' => 0, 'message' => '保险记录已添加', 'data' => $row->load('vehicle')]);
    }

    public function updateInsurance(Request $request, VehicleInsurance $insurance): JsonResponse
    {
        $data = $request->validate([
            'insurance_company' => 'sometimes|string|max:100',
            'policy_no' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:compulsory,commercial',
            'premium' => 'sometimes|numeric|min:0',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'status' => 'sometimes|in:active,expired',
            'notes' => 'nullable|string',
        ]);
        $insurance->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $insurance->load('vehicle')]);
    }

    public function destroyInsurance(VehicleInsurance $insurance): JsonResponse
    {
        $insurance->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    // ========== 保养记录 ==========

    public function maintenances(Request $request): JsonResponse
    {
        $query = VehicleMaintenanceRecord::with(['vehicle', 'handledByUser']);
        if ($request->filled('vehicle_id')) $query->where('vehicle_id', $request->vehicle_id);
        if ($request->filled('maintenance_type')) $query->where('maintenance_type', $request->maintenance_type);
        if ($request->filled('keyword')) $query->where('description', 'like', "%{$request->keyword}%");
        $perPage = $request->per_page ?? 15;
        return response()->json(['code' => 0, 'data' => $query->orderBy('maintenance_date', 'desc')->paginate($perPage)]);
    }

    public function storeMaintenance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'maintenance_type' => 'required|in:routine,repair,inspection',
            'mileage' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'maintenance_date' => 'required|date',
            'description' => 'required|string',
            'next_maintenance_mileage' => 'nullable|integer|min:0',
            'next_maintenance_date' => 'nullable|date|after:maintenance_date',
            'handled_by' => 'nullable|exists:users,id',
        ]);
        $row = VehicleMaintenanceRecord::create($data);
        Cache::forget('vehicles:all');
        return response()->json(['code' => 0, 'message' => '保养记录已添加', 'data' => $row->load(['vehicle', 'handledByUser'])]);
    }

    public function updateMaintenance(Request $request, VehicleMaintenanceRecord $maintenance): JsonResponse
    {
        $data = $request->validate([
            'maintenance_type' => 'sometimes|in:routine,repair,inspection',
            'mileage' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'maintenance_date' => 'sometimes|date',
            'description' => 'sometimes|string',
            'next_maintenance_mileage' => 'nullable|integer|min:0',
            'next_maintenance_date' => 'nullable|date',
            'handled_by' => 'nullable|exists:users,id',
        ]);
        $maintenance->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $maintenance->load(['vehicle', 'handledByUser'])]);
    }

    public function destroyMaintenance(VehicleMaintenanceRecord $maintenance): JsonResponse
    {
        $maintenance->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }
}
