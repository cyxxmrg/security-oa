<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use App\Models\Position;
use App\Models\SkillTag;
use App\Models\Certificate;
use App\Services\CacheHelper;
use App\Support\TemporaryPassword;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Requests\Employee\ResetPasswordRequest;
use App\Http\Resources\EmployeeProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    /**
     * P1-11 修复: 业务用户只能看脱敏的 profile; 自己 + system_admin 可见完整
     */
    private function canViewFullProfile(?User $viewer, User $target): bool
    {
        if (!$viewer) return false;
        if ($viewer->id === $target->id) return true;
        if ($viewer->is_system) return true;
        if (method_exists($viewer, 'hasActiveRole') && $viewer->hasActiveRole('system_admin')) {
            return true;
        }
        return false;
    }

    /**
     * P1-11 修复: 阻止业务用户修改他人的薪资/银行卡/紧急联系人
     */
    private function assertCanUpdateSensitiveFields(Request $request, User $target): ?JsonResponse
    {
        $viewer = $request->user();
        if ($this->canViewFullProfile($viewer, $target)) {
            return null;
        }
        $sensitive = [
            'base_salary', 'salary_allowance',
            'bank_name', 'bank_account',
            'emergency_contact', 'emergency_phone',
        ];
        foreach ($sensitive as $f) {
            if ($request->exists($f)) {
                return response()->json([
                    'code' => 1010,
                    'message' => "无权修改员工档案敏感字段: {$f} (仅本人或系统管理员可改)",
                ], 403);
            }
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::with([
            'department:id,name',
            'position:id,name,department_id',
            'profile',
            'profile.skills:id,name,color',
            'roles:id,name,description'
        ]);
        // 默认只显示未禁用的，除非传了 status
        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'], true)) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }
        // V1.1 admin 隔离: 默认排除 system 用户。
        // system 用户 (含 admin) 主动传 include_system=1 才能看到 (用于 userType=system 的管理端)
        if (!$request->boolean('include_system')) {
            $query->where(function ($q) {
                $q->whereNull('is_system')->orWhere('is_system', false);
            });
        }
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('username', 'like', "%{$kw}%")
                  ->orWhere('phone', 'like', "%{$kw}%")
                  ->orWhere('email', 'like', "%{$kw}%")
                  ->orWhereHas('profile', function ($pq) use ($kw) {
                      $pq->where('employee_no', 'like', "%{$kw}%");
                  });
            });
        }
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('position_id'))   $query->where('position_id', $request->position_id);
        $list = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);
        $viewer = $request->user();
        // 加 is_active 字段（前端习惯用）+ 把 profile.skills 平铺到顶层 skills
        $list->getCollection()->transform(function ($u) use ($viewer) {
            $statusValue = $u->status instanceof \BackedEnum ? $u->status->value : $u->status;
            $u->is_active = $statusValue === 'active';
            $u->role_id = $u->roles->first()?->id;
            $u->roles->each(function ($role) {
                $role->display_name = $this->roleDisplayName($role);
            });
            // V1.2.3: 透出 user_type 给前端, 方便管理员判断"业务账号 vs system 账号"
            $u->user_type = $u->is_system ? 'system' : 'business';
            // 把 profile.skills 平铺为顶层 skills 数组（前端表格要用）
            $u->skills = $u->profile && $u->profile->skills ? $u->profile->skills->map(fn($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'color'       => $s->color,
                'pivot'       => $s->pivot ? ['proficiency' => $s->pivot->proficiency] : null,
            ]) : [];
            // P1-11: profile 字段裁剪 (自己 / system_admin 可见完整)
            if ($u->profile) {
                $fullAccess = $this->canViewFullProfile($viewer, $u);
                $u->profile->setRawAttributes(
                    array_merge($u->profile->getAttributes(), (new EmployeeProfileResource($u->profile))->withFullAccess($fullAccess)->toArray(request()))
                );
                // 不暴露他人 id_card
                if (!$fullAccess && isset($u->id_card)) {
                    $u->id_card = null;
                }
            }
            return $u;
        });
        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $generatedPassword = false;
        if (empty($data['password'])) {
            $data['password'] = TemporaryPassword::generate();
            $data['must_change_password'] = true;
            $generatedPassword = true;
        }
        // 不要手动 bcrypt — User 模型 casts 已配 'password' => 'hashed' 自动加密
        $data['status'] = 'active';
        // phone NOT NULL UNIQUE — 不填时生成唯一占位 (限20字符), 避免空串 UNIQUE 冲突
        if (empty($data['phone'])) {
            $data['phone'] = mb_substr('TEL-' . ($data['username'] ?? 'user') . '-' . bin2hex(random_bytes(2)), 0, 20);
        }
        // email nullable UNIQUE — 空串会冲突, 不填时从 data 移除让 DB 用 null 默认值
        if (empty($data['email'] ?? null)) {
            unset($data['email']);
        }
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);
        $hireDate = $data['hire_date'] ?? null;
        unset($data['hire_date']);

        $user = User::create($data);
        if ($roleId) {
            $user->roles()->sync([$roleId]);
        }
        // employee_profiles.hire_date / base_salary / salary_allowance / contract_type 都是 NOT NULL
        $user->profile()->create([
            'hire_date'        => $hireDate ?? now()->format('Y-m-d'),
            'employee_no'      => 'EMP' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
            'contract_type'    => 'open',
            'base_salary'      => 0,
            'salary_allowance' => 0,
        ]);
        $user->load(['department', 'position', 'profile', 'roles']);
        $this->clearOrgCache();
        return response()->json([
            'code' => 0,
            'message' => '员工已创建',
            'data' => $user,
            'temporary_password' => $generatedPassword ? $data['password'] : null,
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $user->load(['department', 'position', 'profile', 'profile.certificates', 'profile.skills:id,name,color', 'roles:id,name,description']);
        $statusValue = $user->status instanceof \BackedEnum ? $user->status->value : $user->status;
        $user->is_active = $statusValue === 'active';
        $user->role_id = $user->roles->first()?->id;
        $user->roles->each(function ($role) {
            $role->display_name = $this->roleDisplayName($role);
        });
        $user->skills = $user->profile && $user->profile->skills ? $user->profile->skills->map(fn($s) => [
            'id'    => $s->id,
            'name'  => $s->name,
            'color' => $s->color,
        ]) : [];
        // P1-11: profile 字段裁剪
        if ($user->profile) {
            $fullAccess = $this->canViewFullProfile($request->user(), $user);
            $user->profile->setRawAttributes(
                array_merge($user->profile->getAttributes(), (new EmployeeProfileResource($user->profile))->withFullAccess($fullAccess)->toArray($request))
            );
            if (!$fullAccess) {
                $user->id_card = null;
            }
        }
        return response()->json(['code' => 0, 'data' => $user]);
    }

    public function update(UpdateEmployeeRequest $request, User $user): JsonResponse
    {
        // P0-1 安全修复: 只有 system 账号可以改别人的 roles/role_id (防业务用户自封 admin)
        // 也防止业务管理员把自己同事改成 admin
        $operator = $request->user();
        if (!$operator || $operator->user_type !== 'system') {
            // 抹掉 role_id 字段, 不让业务用户把自己/同事封成 admin
            if ($request->filled('role_id')) {
                return response()->json(['code' => 1003, 'message' => '只有 system 账号可以修改用户角色'], 403);
            }
        }
        // P1-11: 禁止业务用户改他人薪资/银行卡/紧急联系人 (只允许本人或 system_admin)
        $denied = $this->assertCanUpdateSensitiveFields($request, $user);
        if ($denied) return $denied;

        $data = $request->validated();
        $shouldSyncRole = array_key_exists('role_id', $data);
        $roleId = $shouldSyncRole ? $data['role_id'] : null;
        if ($shouldSyncRole) {
            unset($data['role_id']);
        }
        $hireDate = null;
        if (array_key_exists('hire_date', $data)) {
            $hireDate = $data['hire_date'];
            unset($data['hire_date']);
        }
        if (array_key_exists('is_active', $data)) {
            $data['status'] = $data['is_active'] ? 'active' : 'inactive';
            unset($data['is_active']);
        }
        $user->fill($data)->save();
        if ($shouldSyncRole) {
            $user->roles()->sync($roleId ? [$roleId] : []);
        }
        if ($hireDate !== null) {
            $profile = $user->profile ?: $user->profile()->create(['employee_no' => 'EMP' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT)]);
            $profile->hire_date = $hireDate;
            $profile->save();
        }
        $user->load(['department', 'position', 'profile', 'roles']);
        $this->clearOrgCache();
        return response()->json(['code' => 0, 'message' => '员工已更新', 'data' => $user]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === 1) {
            return response()->json(['code' => 1001, 'message' => '超级管理员不能删除'], 422);
        }
        $user->update(['status' => 'inactive']);
        $this->clearOrgCache();
        // 撤销所有 token 强制下线
        $user->tokens()->delete();
        return response()->json(['code' => 0, 'message' => '员工已离职']);
    }

    /**
     * 重置员工密码 — POST /api/users/{user}/reset-password
     * 未指定时生成一次性随机密码并强制下次登录修改
     * P0-1 安全修复: 只允许 system 账号调用, 防止业务用户改他人密码
     */
    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        // P0-1 安全修复: 只允许 system 账号重置任意用户密码
        $operator = $request->user();
        if (!$operator || $operator->user_type !== 'system') {
            return response()->json(['code' => 1003, 'message' => '只有 system 账号可以重置密码'], 403);
        }
        $data = $request->validated();
        $newPwd = $data['password'] ?? TemporaryPassword::generate();
        $user->update(['password' => $newPwd, 'must_change_password' => true]);
        // 撤销 token 强制重新登录
        $user->tokens()->delete();
        return response()->json([
            'code'    => 0,
            'message' => '密码已重置',
            'data'    => ['id' => $user->id, 'username' => $user->username, 'password' => $newPwd],
        ]);
    }

    // =================== 部门管理 ===================

    /**
     * 部门列表（整棵树 — 平铺给前端）
     * GET /api/employees/departments
     */
    public function departments(Request $request): JsonResponse
    {
        $depts = CacheHelper::remember('departments:all', 300, ['org'], function () {
            $activeBusinessCounts = User::query()
                ->select('department_id', \DB::raw('COUNT(*) as cnt'))
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('is_system')->orWhere('is_system', false);
                })
                ->whereNotNull('department_id')
                ->groupBy('department_id')
                ->pluck('cnt', 'department_id');

            return Department::with(['children', 'manager:id,name,username'])
                ->orderBy('sort_order')
                ->get()
                ->map(function (Department $d) use ($activeBusinessCounts) {
                    $count = (int) ($activeBusinessCounts[$d->id] ?? 0);
                    foreach ($d->children as $c) {
                        $count += (int) ($activeBusinessCounts[$c->id] ?? 0);
                    }
                    return [
                        'id'          => $d->id,
                        'name'        => $d->name,
                        'parent_id'   => $d->parent_id,
                        'manager_id'  => $d->manager_id,
                        'manager'     => $d->manager?->name,
                        'sort_order'  => $d->sort_order,
                        'status'      => $d->status,
                        'description' => $d->description ?? '',
                        'count'       => $count,
                    ];
                })->values();
        });
        return response()->json(['code' => 0, 'data' => $depts]);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:64',
            'parent_id'   => 'nullable|exists:departments,id',
            'manager_id'  => 'nullable|exists:users,id',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
            'description' => 'nullable|string|max:255',
        ]);
        $data['status'] = 'active';
        $this->clearOrgCache();
        $dept = Department::create($data);
        return response()->json(['code' => 0, 'message' => '部门已创建', 'data' => $dept]);
    }

    public function updateDepartment(Request $request, Department $department): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:64',
            'parent_id'   => 'sometimes|nullable|exists:departments,id',
            'manager_id'  => 'sometimes|nullable|exists:users,id',
            'sort_order'  => 'sometimes|nullable|integer|min:0|max:9999',
            'description' => 'sometimes|nullable|string|max:255',
        ]);
        // 防呆：不能把 parent 设为自己的子孙
        if (! empty($data['parent_id'])) {
            $cur = Department::find($data['parent_id']);
            while ($cur) {
                if ($cur->id === $department->id) {
                    return response()->json(['code' => 1001, 'message' => '不能将部门的上级设为自己或其子孙'], 422);
                }
                $cur = $cur->parent;
            }
        }
        $this->clearOrgCache();
        $department->fill($data)->save();
        return response()->json(['code' => 0, 'message' => '部门已更新', 'data' => $department]);
    }

    public function destroyDepartment(Department $department): JsonResponse
    {
        if ($department->id === 1) {
            return response()->json(['code' => 1001, 'message' => '根部门不能删除'], 422);
        }
        $childCnt = Department::where('parent_id', $department->id)->count();
        if ($childCnt > 0) {
            return response()->json(['code' => 1002, 'message' => "部门「{$department->name}」下还有 {$childCnt} 个子部门，请先处理"], 422);
        }
        $userCnt = User::where('department_id', $department->id)->count();
        if ($userCnt > 0) {
            return response()->json(['code' => 1003, 'message' => "部门「{$department->name}」下还有 {$userCnt} 名员工，请先调整"], 422);
        }
        $this->clearOrgCache();
        $department->positions()->delete();
        $department->delete();
        return response()->json(['code' => 0, 'message' => '部门已删除']);
    }

    // =================== 岗位管理 ===================

    public function positions(Request $request): JsonResponse
    {
        $positions = CacheHelper::remember('positions:all', 300, ['org'], function () {
            return Position::with('department:id,name')->withCount('users')->orderBy('sort_order')->get()
                ->map(function (Position $p) {
                    return [
                        'id'            => $p->id,
                        'name'          => $p->name,
                        'department_id' => $p->department_id,
                        'department'    => $p->department?->name,
                        'level'         => $p->level,
                        'count'         => $p->users_count,
                        'description'   => $p->description,
                        'sort_order'    => $p->sort_order,
                        'status'        => $p->status,
                    ];
                });
        });
        return response()->json(['code' => 0, 'data' => $positions]);
    }

    public function storePosition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:64',
            'department_id' => 'required|exists:departments,id',
            'level'         => 'nullable|string|max:50',
            'description'   => 'nullable|string|max:255',
            'sort_order'    => 'nullable|integer|min:0|max:9999',
        ]);
        $data['status'] = 'active';
        if (empty($data['level'])) {
            $data['level'] = 'P5';
        }
        Cache::forget('positions:all');
        $pos = Position::create($data);
        return response()->json(['code' => 0, 'message' => '岗位已创建', 'data' => $pos]);
    }

    public function updatePosition(Request $request, Position $position): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'sometimes|required|string|max:64',
            'department_id' => 'sometimes|required|exists:departments,id',
            'level'         => 'sometimes|nullable|string|max:50',
            'description'   => 'sometimes|nullable|string|max:255',
            'sort_order'    => 'sometimes|nullable|integer|min:0|max:9999',
        ]);
        Cache::forget('positions:all');
        $position->fill($data)->save();
        return response()->json(['code' => 0, 'message' => '岗位已更新', 'data' => $position]);
    }

    public function destroyPosition(Position $position): JsonResponse
    {
        $userCnt = User::where('position_id', $position->id)->count();
        if ($userCnt > 0) {
            return response()->json(['code' => 1001, 'message' => "岗位「{$position->name}」下还有 {$userCnt} 名员工，请先调整"], 422);
        }
        Cache::forget('positions:all');
        $position->delete();
        return response()->json(['code' => 0, 'message' => '岗位已删除']);
    }

    // =================== 技能标签 ===================

    public function skills(Request $request): JsonResponse
    {
        $tags = SkillTag::withCount('employees')->orderBy('sort_order')->get();
        return response()->json(['code' => 0, 'data' => $tags]);
    }

    public function storeSkill(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:32|unique:skill_tags',
            'category'    => 'nullable|string|in:install,debug,network,cloud,maintain,other',
            'color'       => 'nullable|string|max:7',
            'description' => 'nullable|string|max:255',
        ]);
        $data['category'] = $data['category'] ?? 'other';
        $data['color']    = $data['color']    ?? '#409EFF';
        $tag = SkillTag::create($data);
        return response()->json(['code' => 0, 'message' => '技能已创建', 'data' => $tag]);
    }

    public function updateSkill(Request $request, SkillTag $skillTag): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:32|unique:skill_tags,name,' . $skillTag->id,
            'category'    => 'sometimes|nullable|string|in:install,debug,network,cloud,maintain,other',
            'color'       => 'sometimes|nullable|string|max:7',
            'description' => 'sometimes|nullable|string|max:255',
        ]);
        $skillTag->fill($data)->save();
        return response()->json(['code' => 0, 'message' => '技能已更新', 'data' => $skillTag]);
    }

    public function destroySkill(SkillTag $skillTag): JsonResponse
    {
        $empCnt = $skillTag->employees()->count();
        if ($empCnt > 0) {
            $skillTag->employees()->detach();
        }
        $skillTag->delete();
        return response()->json([
            'code'    => 0,
            'message' => '技能已删除' . ($empCnt > 0 ? "，已解绑 {$empCnt} 名员工" : ''),
        ]);
    }

    // 给员工绑定技能
    public function attachSkill(Request $request, SkillTag $skillTag): JsonResponse
    {
        $data = $request->validate([
            'user_id'     => 'required|exists:users,id',
            'proficiency' => 'nullable|in:beginner,intermediate,advanced,expert',
        ]);
        $user = User::findOrFail($data['user_id']);
        $proficiency = $data['proficiency'] ?? 'intermediate';
        // 拿到或创建 profile
        $profile = $user->profile ?: $user->profile()->create([
            'employee_no'      => 'EMP' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
            'hire_date'        => now()->format('Y-m-d'),
            'contract_type'    => 'open',
            'base_salary'      => 0,
            'salary_allowance' => 0,
        ]);
        $profile->skills()->syncWithoutDetaching([
            $skillTag->id => ['proficiency' => $proficiency],
        ]);
        return response()->json(['code' => 0, 'message' => '技能已绑定']);
    }

    // 给员工解绑技能
    public function detachSkill(Request $request, SkillTag $skillTag): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        $user = User::findOrFail($data['user_id']);
        $profile = $user->profile;
        if ($profile) {
            $profile->skills()->detach($skillTag->id);
        }
        return response()->json(['code' => 0, 'message' => '技能已解绑']);
    }

    // 列出某员工的技能
    public function userSkills(Request $request, User $user): JsonResponse
    {
        $profile = $user->profile()->with('skills')->first();
        $skills = $profile ? $profile->skills : collect();
        return response()->json(['code' => 0, 'data' => $skills]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,xlsx,xls|max:10240']);
        $file = $request->file('file');
        $success = 0; $failed = 0; $errors = []; $temporaryPasswords = [];
        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            return response()->json(['code' => 1001, 'message' => '请使用 CSV 格式导入'], 422);
        }
        $rows = [];
        if (($h = fopen($file->getRealPath(), 'r')) !== false) {
            $bom = fread($h, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($h);
            while (($r = fgetcsv($h)) !== false) $rows[] = $r;
            fclose($h);
        }
        foreach (array_slice($rows, 1) as $idx => $r) {
            if (empty($r[0]) || empty($r[1])) { $failed++; continue; }
            try {
                $providedPassword = trim($r[2] ?? '');
                if ($providedPassword !== '' && !preg_match('/^(?=.*[A-Za-z])(?=.*\d).{12,64}$/', $providedPassword)) {
                    throw new \RuntimeException('密码必须为 12-64 位且同时包含字母和数字');
                }
                $temporaryPassword = $providedPassword !== '' ? $providedPassword : TemporaryPassword::generate();
                $user = \Illuminate\Support\Facades\DB::transaction(function () use ($r, $temporaryPassword) {
                    $created = User::create([
                        'name'          => trim($r[0]),
                        'username'      => trim($r[1]),
                        'password'      => $temporaryPassword,
                        'must_change_password' => true,
                        'phone'         => !empty(trim($r[5] ?? '')) ? trim($r[5]) : ('TEL-IMPORT-' . bin2hex(random_bytes(3))),
                        'email'         => $r[6] ?? null,
                        'department_id' => is_numeric($r[3] ?? null) ? (int) $r[3] : null,
                        'position_id'   => is_numeric($r[4] ?? null) ? (int) $r[4] : null,
                        'status'        => 'active',
                    ]);
                    $created->profile()->create([
                        'hire_date'        => !empty($r[7]) ? $r[7] : now()->format('Y-m-d'),
                        'employee_no'      => 'EMP' . str_pad((string) $created->id, 5, '0', STR_PAD_LEFT),
                        'contract_type'    => 'open',
                        'base_salary'      => 0,
                        'salary_allowance' => 0,
                    ]);
                    return $created;
                });
                if ($providedPassword === '') {
                    $temporaryPasswords[] = ['username' => $user->username, 'password' => $temporaryPassword];
                }
                $success++;
            } catch (\Throwable $e) {
                \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
                $failed++;
                $errors[] = "第 " . ($idx + 2) . " 行: " . $e->getMessage();
            }
        }
        return response()->json(['code' => 0, 'data' => [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'temporary_passwords' => $temporaryPasswords,
        ]]);
    }

    // =================== 证书 ===================

    public function certificates(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => Certificate::with('profile.user')->orderBy('expire_date')->get()]);
    }

    private function roleDisplayName($role): string
    {
        $map = [
            'system_admin' => '系统管理员',
            'admin' => '业务管理员',
            'manager' => '部门经理',
            'finance' => '财务',
            'user' => '普通员工',
            'sales_manager' => '销售经理',
        ];

        return $map[$role->name] ?? ($role->description ?: $role->name);
    }

    private function clearOrgCache(): void
    {
        CacheHelper::forget('departments:all');
        CacheHelper::flushTag('org');
        Cache::forget('departments:all');
        Cache::forget('positions:all');
    }
}
