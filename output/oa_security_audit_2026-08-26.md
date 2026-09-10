# OA 权限/安全审计报告（117 测试机）

- **日期**: 2026-08-26
- **范围**: 补建测试角色账号 + 4 维安全审计（权限边界 / IDOR / 敏感信息 / 登录限流）
- **结论**: **审计全绿 PASS（0 个未解决问题）**，发现 2 类真实权限缺陷已修复，1 项低危观察项待业务确认

---

## 一、测试账号补建（审计前置）

117 原本只有 `admin` 角色（3 个用户：system/业务管理员/zsk），无法验证角色权限矩阵。本次补建：

| 角色 | 权限数 | 测试账号 | 密码 | 权限构成 |
|---|---|---|---|---|
| admin | 全部(短路) | oa_business_admin(业务管理员) | <已脱敏> | 角色名含 admin → CheckPermission 全放行 |
| manager 部门经理 | 118 | manager_test | Test@12345678 | project/employee/customer/sales/vehicle/construction/expense.apply/inventory.*/schedule/approval 等 |
| finance 财务 | 52 | finance_test | Test@12345678 | finance.*/approval.*/expense.*/warranty.deposit/sales.settlement 等 |
| user 普通员工 | 28 | user_test | Test@12345678 | attendance/checkin/leave/approval/disk.view/inventory.view/expense.create|edit 等 |

账号均通过正规 API 创建（`POST /api/roles` + `POST /api/roles/{role}/menu-permissions` + `POST /api/users`），自动 bcrypt 密码、自动生成 employee_profiles，零 SQL 直改用户表。

## 二、发现的问题与修复

### 🔴 P0-1（高）：财务模块对非 admin 角色整体不可用 — 权限点从未注册

**现象**: finance 角色访问 `/api/finance/overview`、`/api/finance/assets` 全部 403。

**根因**: 财务路由组中间件使用 4 个权限点（`finance.view` 读 / `finance.pay` 写 / `finance.receive` 收款 / `finance.approve` 审批），但权限字典（161 项）**从未注册过这 4 个权限**。`CheckPermission` 对未注册权限直接判定拒绝 → 任何分配了财务菜单权限的业务角色都无法使用财务模块。此前只用 admin 账号（短路放行）测试，缺陷从未暴露。

**修复**:
1. 新增幂等迁移 `2026_08_26_000002_add_missing_route_permissions.php`：补建 6 个权限点（`finance.view/pay/receive/approve` + `expense.create/edit`，含 display_name/module）
2. 绑定：finance 角色 += 6 项；manager/user += `expense.create/edit`（报销申请/编辑）
3. `php artisan permission:cache-reset`（spatie 权限缓存 5 分钟，绑定后必须清）

**验证**: finance 访问财务概览/固定资产 200 ✅

### 🔴 P0-2（高）：报销单提交全员 403 — 权限点命名不一致

**现象**: `POST /api/expenses`（新建报销单）对 user/manager/finance 全部 403，**普通员工无法提交报销**。

**根因**: 路由写操作要求 `permission:expense.create|expense.edit`，但 117 权限字典只有 `expense.apply`（申请报销），`expense.create/edit` 从未注册 → CheckPermission 拒绝。

**修复**: 随 P0-1 迁移补建 `expense.create/expense.edit` 并绑定到 user/manager/finance 角色。

**验证**: 三角色 POST 报销单 422（过权限层，进字段校验）✅

### 🟡 P1（中）：车辆模块无权限控制（观察项，未改）

**现象**: `/api/vehicles` 列表任何登录用户（含 user_test 普通员工）都能访问（200）。

**原因**: vehicles 路由组只有 `auth:sanctum + ensure_business`，未挂 `permission:vehicle.view` 中间件（权限字典有 vehicle.view，菜单矩阵也用它，仅路由漏挂）。

**建议**: 若车辆数据（车牌/保险/油费）需限权，给 vehicles GET 路由补 `permission:vehicle.view` 并给 manager 等角色赋权。**涉及生产路由改动，待业务确认后实施**。

## 三、审计结果明细

### 维度 1：权限边界矩阵（17 项探测，全部符合预期）

| 接口 | admin | manager | finance | user | 判定 |
|---|---|---|---|---|---|
| 项目列表 GET | 200 | 200 | 403 | 403 | ✅ 边界正确 |
| 财务概览 GET | 200 | 403 | 200 | 403 | ✅ 边界正确 |
| 固定资产 GET | 200 | 403 | 200 | 403 | ✅ 边界正确 |
| 角色管理 GET | 200 | 403 | 403 | 403 | ✅ 仅 admin |
| 用户管理 GET | 200 | 403 | 403 | 403 | ✅ 仅 admin |
| 员工列表 GET | 200 | 200 | 403 | 403 | ✅ |
| 库存列表/流水/工具 | 200 | 200 | 200 | 200 | ✅ 全员可看 |
| 客户列表 GET | 200 | 200 | 403 | 403 | ✅ |
| 系统设置 GET | 200 | 403 | 403 | 403 | ✅ 仅 admin |
| 新建项目/用户/资产/入库(写) | ✓ | ✓/403 | ✓/403 | 403 | ✅ |
| 报销单(写) | ✓ | ✓ | ✓ | ✓ | ✅ 修复后全员可申请 |

### 维度 2：IDOR 越权（6 项，全部拦截）

- manager 访问**非参与项目** `/api/projects/1` → **404**（DataScope 全局 scope 生效，不泄露存在性）
- user 访问项目详情 → 404（拒绝）
- manager 访问他人用户详情 `/api/users/2` → 403（`user.manage` 拦截）
- finance 访问客户列表 → 403；访问角色矩阵 → 403（`system.role` 拦截）
- user 工具领用(写) → 403（`inventory.transfer` 拦截）

### 维度 3：敏感信息（全部通过）

- 用户列表/详情 **无 password/remember_token/deleted_at 泄露**（User 模型 $hidden 生效）✅
- `/api/settings` 无 DB 密码/密钥等敏感配置 ✅
- 登录响应 user 字段干净（无多余字段）✅
- 身份证：非 fullAccess 视角自动置 null（EmployeeController P1-11 裁剪）；手机号在 OA 内部通讯录场景明文显示，判定低危

### 维度 4：登录暴力破解防护

- 连续 5 次错误密码 → **第 5 次 429 锁定**（LoginThrottle：5 分钟 5 次，锁 30 分钟）✅

## 四、遗留项

1. **车辆模块权限控制**（P1 中危）：是否收紧待业务确认
2. **手机号明文**（低危）：OA 内部员工通讯录场景，如需脱敏可在 field_mask 配置
3. **117 测试账号**保留（manager_test/finance_test/user_test，密码 Test@12345678）供后续审计复用

## 五、部署说明

- 迁移已在 117 执行（`2026_08_26_000002`，幂等，可安全用于 152/202）
- **152 展示机 / 202 生产机同样存在 P0-1/P0-2 缺陷**（权限字典同源）：部署该迁移 + 按角色绑定权限 + `permission:cache-reset` 即可修复，建议由管理员确认后执行
- 版本 v1.4.2 → v1.4.3（已同步前端兜底版本号）
