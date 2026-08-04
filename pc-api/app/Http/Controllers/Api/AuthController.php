<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            // V1.2.4: system 首次登录密码可空 (wipeAll 后 password=null)
            'password' => 'nullable|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (! $user) {
            return response()->json(['code' => 401, 'message' => '用户名或密码错误'], 401);
        }

        // V1.2.7 P0-2 fix: 删空密码登录（之前 system 空密码可 bypass Hash::check 直接发 token）
        // system 刚 wipeAll（password=null）时拒绝登录，走重置密码流程
        if (empty($user->password)) {
            if ($user->is_system) {
                return response()->json(['code' => 401, 'message' => 'system 密码未设置, 请先通过部署脚本重置'], 401);
            }
            return response()->json(['code' => 401, 'message' => '密码未设置'], 401);
        }
        if (! \Hash::check($request->password, $user->password)) {
            return response()->json(['code' => 401, 'message' => '用户名或密码错误'], 401);
        }

        // 检查账号状态（兼容枚举类型）
        $status = $user->status;
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        } elseif (is_object($status) && method_exists($status, 'value')) {
            $status = $status->value();
        }
        if ($status !== 'active') {
            return response()->json(['code' => 403, 'message' => '账号已被禁用'], 403);
        }

        $token = $user->createToken('oa-token')->plainTextToken;
        $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        // 记录登录日志
        DB::table('system_logs')->insert([
            'user_id' => $user->id, 'type' => 'login', 'module' => 'auth',
            'action' => 'login', 'description' => '用户登录',
            'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // V1.2: 检查 system_initialized 标志 (system 账号登录后)
        $systemInitialized = false;
        try {
            $val = DB::table('system_settings')->where('key', 'system_initialized')->value('value');
            $systemInitialized = ($val === 'true');
        } catch (\Throwable $e) {
            // table 还不存在, 默认未初始化
        }

        return response()->json([
            'code' => 0, 'message' => '登录成功',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
                    'avatar' => $user->avatar, 'phone' => $user->phone, 'email' => $user->email,
                    'department' => $user->department?->name,
                    'position' => $user->position?->name,
                    'user_type' => $user->user_type ?? 'business',
                    'is_system' => (bool) ($user->is_system ?? false),
                    // V1.2: 透出 must_change_password, 前端强制跳 /change-password
                    'must_change_password' => (bool) ($user->must_change_password ?? false),
                ],
                // V1.2: 顶层强约束标记 (前端用)
                'force_change_password' => (bool) ($user->must_change_password ?? false),
                'force_init_wizard'     => $user->user_type === 'system' && ! $systemInitialized,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        DB::table('system_logs')->insert([
            'user_id' => Auth::id(), 'type' => 'logout', 'module' => 'auth',
            'action' => 'logout', 'description' => '用户退出',
            'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request->user()->currentAccessToken()->delete();
        return response()->json(['code' => 0, 'message' => '退出成功']);
    }

    public function userInfo(Request $request): JsonResponse
    {
        $user = $request->user()->load(['department', 'position', 'profile', 'roles']);
        // V1.2: 透出 must_change_password 给前端
        $userPayload = $user->toArray();
        $userPayload['must_change_password'] = (bool) ($user->must_change_password ?? false);
        return response()->json([
            'code' => 0,
            'data' => [
                'user' => $userPayload,
                // 暂时注释掉 permissions 和 roles（避免 Spatie 错误）
                // 'permissions' => $user->getAllPermissions()->pluck('name'),
                // 'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    /**
     * 修改当前用户密码
     * POST /api/auth/change-password
     * body: { oldPassword, newPassword }
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'oldPassword'  => 'required|string',
            'newPassword'  => [
                'required',
                'string',
                'min:12',
                'max:64',
                'different:oldPassword',
                'regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\|`~]{12,64}$/',
            ],
        ], [
            'oldPassword.required' => '原密码必填',
            'newPassword.required' => '新密码必填',
            'newPassword.min'      => '新密码至少 12 位',
            'newPassword.max'      => '新密码最长 64 位',
            'newPassword.different'=> '新密码不能与原密码相同',
            'newPassword.regex'    => '新密码必须 12-64 位, 且同时包含字母和数字',
        ]);

        // 弱密码黑名单
        $weak = ['12345678', '123456789', '1234567890', 'password', 'admin123', 'qwerty', '11111111', '00000000', '87654321', 'abcdefgh'];
        if (in_array(strtolower($request->newPassword), $weak, true)) {
            return response()->json(['code' => 1001, 'message' => '密码过于简单,请使用字母+数字组合'], 422);
        }

        $user = $request->user();
        if (! \Hash::check($request->oldPassword, $user->password)) {
            return response()->json(['code' => 422, 'message' => '原密码错误'], 422);
        }

        $user->password = \Hash::make($request->newPassword);
        // V1.2: 改密后清 must_change_password 标志
        $user->must_change_password = false;
        $user->save();

        // 记录审计
        DB::table('system_logs')->insert([
            'user_id' => $user->id, 'type' => 'security', 'module' => 'auth',
            'action' => 'change_password', 'description' => '用户修改密码',
            'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 撤销当前 token 之外的所有 token（强制其他设备重新登录）
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'code'    => 0,
            'message' => '密码修改成功',
            'data'    => [
                'must_change_password' => false,
            ],
        ]);
    }

    /**
     * 更新当前用户基础资料
     * PUT /api/auth/profile
     * body: { name?, phone?, email?, avatar? }
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name'   => 'sometimes|string|max:50',
            'phone'  => 'sometimes|nullable|string|max:20',
            'email'  => 'sometimes|nullable|email|max:100',
            'avatar' => 'sometimes|nullable|string|max:255',
        ], [
            'name.max'   => '姓名最长 50 字符',
            'email.email'=> '邮箱格式不正确',
            'email.max'  => '邮箱最长 100 字符',
            'phone.max'  => '手机号最长 20 字符',
        ]);

        $user = $request->user();
        $data = array_filter($request->only(['name', 'phone', 'email', 'avatar']), fn($v) => $v !== null);
        if (! empty($data)) {
            $user->update($data);
        }

        // 记录审计
        if (! empty($data)) {
            DB::table('system_logs')->insert([
                'user_id' => $user->id, 'type' => 'update', 'module' => 'auth',
                'action' => 'update_profile', 'description' => '用户更新资料',
                'request_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return response()->json([
            'code' => 0, 'message' => '资料已更新',
            'data' => [
                'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
                'avatar' => $user->avatar, 'phone' => $user->phone, 'email' => $user->email,
                'department' => $user->department?->name,
                'position' => $user->position?->name,
            ],
        ]);
    }
}
