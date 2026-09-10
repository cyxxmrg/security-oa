# OA 安防运维系统 · 端到端扫描报告 (oa-e2e-full-scan)

- **扫描时间**：2026-08-02
- **目标环境**：117 测试机 `http://192.168.1.117`（web + api 同源）
- **测试账号**：`oa_business_admin / <已脱敏>`（117 仅此账号有效，其余 6 个账号均 401，符合当前账号状态）
- **扫描方式**：实跑（非静态推断）——API 维度用真实 token 并发请求全部 GET 路由；UI 维度用 Playwright 真机登录并渲染 SPA；Data 维度经 SSH 隧道直连 PostgreSQL `security_oa` 校验表结构与外键；集成维度用 7 个账号实跑登录。
- **版本**：v1.3.x（117 当前部署）

---

## 一、四维度总览（技能格式）

```
[API]   GET 374 实跑 | 200:259  403:14  422:9  400:1  429:2  404:86  500:2
[UI]    登录✅  0 console错误  0 页面异常  0 失败响应  菜单56项(弹窗探测受限)
[DATA]  164表 | 两缺失列已确认 | RBAC(permission_role 38绑定) | FK无孤儿
[INTEG] 7账号登录 | 成功1 失败6(预期) | 权限绑定 38
BUG: 3  |  WARN: 4  |  维度结论: API/UI/DATA/INTEG 基本通过(含3个服务端BUG)
```

> 说明：API 维度 374 条 GET 中，仅 **2 条真实 500**，其余非 200 均为「权限不足(403)/参数校验(422/400)/资源id不存在(404)/限流(429)」等预期结果；UI/Data/集成维度未发现客户端或数据层崩溃。

---

## 二、BUG 详情（3 个，均已定位根因）

### 🔴 BUG-1 `/api/customers/1/devices` → HTTP 500
- **异常**：`SQLSTATE[42703]: Undefined column: column "device_id" does not exist`
  `SQL: select "id","device_id",... from "service_orders" where "customer_id"=1 and "device_id" is not null`
- **根因**：`app/Services/CustomerService.php:294-295` 的 `customerDevices()` 直接查询列 `device_id`，但 `service_orders` 表实际列名为 **`customer_device_id`**（迁移 `2024_01_03_000001` L23；模型 `ServiceOrder` fillable L16、`device()` 关系 L41 均用 `customer_device_id`）。属**代码写错列名**。
- **修复**：将两处 `device_id` 改为 `customer_device_id`（select 与 whereNotNull）。

### 🔴 BUG-2 `/api/projects/payment-calendar` → HTTP 500
- **异常**：`SQLSTATE[42703]: Undefined column: column "type" does not exist`
  `SQL: select * from "finance_payments" where "type"='standalone' and "project_id" is not null`
- **根因**：`app/Http/Controllers/Api/ProjectController.php:350` 拼接 `FinancePayment::where('type','standalone')`，但 `finance_payments` 表**根本没有 `type` 列**（迁移 `2026_06_19_110001` L16-36 未建；模型 `FinancePayment` `$fillable` 也无 `type`）。属**代码引用不存在的列**。
- **修复**：删除 `->where('type','standalone')`；若意图是「独立付款（不挂应收/应付）」，应改为 `->whereNull('receivable_id')->whereNull('payable_id')`。

### 🔴 BUG-3 `/api/portal/t/1` → HTTP 500（公开招标门户，无需登录）
- **异常**：`SQLSTATE[22P02]: Invalid text representation: invalid input syntax for type uuid: "1"`
  `SQL: select * from "tender_projects" where "public_token" = 1`
- **根因**：`app/Http/Controllers/Api/PortalController.php:35 / 78 / 108 / 177` 用 `TenderProject::where('public_token', $token)` 查询，而 `public_token` 是 **UUID 类型**列；路由把 URL 路径段 `1`（非 UUID）直接带入，PostgreSQL 报类型转换失败。属**类型不匹配 + 入口未校验**。
- **修复**：对 `$token` 做 UUID 格式校验（非法直接 404），或用路由约束 `where('token', '[0-9a-f-]{36}')` 让非法值在路由层 404，而非落到查询抛 500；也可包裹 `QueryException` 返回 404。

---

## 三、WARN（需关注，非崩溃级）

| 编号 | 项 | 说明 |
|---|---|---|
| W1 | RBAC 权限覆盖 | `permissions` 共 148 条，仅 **38 条**经自定义 `permission_role` 表绑定到唯一角色；其余 110 条未绑任何角色。功能上当前 3 个用户(2 个在角色内)可正常工作，但需确认是否为「故意未启用」还是「绑定遗漏」。注：`role_has_permissions`(spatie 默认表)为空，本项目实际用 `permission_role` 透视表。 |
| W2 | 9 个非参数 GET 返回 404 | `/api/`(根)、`customer-rfm`、`finance-pnl`、`inventory-aging`、`project-health`、`revenue`、`sales-funnel`、`refresh-status`、`export/pdf`。这些是**无路径参数**的 GET，却 404，疑似：① 这些端点实际是 POST/需 query 参数；② 路由未注册。需逐条核对 `routes/` 确认是否缺失，不建议直接判为 BUG。 |
| W3 | 门户限流 429 | `/api/portal/invitations`、`/api/portal/supplier/info` 返回 429。门户接口有节流中间件，确认阈值是否过严（对外公开接口被限可能影响供应商使用）。 |
| W4 | UI 弹窗探测受限 | 自动化仅验证了「登录 + 首屏渲染 + 全菜单枚举」且无任何客户端报错；因侧边栏子菜单默认折叠，自动点击「新建/新增」弹窗未触发（非产品缺陷，是脚本局限）。建议人工抽查各模块新增弹窗的表单校验与提交。 |

---

## 四、API 维度 404 分类（避免误判）

- **参数型 404（预期，非 BUG，占绝大多数）**：形如 `/api/xxx/1` 的资源详情，因 117 测试库近乎为空（projects=0、contracts 相关=0、customers=3 等），id=1 不存在而 404。例如 `projects/1`、`suppliers/1`、`work-orders/1`、`sales/opps/1` 等。
- **权限型 403（预期）**：`/api/admin/monitor/*`、`/api/dict*`、`/api/system/roles`、`/api/setup/*` —— oa_business_admin 无对应权限，返回 403 正确。
- **校验型 422/400（预期）**：`/api/attendance/report`、`/api/schedules`、`/api/step-photos` 等需要必填参数/请求体，空参返回 422/400 正确。
- **真实服务端错误 500（BUG）**：仅 BUG-1、BUG-2 两条（见第二节）。
- **第三方/公开门户 500（BUG）**：BUG-3（UUID 比较）。

---

## 五、Data 维度关键表行数（117 当前）

users=3, roles=1, permissions=148, permission_role=38, model_has_roles=2,
customers=3, projects=0, contract_payment_nodes=0, service_orders=0,
customer_devices=0, finance_payments=0, receivables=0, payables=0,
stock_records=25, tools=3, fixed_assets=6, tender_projects=0,
system_logs=92, audit_logs=105, inventory_items=9

- **缺失列确认**：`service_orders.device_id`=不存在 ✅(对应 BUG-1)、`finance_payments.type`=不存在 ✅(对应 BUG-2)、`service_orders.customer_device_id`=存在（印证 BUG-1 修复方向）。
- **外键孤儿检查**：service_orders/contract_payment_nodes/finance_payments/projects 等主外键参照**无孤儿记录**（contracts 表不存在的误报已排除——ContractPaymentNode 实际关联 `project_contracts`，该表存在）。
- **表总数**：164 张，结构完整，未发现缺失核心表。

---

## 六、结论与整改优先级

- **P0（立即修）**：BUG-1、BUG-2、BUG-3 —— 三个接口在生产库（结构与 117 同源）同样会 500，属确定性的服务端错误，且 BUG-3 暴露在公开门户无需登录即可触发。
- **P1（核对）**：W2 的 9 个 404 端点逐条核对路由定义；W3 门户限流阈值。
- **P2（确认）**：W1 权限覆盖是否遗漏绑定；W4 人工抽查新增弹窗。

> 附：本扫描的自动化脚本位于 `.workbuddy/`：`oa_full_scan.py`(API+集成)、`oa_data_scan.py`(Data)、`oa_ui_scan.py`(UI)，依赖 venv(playwright/psycopg2/requests)。后续可一键复跑。
