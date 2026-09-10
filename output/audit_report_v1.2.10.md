# OA 系统代码审计报告 V1.2.10

> 审计日期: 2026-07-07 18:30
> 范围: pc-api (617 PHP) + pc-web (364 Vue + 2792 TS)
> 维度: 后端安全 / 后端质量 / 前端质量 / 架构一致性
> 方法: 4 个并行扫描 agent，聚焦 P0/P1 严重问题

---

## 一、问题汇总

| 维度 | P0 | P1 | 合计 |
|---|---|---|---|
| 后端安全 | 0 | 4 | 4 |
| 后端质量 | 4 类 | 3 类 | 7 |
| 前端质量 | 3 类 | 5 类 | 8 |
| 架构一致性 | 2 | 6 | 8 |
| **合计** | **9** | **18** | **27** |

---

## 二、后端安全 (pc-api)

### P1 高风险

**1. 硬编码弱默认密码**
- `database/migrations/2026_06_27_150000_v12_system_init_and_password.php:61`
- `'password' => Hash::make('<已脱敏>')` — system 超管密码入版本库
- 修复: 改从 `env('SYSTEM_INIT_PASSWORD')` 读取，或随机生成

**2. RepairStepPhoto 删除越权 IDOR**
- `app/Http/Controllers/Api/RepairStepPhotoController.php:118-124`
- `RepairStepPhoto::findOrFail($id)->delete()` 不校验 `uploaded_by`
- 修复: 加 `->where('uploaded_by', $request->user()->id)`

**3. RepairProgressLog 删除越权 IDOR**
- `app/Http/Controllers/Api/RepairProgressLogController.php:47-52`
- `destroy(int $id)` 忽略路径参数 `{repairOrderId}`，不校验工单归属和 `action_by`
- 修复: 签名改 `destroy(int $repairOrderId, int $id)`，加归属校验

**4. RepairOrder 附件删除 IDOR**
- `app/Http/Controllers/Api/RepairOrderController.php:613-619`
- 限工单但未校验 `uploaded_by`，同工单用户可删别人附件
- 修复: 加 `uploaded_by` 校验

### 未发现的问题 ✓
- ✓ SQL 注入 (whereRaw/DB::raw 均用参数绑定)
- ✓ 未授权访问 (业务路由全在 auth:sanctum + ensure_business 内)
- ✓ Mass Assignment (119 Model 全有 $fillable，无 $guarded=[])
- ✓ 硬编码真实密钥 (.env.example 全占位符)
- ✓ 文件上传未校验 (FileUploadService 三重校验)
- ✓ 命令注入 (无 exec/shell_exec)
- ✓ eval / preg_replace /e

---

## 三、后端质量 (pc-api)

### P0 严重

**1. 胖控制器 (14 个 > 400 行)**
| Controller | 行数 |
|---|---|
| DashboardController | **1130** |
| SystemSettingsController | 1041 |
| RoleController | 981 |
| FinanceController | 736 |
| ProjectController | 715 |
| TenderController | 708 |
| AttendanceController | 632 |
| RepairOrderController | 620 |
| ProcessController | 596 |
| EmployeeController | 547 |
| PurchaseFlowController | 531 |
| SetupWizardController | 530 |
| WorkOrderController | 449 |
| ExpenseController | 433 |

修复: 业务逻辑下沉到 Service，Controller 只做参数校验+响应组装

**2. N+1 查询 (2 处)**
- `TenderController.php:171` — foreach 内 `TenderBid::find($e['bid_id'])`，N 条评估 N 次 SQL
  - 修复: `TenderBid::whereIn('id', $bidIds)->get()->keyBy('id')` 预加载
- `ExpenseController.php:151` — foreach 内 `$claim->items()->create($item)` 逐条 INSERT
  - 修复: `$claim->items()->createMany($items)`

**3. Controller 直连 DB::table (9 文件 80+ 处)**
- `DashboardController` 30+ 处 (绕过 Model 丢软删除/casts/事件)
- `AuditController` 11 处 `DB::table('system_logs')`
- `FieldMaskController` 8 处全用 DB::table (无对应 Model)
- `SystemSettingsController` 12 处 (含 insertGetId 写 permissions)
- `SetupWizardController` 13 处批量初始化
- 另: AuthController/BackupController/RoleController/ProjectController/PurchaseFlowController

修复: 为 warranties/field_masks/system_logs 建 Model，审计写入抽到 AuditService

**4. 未处理异常 (空捕获)**
- `Concerns/HandlesApproval.php:75-76` — `catch (\Throwable $e) {}` 吞权限异常静默 fallthrough
  - 修复: 至少 `Log::warning` 记录
- `Services/CacheHelper.php:88/131/186` — 缓存降级可接受，建议加 debug 日志

### P1 应修

**5. FormRequest 形同虚设**
- `app/Http/Requests/` 有 **31 个 FormRequest 文件**，但 **0 个 Controller 注入使用**
- 76 个 Controller 全用 `Request $request` + 内联 `$request->validate()`
- 修复: 路由绑定对应 FormRequest，删除 Controller 内 validate

**6. 魔法数字硬编码 (15+ 处)**
- per_page: 500/50/20 散落各处; limit: 500/200/10
- 修复: 统一 `config/pagination.php`

**7. 重复 paginate 响应封装 (8+ Controller)**
- 各自手拼 `{data, total, current_page}`，字段顺序/命名不统一
- 修复: Trait 提供 `paginateResponse($paginator)` 统一封装

---

## 四、前端质量 (pc-web)

### P0 严重

**1. XSS 风险**
- `src/views/knowledge/index.vue:283-292`
- `ElMessageBox.alert` 用 `dangerouslyUseHTMLString: true` 拼接 `item.file_name`/`item.title` (用户上传文件名)
- 修复: 改 `dangerouslyUseHTMLString: false`，或 escapeHtml

**2. any 滥用 (60+ 文件 > 5 次)**
- Top 10: `disk/index.vue`(28)、`purchase/PurchaseDetail.vue`(27)、`employee/Resignations.vue`(25)、`settings/role/Matrix.vue`(22)、`inventory/index.vue`(22)、`inventory/components/CategoryTree.vue`(21)、`attendance/Schedule.vue`(21)、`settings/Backup.vue`(21)、`approval/finance/Index.vue`(20)、`finance/Receipt.vue`(20)
- 修复: 定义 API 返回 interface，分批替换；CI 加 typecheck 拦截

**3. 超大组件 (9 个 > 600 行)**
| 组件 | 行数 |
|---|---|
| business/tender/Detail.vue | 841 |
| project/Detail.vue | 752 |
| purchase/PurchaseDetail.vue | 740 |
| maintenance/WorkOrderDetail.vue | 653 |
| expense/index.vue | 638 |
| customer/components/detail/EditCustomerDialog.vue | 627 |
| settings/role/Matrix.vue | 607 |
| knowledge/index.vue | 606 |
| employee/Organization.vue | 603 |

修复: 拆分子组件 + composable (如 `useTenderDetail()`)，目标 < 400 行

### P1 应修

**4. console.log 残留 (57 处)**
- Top 5: process/InstanceList(5)、inventory/OutboundOrder(4)、MaterialRequest(4)、InboundOrder(4)、expense/index(4)
- 修复: Vite 配置 `drop_console` 或 ESLint `no-console`

**5. TODO 残留 (2 处真实)**
- `construction/budget/index.vue:231` (预算状态机待补)
- `project/Gantt.vue:203` (甘特图待接后端 API)
- 修复: 转 issue 跟踪

**6. 硬编码 /api/ URL (4 处绕过拦截器)**
- `router/index.ts:649`、`disk/index.vue:477`、`employee/components/FileLink.vue:18`、`portal/Repair.vue:133`
- 修复: 迁入 api 模块封装

**7. 重复解包代码 (100+ 处未迁移)**
- `res?.data ?? res` ~35 处
- `Array.isArray(res)` 三段式 fallback ~70 处 (construction/log 9 次最严重)
- 修复: 批量替换为 `unwrapList(res)` (V1.2.10 helper 已就绪)

### 未发现 ✓
- ✓ v-html 直接使用 (0 处)
- ✓ debugger / 原生 alert / confirm (均为 ElMessageBox 正常用法)

---

## 五、架构一致性

### P0 严重

**1. 权限中间件不统一**
- `customer.php`: `permission:customer.view/create` 细粒度
- `purchase.php`: tenders/purchase-flow/purchase 组**仅 ensure_business**，写操作无 permission 校验
- 修复: 统一 `ensure_business` + `permission:模块.动作` 双层

**2. 路由命名复数单数混用**
- 复数: `customers`、`suppliers`
- 单数: `ledger`、`purchase`、`purchase-flow`、`service`
- 修复: 统一复数 (ledgers/purchases/services) 或 kebab 复数

### P1 应修

**3. 审计日志缺失**
- 仅 AuthController 写 system_logs
- 业务 CRUD (客户/库存/采购/施工) 关键写操作**无审计**
- 修复: Service 层或中间件统一写 system_logs

**4. env() 直调 (4 处，已知踩坑)**
- `Jobs/SlowRequestAlertJob.php:49` `env('SLOW_ALERT_WEBHOOK_URL')`
- `Middleware/EnforcePaginationLimit.php:40,90` `env('OA_MAX_PER_PAGE')`
- `SystemSettingsController.php:29` `env('OA_ALLOW_DESTRUCTIVE_RESET')`
- 修复: 迁移到 `config('oa.xxx')`

**5. date() vs Carbon 混用**
- `AttendanceController.php` 多处 `date('Y-m')` 不走应用时区
- 修复: 统一 `Carbon::now()` / `now()`

**6. Model 表名不一致 (3 处)**
- `ApprovalRecord` → `approval_records_v2` (带版本后缀)
- `ProjectPool` → `project_pool` (单数)
- `RectificationDailyRequired` → `rectification_daily_required` (单数)
- 修复: 统一复数，移除 _v2

**7. 响应格式局部不一致**
- `BackupController.php:136` 用 `code: 404` 当业务 code (约定 0=成功/1/1001=失败)
- 修复: 改 `code: 1001`

**8. 配置硬编码**
- `DashboardController.php:329` 满意度 fallback `4.8`、`:487` `addDays(30)` 预警天数、`:516` `1e8` 金额阈值
- 修复: 抽到 `config('oa.warranty_warn_days')` 等

---

## 六、修复优先级建议

### 立即修 (P0 安全 + XSS)
1. **3 个 IDOR** (RepairStepPhoto/RepairProgressLog/RepairOrder 附件删除越权)
2. **XSS** (knowledge/index.vue dangerouslyUseHTMLString)
3. **硬编码弱密码** (<已脱敏> 入版本库)

### 短期修 (P0 质量)
4. **N+1 查询** (TenderController 评分 + ExpenseController 批量插入)
5. **HandlesApproval 空捕获** (吞权限异常)
6. **权限中间件补齐** (purchase/tender 写操作无 permission)

### 中期重构 (P0 架构)
7. **胖控制器拆分** (DashboardController 1130 行优先)
8. **Controller DB::table 抽 Model** (DashboardController/AuditController/FieldMaskController)
9. **FormRequest 实际挂载** (31 个写了没用)
10. **超大 Vue 组件拆分** (9 个 > 600 行)

### 长期治理 (P1)
11. **any 滥用治理** (60+ 文件，加 typecheck)
12. **重复解包迁移** (100+ 处换 helper)
13. **审计日志补齐** (业务 CRUD 写操作)
14. **env() 直调迁移** (4 处)
15. **路由命名统一**

---

*审计完成，未修改任何代码。修复需大哥确认优先级后逐项推进。*
