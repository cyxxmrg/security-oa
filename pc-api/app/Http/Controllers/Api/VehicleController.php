<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleInsurance;
use App\Models\VehicleMaintenanceRecord;
use App\Models\VehicleUsageRequest;
use App\Support\AuthScope;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    private const ACTIVE_USAGE_STATUSES = ['pending', 'approved', 'using', 'in_progress'];

    private const VEHICLE_STATUS_ALIASES = [
        'normal' => 'normal',
        'available' => 'normal',
        'maintenance' => 'maintenance',
        'scrapped' => 'scrapped',
        'retired' => 'scrapped',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::with([
            'department',
            'responsibleUser',
            'usageRequests' => fn ($usage) => $usage->whereIn('status', ['using', 'in_progress'])->select('id', 'vehicle_id'),
        ]);

        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->input('keyword'));
            $query->where(function ($q) use ($keyword) {
                $q->where('plate_no', 'like', "%{$keyword}%")
                    ->orWhere('brand', 'like', "%{$keyword}%")
                    ->orWhere('model', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('status')) {
            $this->applyVehicleStatusFilter($query, (string) $request->input('status'));
        }

        try {
            $schedule = $this->validatedSchedule($request, false);
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        }
        if ($schedule !== null) {
            $this->excludeConflictingVehicles(
                $query,
                $schedule['usage_date'],
                $schedule['start_time'],
                $schedule['end_time'],
                $request->integer('exclude_usage_request_id') ?: null
            );
        }

        $vehicles = $query->orderBy('plate_no')->get();
        $vehicles->each(function (Vehicle $vehicle): void {
            if (in_array((string) $vehicle->status, ['normal', 'available'], true) && $vehicle->usageRequests->isNotEmpty()) {
                $vehicle->status = 'in_use';
            }
            $vehicle->unsetRelation('usageRequests');
        });
        return response()->json(['code' => 0, 'data' => $vehicles]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plate_no' => 'required|string|max:20',
            'brand' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'department_id' => 'nullable|integer|exists:departments,id',
            'responsible_user_id' => 'nullable|integer|exists:users,id',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'mileage' => 'nullable|integer|min:0',
            'status' => 'nullable|in:normal,available,maintenance,scrapped,retired',
            'year' => 'nullable|integer|min:1900|max:2100',
            'color' => 'nullable|string|max:20',
            'vin' => 'nullable|string|max:50',
            'engine_no' => 'nullable|string|max:50',
            'seats' => 'nullable|integer|min:1|max:100',
            'fuel_type' => 'nullable|string|max:50',
        ]);
        $data['plate_no'] = trim($data['plate_no']);

        try {
            $data['status'] = $this->normalizeVehicleStatus($data['status'] ?? null);
            $vehicle = Vehicle::create($data)->load(['department', 'responsibleUser']);
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        } catch (UniqueConstraintViolationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '车牌号已存在',
                'errors' => ['plate_no' => ['该车牌号已被登记']],
            ], 422);
        }

        return response()->json(['code' => 0, 'data' => $vehicle]);
    }

    public function usageRequests(Request $request): JsonResponse
    {
        $query = VehicleUsageRequest::with(['applicant', 'vehicle', 'approver'])
            ->orderByDesc('usage_date')
            ->orderByDesc('start_time');

        if (!$this->canDispatch($request->user())) {
            $query->where('applicant_id', $request->user()->id);
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }
        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->input('keyword'));
            $query->where(function ($q) use ($keyword) {
                $q->where('destination', 'like', "%{$keyword}%")
                    ->orWhere('purpose', 'like', "%{$keyword}%")
                    ->orWhereHas('applicant', fn ($user) => $user->where('name', 'like', "%{$keyword}%"))
                    ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('plate_no', 'like', "%{$keyword}%"));
            });
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        return response()->json(['code' => 0, 'data' => $query->paginate($perPage)]);
    }

    public function storeUsageRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'usage_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i,H:i:s',
            'end_time' => 'required|date_format:H:i,H:i:s',
            'destination' => 'required|string|max:200',
            'purpose' => 'required|string|max:5000',
            'passengers' => 'nullable|integer|min:1|max:100',
            'self_drive' => 'nullable|boolean',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
        ]);

        try {
            $schedule = $this->validatedSchedule($request, true);
            $data['usage_date'] = $schedule['usage_date'];
            $data['start_time'] = $schedule['start_time'];
            $data['end_time'] = $schedule['end_time'];
            $data['applicant_id'] = $request->user()->id;
            $data['status'] = 'pending';

            $usageRequest = DB::transaction(function () use ($data, $schedule) {
                if (!empty($data['vehicle_id'])) {
                    $vehicle = Vehicle::lockForUpdate()->findOrFail($data['vehicle_id']);
                    $this->assertVehicleCanBeUsed($vehicle);
                    $this->assertNoUsageConflict($vehicle->id, $schedule['usage_date'], $schedule['start_time'], $schedule['end_time']);
                }

                return VehicleUsageRequest::create($data)->load(['applicant', 'vehicle', 'approver']);
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['code' => 0, 'data' => $usageRequest]);
    }

    public function dispatchVehicle(Request $request, VehicleUsageRequest $usageRequest): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:approved,rejected,using,returned',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'reason' => 'nullable|string|max:500',
            'start_mileage' => 'required_if:action,returned|nullable|integer|min:0',
            'end_mileage' => 'required_if:action,returned|nullable|integer|min:0',
            'actual_fuel' => 'nullable|numeric|min:0',
        ]);
        $actorId = (int) $request->user()->id;

        try {
            $updated = DB::transaction(function () use ($data, $usageRequest, $actorId) {
                $usage = VehicleUsageRequest::lockForUpdate()->findOrFail($usageRequest->id);
                $currentStatus = (string) $usage->status;
                $action = (string) $data['action'];
                $allowed = [
                    'pending' => ['approved', 'rejected'],
                    'approved' => ['using'],
                    'using' => ['returned'],
                ];
                if (!in_array($action, $allowed[$currentStatus] ?? [], true)) {
                    throw new \DomainException("用车申请当前状态为 {$currentStatus}，不能执行{$action}操作");
                }

                if ($action === 'approved') {
                    if (!empty($data['vehicle_id'])) {
                        $vehicle = Vehicle::lockForUpdate()->findOrFail($data['vehicle_id']);
                        $this->assertVehicleCanBeUsed($vehicle);
                        $this->assertNoUsageConflict($vehicle->id, $usage->usage_date->toDateString(), $usage->start_time, $usage->end_time, $usage->id);
                        $usage->vehicle_id = $vehicle->id;
                    } elseif ($usage->vehicle_id) {
                        $vehicle = Vehicle::lockForUpdate()->findOrFail($usage->vehicle_id);
                        $this->assertVehicleCanBeUsed($vehicle);
                        $this->assertNoUsageConflict($vehicle->id, $usage->usage_date->toDateString(), $usage->start_time, $usage->end_time, $usage->id);
                    }
                    $usage->status = 'approved';
                    $usage->approver_id = $actorId;
                    $usage->approved_at = now();
                } elseif ($action === 'rejected') {
                    $usage->status = 'rejected';
                    $usage->approver_id = $actorId;
                    $usage->approved_at = now();
                } elseif ($action === 'using') {
                    $vehicleId = $data['vehicle_id'] ?? $usage->vehicle_id;
                    if (!$vehicleId) {
                        throw new \DomainException('派车前必须选择车辆');
                    }
                    $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicleId);
                    $this->assertVehicleCanBeUsed($vehicle);
                    $this->assertNoUsageConflict($vehicle->id, $usage->usage_date->toDateString(), $usage->start_time, $usage->end_time, $usage->id);
                    $usage->vehicle_id = $vehicle->id;
                    if ($this->vehicleHasMileageColumn() && $usage->start_mileage === null) {
                        $usage->start_mileage = (int) ($vehicle->mileage ?? 0);
                    }
                    $usage->status = 'using';
                } else {
                    $this->assertMileageRange($data['start_mileage'], $data['end_mileage']);
                    if (!$usage->vehicle_id) {
                        throw new \DomainException('该申请未绑定车辆，不能登记归还');
                    }
                    $vehicle = Vehicle::lockForUpdate()->findOrFail($usage->vehicle_id);
                    $usage->status = 'returned';
                    $usage->start_mileage = (int) $data['start_mileage'];
                    $usage->end_mileage = (int) $data['end_mileage'];
                    $usage->actual_mileage = $usage->end_mileage - $usage->start_mileage;
                    if (array_key_exists('actual_fuel', $data)) {
                        $usage->actual_fuel = $data['actual_fuel'];
                    }
                    if ($this->vehicleHasMileageColumn()) {
                        $vehicle->mileage = max((int) ($vehicle->mileage ?? 0), $usage->end_mileage);
                    }
                }

                $usage->save();
                if ($action === 'returned' && isset($vehicle)) {
                    if ($this->vehicleHasMileageColumn()) {
                        $vehicle->save();
                    }
                    if ((string) $vehicle->status === 'in_use' && !$this->hasActiveUsage($vehicle->id, $usage->id)) {
                        $vehicle->status = 'normal';
                        $vehicle->save();
                    }
                }
                return $usage->load(['applicant', 'vehicle', 'approver']);
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['code' => 0, 'message' => '操作完成', 'data' => $updated]);
    }

    public function updateUsageRequest(Request $request, VehicleUsageRequest $usageRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => 'nullable|in:cancelled',
            'start_mileage' => 'nullable|integer|min:0',
            'end_mileage' => 'nullable|integer|min:0',
            'actual_fuel' => 'nullable|numeric|min:0',
            'actual_mileage' => 'nullable|integer|min:0',
        ]);
        $user = $request->user();
        $canDispatch = $this->canDispatch($user);

        if (!$canDispatch && (int) $usageRequest->applicant_id !== (int) $user->id) {
            return response()->json(['code' => 403, 'message' => '无权修改他人的用车申请'], 403);
        }

        try {
            $updated = DB::transaction(function () use ($data, $usageRequest, $canDispatch, $user) {
                $usage = VehicleUsageRequest::lockForUpdate()->findOrFail($usageRequest->id);
                if (isset($data['status'])) {
                    if ($canDispatch || (int) $usage->applicant_id !== (int) $user->id || $usage->status !== 'pending') {
                        throw new \DomainException('只有申请人可以取消自己的待审批申请');
                    }
                    $usage->status = 'cancelled';
                }

                $hasMetrics = array_key_exists('start_mileage', $data)
                    || array_key_exists('end_mileage', $data)
                    || array_key_exists('actual_mileage', $data)
                    || array_key_exists('actual_fuel', $data);
                if ($hasMetrics) {
                    if (!$canDispatch || !in_array((string) $usage->status, ['using', 'returned'], true)) {
                        throw new \DomainException('只有调度人员可以登记已派车申请的里程和油耗');
                    }
                    $startMileage = array_key_exists('start_mileage', $data) ? $data['start_mileage'] : $usage->start_mileage;
                    $endMileage = array_key_exists('end_mileage', $data) ? $data['end_mileage'] : $usage->end_mileage;
                    if ($startMileage !== null && $endMileage !== null) {
                        $this->assertMileageRange($startMileage, $endMileage);
                        $usage->actual_mileage = (int) $endMileage - (int) $startMileage;
                    }
                    foreach (['start_mileage', 'end_mileage', 'actual_fuel'] as $field) {
                        if (array_key_exists($field, $data)) {
                            $usage->{$field} = $data[$field];
                        }
                    }
                    if (array_key_exists('actual_mileage', $data)
                        && $startMileage !== null
                        && $endMileage !== null
                        && (int) $data['actual_mileage'] !== (int) $usage->actual_mileage) {
                        throw new \DomainException('实际里程必须等于还车里程减出车里程');
                    }
                    if ($usage->vehicle_id && $endMileage !== null && $usage->status === 'returned' && $this->vehicleHasMileageColumn()) {
                        $vehicle = Vehicle::lockForUpdate()->findOrFail($usage->vehicle_id);
                        $vehicle->mileage = max((int) ($vehicle->mileage ?? 0), (int) $endMileage);
                        $vehicle->save();
                    }
                }

                $usage->save();
                return $usage->load(['applicant', 'vehicle', 'approver']);
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $updated]);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $vehicle->load(['department', 'responsibleUser'])]);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'plate_no' => ['sometimes', 'string', 'max:20', Rule::unique('vehicles', 'plate_no')->ignore($vehicle->id)],
            'brand' => 'sometimes|string|max:50',
            'model' => 'sometimes|string|max:50',
            'department_id' => 'nullable|integer|exists:departments,id',
            'responsible_user_id' => 'nullable|integer|exists:users,id',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'mileage' => 'nullable|integer|min:0',
            'status' => 'nullable|in:normal,available,maintenance,scrapped,retired',
            'year' => 'nullable|integer|min:1900|max:2100',
            'color' => 'nullable|string|max:20',
            'vin' => 'nullable|string|max:50',
            'engine_no' => 'nullable|string|max:50',
            'seats' => 'nullable|integer|min:1|max:100',
            'fuel_type' => 'nullable|string|max:50',
        ]);
        if (array_key_exists('plate_no', $data)) {
            $data['plate_no'] = trim($data['plate_no']);
        }

        try {
            if (array_key_exists('status', $data)) {
                $data['status'] = $this->normalizeVehicleStatus($data['status']);
            }
            $updated = DB::transaction(function () use ($data, $vehicle) {
                $current = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
                if (array_key_exists('mileage', $data) && $data['mileage'] < (int) ($current->mileage ?? 0)) {
                    throw new \DomainException('车辆里程不能小于当前登记里程');
                }
                if (isset($data['status']) && in_array($data['status'], ['maintenance', 'scrapped'], true)
                    && $this->hasActiveUsage($current->id)) {
                    throw new \DomainException('该车辆存在未完成用车申请，不能设为维修或报废状态');
                }
                $current->update($data);
                return $current->load(['department', 'responsibleUser']);
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        } catch (UniqueConstraintViolationException $e) {
            return response()->json(['code' => 422, 'message' => '车牌号已存在'], 422);
        }

        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $updated]);
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            DB::transaction(function () use ($vehicle) {
                $current = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
                if ($this->hasActiveUsage($current->id)) {
                    throw new \DomainException('该车辆有未完成的用车申请，不允许删除');
                }
                $current->delete();
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    public function stats(): JsonResponse
    {
        $total = Vehicle::count();
        $available = Vehicle::whereIn('status', ['normal', 'available'])
            ->whereDoesntHave('usageRequests', fn ($usage) => $usage->whereIn('status', ['using', 'in_progress']))
            ->count();
        $maintenance = Vehicle::where('status', 'maintenance')->count();
        $retired = Vehicle::whereIn('status', ['scrapped', 'retired'])->count();
        $inUse = VehicleUsageRequest::whereIn('status', ['using', 'in_progress'])->whereNotNull('vehicle_id')->distinct('vehicle_id')->count('vehicle_id');
        $pending = VehicleUsageRequest::where('status', 'pending')->count();
        $approved = VehicleUsageRequest::where('status', 'approved')->count();
        $monthRequests = VehicleUsageRequest::where('usage_date', '>=', now()->subDays(30)->toDateString())->count();
        VehicleInsurance::where('end_date', '<', now()->toDateString())->where('status', 'active')->update(['status' => 'expired']);
        $activeInsurance = VehicleInsurance::where('status', 'active')->count();
        $expiringSoon = VehicleInsurance::where('status', 'active')->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count();
        $expiredInsurance = VehicleInsurance::where('status', 'expired')->count();
        $monthMaintenanceCost = VehicleMaintenanceRecord::where('maintenance_date', '>=', now()->subDays(30)->toDateString())->sum('cost');

        return response()->json(['code' => 0, 'data' => compact(
            'total', 'available', 'inUse', 'maintenance', 'retired', 'pending', 'approved', 'monthRequests',
            'activeInsurance', 'expiringSoon', 'expiredInsurance', 'monthMaintenanceCost'
        )]);
    }

    public function insurances(Request $request): JsonResponse
    {
        VehicleInsurance::where('end_date', '<', now()->toDateString())->where('status', 'active')->update(['status' => 'expired']);
        $query = VehicleInsurance::with('vehicle');
        if ($request->filled('vehicle_id')) $query->where('vehicle_id', $request->integer('vehicle_id'));
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->input('keyword'));
            $query->where(fn ($q) => $q->where('policy_no', 'like', "%{$keyword}%")->orWhere('insurance_company', 'like', "%{$keyword}%"));
        }
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        return response()->json(['code' => 0, 'data' => $query->orderByDesc('end_date')->paginate($perPage)]);
    }

    public function storeInsurance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'insurance_company' => 'required|string|max:100',
            'policy_no' => 'required|string|max:100',
            'type' => 'required|in:compulsory,commercial',
            'premium' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'notes' => 'nullable|string|max:5000',
        ]);
        $data['status'] = $data['end_date'] >= now()->toDateString() ? 'active' : 'expired';
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
            'notes' => 'nullable|string|max:5000',
        ]);
        $startDate = $data['start_date'] ?? optional($insurance->start_date)->toDateString();
        $endDate = $data['end_date'] ?? optional($insurance->end_date)->toDateString();
        if ($startDate && $endDate && $endDate <= $startDate) {
            return response()->json(['code' => 422, 'message' => '保险结束日期必须晚于开始日期'], 422);
        }
        $data['status'] = $endDate >= now()->toDateString() ? 'active' : 'expired';
        $insurance->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $insurance->load('vehicle')]);
    }

    public function destroyInsurance(VehicleInsurance $insurance): JsonResponse
    {
        $insurance->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    public function maintenances(Request $request): JsonResponse
    {
        $query = VehicleMaintenanceRecord::with(['vehicle', 'handledByUser']);
        if ($request->filled('vehicle_id')) $query->where('vehicle_id', $request->integer('vehicle_id'));
        if ($request->filled('maintenance_type')) $query->where('maintenance_type', $request->input('maintenance_type'));
        if ($request->filled('keyword')) $query->where('description', 'like', '%' . trim((string) $request->input('keyword')) . '%');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        return response()->json(['code' => 0, 'data' => $query->orderByDesc('maintenance_date')->paginate($perPage)]);
    }

    public function storeMaintenance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'maintenance_type' => 'required|in:routine,repair,inspection',
            'mileage' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'maintenance_date' => 'required|date',
            'description' => 'required|string|max:5000',
            'next_maintenance_mileage' => 'nullable|integer|min:0',
            'next_maintenance_date' => 'nullable|date|after:maintenance_date',
            'handled_by' => 'nullable|integer|exists:users,id',
        ]);
        $row = VehicleMaintenanceRecord::create($data);
        return response()->json(['code' => 0, 'message' => '保养记录已添加', 'data' => $row->load(['vehicle', 'handledByUser'])]);
    }

    public function updateMaintenance(Request $request, VehicleMaintenanceRecord $maintenance): JsonResponse
    {
        $data = $request->validate([
            'maintenance_type' => 'sometimes|in:routine,repair,inspection',
            'mileage' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'maintenance_date' => 'sometimes|date',
            'description' => 'sometimes|string|max:5000',
            'next_maintenance_mileage' => 'nullable|integer|min:0',
            'next_maintenance_date' => 'nullable|date',
            'handled_by' => 'nullable|integer|exists:users,id',
        ]);
        $maintenanceDate = $data['maintenance_date'] ?? optional($maintenance->maintenance_date)->toDateString();
        $nextDate = $data['next_maintenance_date'] ?? optional($maintenance->next_maintenance_date)->toDateString();
        if ($nextDate && $maintenanceDate && $nextDate <= $maintenanceDate) {
            return response()->json(['code' => 422, 'message' => '下次保养日期必须晚于保养日期'], 422);
        }
        $maintenance->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $maintenance->load(['vehicle', 'handledByUser'])]);
    }

    public function destroyMaintenance(VehicleMaintenanceRecord $maintenance): JsonResponse
    {
        $maintenance->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    private function canDispatch($user): bool
    {
        if (!$user || AuthScope::isUnrestricted($user)) return true;
        return method_exists($user, 'hasActivePermissionTo')
            ? $user->hasActivePermissionTo('vehicle.dispatch')
            : $user->can('vehicle.dispatch');
    }

    private function normalizeVehicleStatus(?string $status): string
    {
        if ($status === null || $status === '') return 'normal';
        if ($status === 'in_use') {
            throw new \DomainException('车辆使用中状态由用车申请自动维护，不能直接设置');
        }
        if (!isset(self::VEHICLE_STATUS_ALIASES[$status])) {
            throw new \DomainException('车辆状态不合法');
        }
        return self::VEHICLE_STATUS_ALIASES[$status];
    }

    private function applyVehicleStatusFilter($query, string $status): void
    {
        if (in_array($status, ['available', 'normal'], true)) {
            $query->whereIn('status', ['normal', 'available'])
                ->whereDoesntHave('usageRequests', fn ($usage) => $usage->whereIn('status', ['using', 'in_progress']));
        } elseif ($status === 'in_use') {
            $query->where(function ($q) {
                $q->where('status', 'in_use')->orWhereHas('usageRequests', fn ($usage) => $usage->whereIn('status', ['using', 'in_progress']));
            });
        } elseif (in_array($status, ['retired', 'scrapped'], true)) {
            $query->whereIn('status', ['scrapped', 'retired']);
        } else {
            $query->where('status', $status);
        }
    }

    private function assertVehicleCanBeUsed(Vehicle $vehicle): void
    {
        if (!in_array((string) $vehicle->status, ['normal', 'available'], true)) {
            throw new \DomainException('车辆当前不是可用状态');
        }
    }

    private function assertNoUsageConflict(int $vehicleId, string $usageDate, string $startTime, string $endTime, ?int $excludeId = null): void
    {
        $query = VehicleUsageRequest::query()
            ->where('vehicle_id', $vehicleId)
            ->where('usage_date', $usageDate)
            ->whereIn('status', self::ACTIVE_USAGE_STATUSES)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
        if ($excludeId !== null) $query->where('id', '!=', $excludeId);
        if ($query->exists()) {
            throw new \DomainException('该车辆在所选时间段已有未完成用车安排');
        }
    }

    private function excludeConflictingVehicles($query, string $usageDate, string $startTime, string $endTime, ?int $excludeId = null): void
    {
        $query->whereDoesntHave('usageRequests', function ($usage) use ($usageDate, $startTime, $endTime, $excludeId) {
            $usage->where('usage_date', $usageDate)
                ->whereIn('status', self::ACTIVE_USAGE_STATUSES)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime);
            if ($excludeId !== null) $usage->where('id', '!=', $excludeId);
        });
    }

    private function hasActiveUsage(int $vehicleId, ?int $excludeId = null): bool
    {
        $query = VehicleUsageRequest::where('vehicle_id', $vehicleId)->whereIn('status', self::ACTIVE_USAGE_STATUSES);
        if ($excludeId !== null) $query->where('id', '!=', $excludeId);
        return $query->exists();
    }

    private function validatedSchedule(Request $request, bool $required): ?array
    {
        $hasAny = $request->filled('usage_date') || $request->filled('start_time') || $request->filled('end_time');
        if (!$required && !$hasAny) return null;
        if (!$request->filled('usage_date') || !$request->filled('start_time') || !$request->filled('end_time')) {
            throw new \DomainException('用车日期、开始时间和结束时间必须同时提供');
        }

        try {
            $usageDate = Carbon::parse((string) $request->input('usage_date'))->toDateString();
            $startTime = $this->normalizeTime((string) $request->input('start_time'));
            $endTime = $this->normalizeTime((string) $request->input('end_time'));
        } catch (\Throwable $e) {
            throw new \DomainException('用车日期或时间格式不正确');
        }
        if ($required && $usageDate < now()->toDateString()) {
            throw new \DomainException('用车日期不能早于今天');
        }
        if ($startTime >= $endTime) {
            throw new \DomainException('结束时间必须晚于开始时间，暂不支持跨日用车');
        }
        return [
            'usage_date' => $usageDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    private function normalizeTime(string $value): string
    {
        foreach (['H:i', 'H:i:s'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('H:i:s');
            } catch (\Throwable $e) {
            }
        }
        throw new \DomainException('时间格式不正确');
    }

    private function assertMileageRange($startMileage, $endMileage): void
    {
        if ((int) $endMileage < (int) $startMileage) {
            throw new \DomainException('还车里程不能小于出车里程');
        }
    }

    private function vehicleHasMileageColumn(): bool
    {
        static $hasColumn = null;
        return $hasColumn ??= Schema::hasColumn('vehicles', 'mileage');
    }
}
