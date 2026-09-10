# Security OA 全库代码审查报告（全面 Review）

> 审查日期：2026-09-09
> 审查方式：静态审读 + 全库模式扫描（grep）+ 与既有安全审计（`output/REVIEW_security-audit.md`，2026-09-06）交叉核验。**只读，未执行运行时验证。**
> 范围：后端 `pc-api/`（Laravel 12，729 个 PHP）、前端 `pc-web/`（Vue 3 + TS，413 vue + 78 ts）、桌面端 `pc-desktop/`、工程与部署（`install.sh`、`oa-docker/`、`oa-baremetal/`、`deploy/`、`hooks/`）、文档与仓库卫生。

---

## 一、结论摘要

Security OA 是一个**工程成熟度明显高于同类自研系统的企业级业务平台**：事务纪律、行级锁、数据权限 scope、审计日志、定时任务与部署脚本均达到可生产水平。上一轮安全审计（2026-09-06）列出的 P0/P1 关键项**绝大部分已修复并经本报告逐条核实**。本报告新发现的**最重要问题在前端权限链路**（按钮级权限整体失效）与**仓库治理**（文档漂移、output/ 目录被 gitignore、deploy/ 残留内网凭据）。

| 维度 | 评价 |
| --- | --- |
| 后端架构 | 优秀：Route→Middleware→FormRequest→Controller→Service→Model 分层清晰，80 Controller / 40 Service / 160+ Model |
| 数据一致性 | 优秀：243 处 `DB::transaction`、268 处 `lockForUpdate`，库存/资金/审批/考勤全部行锁+事务 |
| 权限体系 | 良好但有结构性风险：DataScope（60+ 表规则）+ owns + 手工 assert 三层拼装，未映射表无隔离 |
| 前端权限 | **问题**：v-permission 指令死代码；userStore.hasPermission 恒 false（报销页按钮全员隐藏） |
| 可观测性 | 良好：PerformanceMonitor 三级阈值 + AuditLogger + 审计日志三类 + Horizon |
| 工程化 | 良好：install.sh 规范（TLS/HSTS/CSP/审计/随机凭据）；仓库卫生欠佳 |
| 文档一致性 | **问题**：README 声称 Laravel 11 / V1.4.2，实际 Laravel 12 / v1.4.4 |

---

## 二、架构总览（实测数据）

### 2.1 技术栈（以 composer.lock 为准，与 README 矛盾）

| 项 | README 声称 | 实际（composer.lock / 代码） |
| --- | --- | --- |
| 框架 | Laravel 11 | **laravel/framework v12.64.0** |
| PHP | 8.2 最低 / 8.5 建议 | **>=8.4** |
| Sanctum | — | v4.3.3 |
| spatie/laravel-permission | — | 6.25.0 |
| Horizon | — | v5.48.1 |
| 版本号 | v1.4.2 | `config/oa.php` app_version=**v1.4.4**；最新迁移 2026_09_06 |

### 2.2 代码规模

- 后端：729 PHP 文件；80 个 Api Controller、40 个 Service、160+ Model、约 190 个迁移、17 个中间件、5 个 Job、10 个 Command、约 45 个测试文件
- 前端：413 `.vue` + 78 `.ts`，32 个业务视图域，14 个 API 聚合模块
- 路由按域拆分：auth/attendance/employee/customer/project/sales/purchase/finance/system/analytics/portal

### 2.3 调用边界与关键链路（实测）

- 请求链路：Route → 中间件（ForceJson/HandleCors/throttle/AuditLogger → 业务 auth:sanctum+ensure_business+permission → PerformanceMonitor/EnforcePaginationLimit 收尾）
- 权限四层：路由 `permission:`（103 个表达式）→ `owns` 中间件（12 类资源）→ `DataScope` 全局 scope（60+ 表规则）→ Controller/Service 手工 `assert*`
- 审批：`HandlesApproval` trait 统一列表/详情/转交，`OperationApprovalController`/`ProjectApprovalController`/`FinanceApprovalController` 三类中心
- 定时任务 8 项：报价过期、结算逾期提醒、实际成本同步、施工日志扫描、质保到期、临时角色清理、健康探针、BI 物化视图刷新、合规数据保留（每日 03:30 匿名化离职人员+清理过期日志）

---

## 三、已验证的正面设计（带证据）

1. **事务纪律**：243 处 `DB::transaction`，多表写入（库存流水、资金、审批回写）均包裹事务。
2. **并发控制**：268 处 `lockForUpdate`；`PurchaseFlowService`（L166/304/484/638/745/766 等）、`AssetService`、`AttendanceController`（请假/加班/打卡）、`ExpenseController`、`RepairOrderController` 均在行锁内做状态机判断。
3. **DataScope 数据权限**：`app/Scopes/DataScope.php` 60+ 表规则；匿名用户/后台任务放行，admin/finance/system 放行；被拒访问写 `data_scope_denied` 审计日志（L641-659）。`customers` 规则含 5 种关联可达性（L674-694），`expense_claims` 规则为 `user_id = 本人`（L547-550）。
4. **认证与限流**：登录 `throttle:30,1`（IP）+ `LoginThrottle` 5 次失败锁 30 分钟（账号）；改密 5/min；Sanctum token；登录后写 `system_logs`。
5. **RBAC 护栏（V1.4.3 修复，已核实）**：`RoleController` 对内置角色 `admin/system/system_admin` 只读保护（L76）、非 system 不能授予 admin（L109-112）、不能修改自己角色（L899）、批量授权排除 admin 与自身（L962-965）、临时角色防自授（L1048）。
6. **巡检执行人绑定（V1.4.3 修复，已核实）**：`InspectionService::assertTaskOperator`（L251-263）校验 `assigned_to`，打卡/提交/跳过均绑定执行人；admin/finance/manager 可代操作。
7. **油卡读写分权（V1.4.4 修复，已核实）**：`vehicle.fuel.edit` 权限点经迁移 `2026_09_06_000001_register_fuel_card_edit_permission.php` 注册，路由读写分权。
8. **返修公开查询爆破防护（V1.4.3 修复，已核实）**：`PortalRepairController` 同 IP 失败 5 次锁 15 分钟（L36-108），单号不存在同样计失败防枚举，返回脱敏数据。
9. **前端无 XSS sink**：全库 grep `v-html` 零命中；硬编码 IP/密码在 `pc-web` 仅企业邮箱域名 `mail.example.com`（布局入口）。
10. **install.sh**：TLS 1.2/1.3 + HSTS + CSP + 存储敏感目录 deny（`/storage/(tenders|purchase|external-quotes|repairs)/`）+ `composer audit` + `npm audit` + 随机 DB/管理员密码 + `key:generate` + 部署后删除 `SYSTEM_INIT_PASSWORD`。
11. **SQL 注入面小**：`selectRaw/whereRaw` 均参数化或静态 SQL（DashboardWidget 用 `?` 占位）；用户 ID 拼接均 `%d` 强转；`env()` 直调仅健康检查签名文档。

---

## 四、问题清单（按优先级，均含证据）

### P0 — 影响面大

**P0-1 前端按钮级权限链路整体失效（本报告新发现，需立即确认）**
证据链：
1. `pc-web/src/utils/permission.ts` 定义 `v-permission` 指令并注册（`main.ts:17`），但**全库 413 个 .vue 中零使用**（grep `v-permission` 仅命中 main.ts 与 permission.ts 自身）——整套按钮级权限指令为死代码。
2. `pc-web/src/stores/user.ts:98-100` 的 `hasPermission()` 读取 `userStore.permissions`，该数组**仅**在 `getUserInfoAction`（L70）由 `/auth/userinfo` 返回的 `payload.permissions` 填充；而后端 `AuthController::userInfo` **已注释掉 permissions 字段**（`AuthController.php` L115-117 附近）→ 数组恒为空。
3. `userStore.hasPermission` 无 admin 角色旁路 → 恒返回 false。
4. 实际影响：`views/expense/composables/useExpense.ts`（L254-273）的 `canCreate/canEdit/canDelete/canPay` 全部恒 false → **报销页新建/编辑/删除/付款按钮对包括 admin 在内所有用户隐藏**（后端接口仍正常，属 UI 层失效）。同类模式需全前端排查。
修复建议：统一改用 permission store（`/permissions/my` 正常返回）；恢复 `/auth/userinfo` 返回 permissions 或删除 userStore.hasPermission 改用 `usePermissionStore().hasPermission`；admin 角色旁路。

**P0-2 备份 cron token 无任何写入方，外部备份触发功能为死代码**
证据：全仓 grep `backup_cron_token` 仅 2 处读取——`VerifyBackupCronToken.php:14` 与 `BackupController.php:101`；无任何生成/保存代码（install.sh、SystemSettingsController、命令均未写入）。`POST /api/backups/run-due`（`routes/api.php:63-64`）被 `backup.cron` 中间件拦截，token 为空时中间件直接 403（`VerifyBackupCronToken.php:19`）→ **该端点永远 403，除非手工插库**。另注意中间件与控制器校验逻辑不一致（中间件空 token 拒绝、控制器空 token 放行，`BackupController.php:101-104`）。
修复建议：在备份设置 UI 增加"生成/轮换 token"动作（写 system_settings），或提供 artisan 命令；统一两处校验语义。

**P0-3 README/工程文档与代码事实严重漂移**
证据：README 声称 Laravel 11 + PHP 8.2 + V1.4.2；实际 `composer.lock` 为 Laravel 12.64 + PHP>=8.4，`config/oa.php` 为 v1.4.4，最新迁移 2026_09_06；CHANGELOG 止步 v1.4.2 而代码注释已含 v1.4.3/v1.4.4 修复记录（如 finance.php、CheckPermission.php:25）。`docs/PROJECT_STRUCTURE.md` 的"验证基线"仍指向 v1.4.2 时代。
修复建议：README/CHANGELOG 升版至 v1.4.4；技术栈表格改为 Laravel 12 / PHP 8.4。

### P1 — 高优先级

**P1-1 菜单不按权限过滤，页面可访问性与菜单漂移**（`pc-web/src/layouts/MainLayout.vue:293-299`）
`isVisible` 仅检查 `hidden/hideInMenu/systemOnly/businessOnly`，**不检查 `meta.permission`**。所有业务用户看到全部菜单；点入无 `meta.permission` 的页面后靠后端 403 兜底。与 PROJECT_STRUCTURE 自身原则（"避免页面可访问但菜单不可见的漂移"）相悖（此处为反向漂移：菜单可见但内容拒绝）。

**P1-2 前端多处读取已废弃的 localStorage 旧键**
`auth.ts` 已迁移到 sessionStorage（localStorage 仅做旧数据清理），但至少 9 处视图仍 `localStorage.getItem('oa_user_info')` 或旧键：`views/purchase/Requirement.vue:189`、`views/inventory/MaterialRequest.vue:228`、`OutboundOrder.vue:560`、`InboundOrder.vue:468`、`MaterialReturn.vue:354`、`StockTransfer.vue:323`、`views/finance/Payment.vue:412`、`views/warranty/Deposit.vue:547` → 恒返回 null，这些页面的"当前用户"回退逻辑失效。**`views/disk/index.vue:684` 读取 `localStorage.getItem('token')`（该键从未写入）→ 网盘下载鉴权参数恒为空**，需核实是否导致网盘文件下载失效。

**P1-3 DataScope 未映射表无行级隔离（结构性 IDOR 风险，审计已指出、本报告核实代码）**
`DataScope.php:594-596` `default: return [];`，`apply()` L617 `if (empty($clauses)) return;` → 非 admin 用户对未映射表**完全无 scope 过滤**，依赖 owns 中间件 + Controller 手工 assert。`CheckResourceOwnership.php` 仅在 ownerFields 映射内生效，未命中映射或路由参数非模型对象时**静默放行**（L71-81）。三层皆漏的新接口即裸奔。建议：新增接口强制走统一的 `HandlesDataScope`/显式授权校验，或对未映射表默认 `1=0`（白名单制）。

**P1-4 deploy/ 残留内网凭据（审计 P2-8 未清理）**
- `deploy/deploy.py:113` 仍含 `192.168.1.117 / example`（活动脚本，非归档）
- `deploy/oa-reset-password-tool.py:38-40` 含内网 IP + 用户名
- `deploy/_archive/deploy_152.py:391-457` 明文 `<已脱敏>` 重置密码并打印
- `deploy/_archive/renew_cert_152.py:172` 明文 `<已脱敏>`
修复建议：活动脚本凭据全部改环境变量/密钥文件；归档脚本从版本库删除或脱敏。

**P1-5 审计日志明文记录 PII**
`AuthController::updateProfile`（L212-218）将含新手机号/邮箱的完整 `request_data` 明文写入 `system_logs`，而系统已做敏感字段加密（`User.id_card` encrypted cast）与合规保留策略。`oa:purge-expired-personal-data` 虽清理过期日志，但明文窗口仍在。建议：审计日志对 phone/email/id_card 脱敏后落库。

**P1-6 编号生成双轨并行**
新 `NumberSequenceService`（`number_sequences` 表，迁移 2026_09_03）与旧 `MAX(SUBSTRING(code FROM '...'))` 模式并存：`RepairOrderController:599`、`WorkOrderController:490/502`、`CommencementOrderService:38`、`ExternalConstructionService:39/56`、`RectificationService:78`、`WarrantyServiceOrderService:308` 等仍用旧法（全表正则扫描取最大值，数据量大时慢且并发可撞号）。建议统一迁移到 NumberSequenceService。

### P2 — 中优先级

**P2-1 登录锁定可被利用做账号 DoS**：`LoginThrottle.php` 按用户名锁定（5 次失败锁 30 分钟），无 IP 维度 → 攻击者可对任意已知账号发起 5 次错误尝试锁死 30 分钟。建议失败计数加入 IP 维度或缩短锁定窗口。

**P2-2 CheckPermission 每次请求实时查库**：`resolveCandidates`（L83-103）对含 `|` 或 `.*` 的表达式逐个查 permissions 表（通配展开为 LIKE 查询），绕过了 spatie 权限缓存，高频接口有数据库压力。建议结果按小时缓存 + 变更时失效（已有 `spatie.permission.cache` 失效机制可复用）。

**P2-3 `routes/api/finance.php` 中文注释乱码**：全库唯一乱码文件（UTF-8/GBK 串码，如 `鎶ラ攭绠＄悊`），系合并残留（`.merge_tmp/` 佐证存在手工合并）。影响可维护性，建议重写注释。

**P2-4 空密码登录分支可枚举 system 账号**：`AuthController.php:30-35` 对 system 返回"system 密码未设置, 请先通过部署脚本重置"，非 system 返回"密码未设置" → 可探测 system 账号存在。建议统一文案。

**P2-5 前端 PWA 服务端/客户端策略矛盾**：`main.ts:40-54` 先注销全部 SW 又注册 `/sw.js`（注释称"部署策略已禁用服务端 sw.js"）→ 若服务器 404 则注册失败告警；若被托管则与注销逻辑矛盾。建议二选一并清理 `sw.js` 引用。

**P2-6 `bootstrap/app.php` 手工注册 31 个核心 Provider**（L8-36）：与 Laravel 12 自动发现机制不同，任何新依赖若漏加 Provider 会静默缺失（该文件已注明"bootstrap/cache/services.php 不存在时必须"）。建议核实部署后 `php artisan optimize` 是否正常生成 services.php。

**P2-7 测试基建依赖真实 HTTP**：`tests/E2E/RoleGuardrailApiTest` 等使用 `OA_TEST_USER/OA_TEST_PASS` 直连环境 API，CI 不可移植；部分 E2E 依赖真实浏览器/远程库。建议拆分单元/特性测试（RefreshDatabase 事务回滚）与 E2E 冒烟。

### P3 — 仓库卫生与低优先

**P3-1 `.gitignore` 第 116 行含 4 个非法 UTF-8 字节**（isort 注释行，GBK 乱码残留），导致整文件被部分工具判为 invalid UTF-8。修复：重写该行注释。

**P3-2 README 与 .gitignore 矛盾**：`.gitignore:81` 裸 `output/` 忽略整个目录（无 `!` 例外），而 README 声称 `output/OA_V1.4.2_专项修复验证报告` 是"纳入版本的验证基线"→ **这些报告实际不在版本库**。建议：`output/` 下仅保留报告（`.md`）白名单例外，脚本/截图移入忽略区或 `deploy/tools`。

**P3-3 版本库散落非资产文件**：根目录 `oa-web-dist-*.tar.gz` ×3、`.deploy_*.tar.gz` ×4（均在 gitignore 内但未清理工作区）、空文件 `=`、`pc-web/CUsersMRG.workbuddytmpvite_build.log`、`.vue-tsc*.log`、`.merge_tmp/`（合并残留，未忽略）、`.ruff_cache/`（未忽略）。建议清理工作区并将 `.merge_tmp`、`.ruff_cache`、`CUsers*` 加入 gitignore。

**P3-4 `pc-api/.env` 存在于工作区**（dummy 值，已被 gitignore 覆盖）——建议删除或换成 `.env.example`，避免误传真实凭据。

**P3-5 `oa:seed-admin` 三个演示账号共用同一 `--password`**（`routes/console.php:17-45`）：admin/manager/user 同密码，仅作演示可接受，但建议文档注明演示账号需改密。

---

## 五、与既有安全审计（REVIEW_security-audit.md）的交叉结论

| 审计条目 | 结论 | 证据 |
| --- | --- | --- |
| P0-1 RBAC 无提权护栏 | **已修复** | RoleController V1.4.3 护栏（L76/109/899/962/1048） |
| P0-2 写接口行级保护无强制规范 | 结构性风险仍在 | DataScope default→[]（本报告 P1-3） |
| P1-3 fuel-cards 读写同权 | **已修复** | `vehicle.fuel.edit` 迁移 2026_09_06_000001 + 路由拆分 |
| P1-4 资金/处置权限点混用 | 部分修复，见残留（质保金 deposit.manage 双动作等） | routes/system.php 269-276（release/forfeit 共用） |
| P1-5 用车申请归属 | 审计已更正为"有校验"；本报告复核同意 | VehicleController updateUsageRequest 本人/调度校验 |
| P1-6 巡检/工序动作未绑执行人 | **已修复** | InspectionService::assertTaskOperator L251-263 |
| P1-7 返修公开查询爆破 | **已修复** | PortalRepairController IP 失败锁 |
| P2-8 deploy/ 硬编码凭据 | **未清理** | 本报告 P1-4（deploy.py:113 仍在） |
| 修复记录第 6 节（ExpenseController 纵深、AttendanceController 绕 scope 后手动校验） | **已核实属实** | ExpenseController index/show、AttendanceController:945 |

---

## 六、修复优先级路线图（建议）

1. **本周**：P0-1 前端权限链路（报销页按钮）；P0-2 备份 token 生成入口；P1-1 菜单按权限过滤。
2. **两周内**：P1-3 新增接口默认白名单制；P1-4 deploy/ 凭据清理；P1-5 审计日志 PII 脱敏；P1-6 编号统一。
3. **月度**：P2 各项（登录锁 DoS、权限缓存、乱码注释、Provider 注册核验、测试基建）；P3 仓库卫生批量清理（gitignore 修正 + output/ 白名单 + 工作区清理 + README/CHANGELOG 升版）。

---

## 七、附录：本报告未覆盖/建议后续深挖

- 80 个 Controller 中约 60 个未逐行精读（本报告以安全中间件、DataScope、审批、库存/资金服务、权限注册为深读重点；其余依赖模式扫描+既有审计）。
- 前端 32 个业务域仅架构级审查 + 权限链路深挖；建议按域抽查页面（customer/sales/project/purchase/inventory/finance/attendance）的 API 调用与错误处理。
- `oa-docker/`、`oa-baremetal/` 已核验（见下"第二轮补充"）。

> **勘误（2026-09-09 补充核查后）**：附录曾怀疑存在"同名字段重复迁移"风险——**经逐迁移核验排除**：227 个迁移无重名文件；`suppliers` 表虽在 `2024_01_02_000005` 与 `2026_06_24_110000` 两次出现，但后者为 `Schema::hasTable` 守卫的幂等写法（已存在则 ALTER，未存在才 CREATE，且含 raw ALTER 容错）；`tool_usage_orders` 在 `2026_08_02_000002` 中被刻意 drop（V1.3.4 简化设计，down() 会重建）。迁移层面无真实冲突。

---

## 八、第二轮补充审查（2026-09-09）

本批次深读：InventoryService 出入库与结存、FinanceController 资金核心、LedgerService 台账分摊、SalesService 访问控制、oa-docker/oa-baremetal 部署资产，并对迁移专项、前端解包模式做了系统核查。

### 8.1 新增验证通过项（证据）

1. **库存出入库**（`InventoryService::stockIn` L325 / `stockOut` L478）：单事务 + 逐物料 `lockForUpdate` + 出库前库存充足校验（不足抛错回滚）+ 结存快照写入流水；仓库归属强校验（物料属于其他仓库拒绝入库）。
2. **库存→财务联动上下文校验**（`validateStockFinancialContext` L1575-1631）：现金收付必选账户、只有现金可关联账户、供应商赊购（credit）必带 supplier、客户应收（receivable）必带 customer、往来单位类型与 id 必须成对且存在性校验（match supplier/customer）。
3. **资金账户双重保护**（`lockActiveAccount`/`lockAccountForDebit` L1633-1651）：仅 active 账户可锁；借记前余额校验（`balance + 0.0001 < amount` 拒付，浮点容差）。
4. **supplier_payables.balance 为 PG 生成列**（迁移 2026_06_24_110006 L51-55）：`GENERATED ALWAYS AS (amount - paid_amount) STORED`——无需手动维护，杜绝两字段失步。
5. **台账分摊防超额**（`LedgerService::applySupplierPayment` L264-268）：分摊金额 > 未付余额即拒绝（`+0.0001` 容差）；行锁 + 供应商归属校验。
6. **销售域行级隔离**（`SalesService`）：`applyOwnerScope`（L1040-1053）本人 + 同部门扩展，admin/manager/sales_manager 旁路；`assertOpportunityAccess`/`assertFollowUpTargetAccess`/`assertSettlementOwner` 贯穿写操作；结算付款防重（L910-932：行锁 + 仅 approved + 必须存在已批财务审批记录 + 状态机置 paid）。路由层所有行级动作挂 `owns:opp/quote/...`。
7. **迁移专项**：227 个迁移无重名；两处"双建表"均为幂等守卫（见上勘误）；`2026_09_*` 23 个新迁移覆盖权限注册/私有盘迁移/唯一约束加固，版本活跃且方向正确。

### 8.2 新增发现

| 级别 | 问题 | 证据 |
| --- | --- | --- |
| P2 | **库存出入库无幂等保护**：网络重试/双击重复提交会生成新 record_no 并重复入账/扣减 + 双份财务联动（无唯一键或幂等键约束） | `InventoryService::stockIn/stockOut`（record_no 每次事务内新生成） |
| P2 | **部署资产存在默认凭据面**：oa-docker 与 oa-baremetal 的 README/INSTALL/deploy.sh 大量出现 `admin / <已脱敏>`；`oa-docker/scripts/healthcheck.sh:41` 直接以 admin/<已脱敏> 调登录接口做健康检查；compose 默认 `DB_PASSWORD:-CHANGEME_DB_PASSWORD`（若跳过 gen-env.sh 即弱密码上线）；文档含内网 IP（192.168.1.115/117） | oa-docker/docs/README.md、healthcheck.sh、docker-compose.yml:9/24；oa-baremetal/README.md、deploy.sh:385 |
| P2 | **金额为 float 累加**：入库多行 `$totalAmount += round(qty*cost,2)` 浮点累加后写 decimal 字段，行数多时可能差 0.01 | `InventoryService::stockIn` L374-380 |
| P3 | **前端响应解包每页手写 fallback**：`utils/response.ts` 已提供统一 `unwrapList/unwrapItem`（覆盖 data/data.data/items/rows/list 形态），但 60+ 处调用仍是 `res.data || res` 各写各的，形态不统一易埋 bug | `utils/response.ts:6`（自述"135+ 页面各写各的 fallback"）、`views/vehicle/index.vue:221` 等 |
| 设计注记 | `FinanceController::storePayment`（通用付款，L71-185）对超额应付**吸收为新的已付记录**，而 `LedgerService::applySupplierPayment` 对超额分摊**拒绝**——两套语义并存，需在业务上明确"超额付款是否允许" | FinanceController.php:133-144 vs LedgerService.php:264-268 |
| 设计注记 | `FinancePayment` 表同时承载收款（inventory stockOut cash）与付款（storePayment/expense pay），靠 type/方向字段区分，聚合报表需小心口径 | InventoryService stockOut、FinanceController storePayment |

### 8.3 后续仍可深挖

- 前端 32 域中约 30 个未逐页精读（本报告以权限链路、解包模式、敏感存储为扫描重点）。
- `AttendanceController`（964 行，含打卡/请假/加班复杂状态机）与 `ProjectController` 未逐行精读。
- `config/cors.php`、`config/compliance.php`、Horizon 配置未核验。

---

## 九、第三轮补充审查（考勤/项目/导出/桌面端/hooks）

### 9.1 新增验证通过项（证据）

1. **考勤请假/加班**（`AttendanceController`）：天数与起止日期强校验（单日仅 0.5/1 天，L495-500）；重叠请假检查在事务+申请人行锁内（L506-519）；请假与审批中心记录同事务创建（L505-562）；审批走 `ApprovalFlowService->advanceFlow`，重复处理被状态+锁拦截（L576-590）；打卡/签退/外勤打卡全行锁 + 重复打卡拦截（L163-238）。
2. **项目访问控制闭环**：`DataScope.php:36-40` 含 `projects` 规则（manager OR 活跃成员），路由模型绑定 `{project}` 会应用全局作用域 → 详情/更新/删除天然行级隔离；列表另有 `project.view.own` 的 manager/成员过滤（`ProjectController.php:43-47`）；项目组路由挂 `field_mask` 中间件（`routes/api/project.php:16`）；创建项目同事务落成员+审批流+网盘目录（L90-138）。
3. **全库无调试残留**：`dd(/dump(/var_dump/print_r(` 在 app/ 零命中。
4. **无 SQL 拼接注入面**：`whereRaw/selectRaw/orderByRaw/havingRaw` 与 `$` 拼接零命中。
5. **导出安全**：`exporter.ts::printTable` 全量 HTML 转义（esc()）；`printHtml` 未转义但全库无调用点（非可利用 sink）；`QuoteExportDialog.vue:66` 注释证明已修复过同源 XSS 问题。
6. **提交前钩子体系**（hooks/）：`check_secrets.py`（AWS/GitHub/OpenAI/私钥/password 赋值/Bearer/高熵串）+ official pre-commit 钩子（大文件/大小写冲突/合并残留/JSON/YAML/私钥/空白）。

### 9.2 新增发现

| 级别 | 问题 | 证据 |
| --- | --- | --- |
| P2 | **密钥扫描钩子不覆盖内网 IP/用户名组合**：`deploy/deploy.py:113` 的 `example@192.168.1.117`、`oa-reset-password-tool.py:38-40` 的内网地址均不在 PATTERNS 内（仅匹配带 password/token 等关键字的赋值）——裸内网地址+运维账号是最常见泄露形态却查不出；`_archive/` 的 `<已脱敏>` 亦属历史遗留 | hooks/check_secrets.py L34-62 |
| P3 | **cors 默认值硬编码内网 IP**：`config/cors.php:24` 默认 `http://192.168.1.20`（未设 CORS_ALLOWED_ORIGINS 的新部署会继承该内网地址，可能与实际部署地址不符导致跨域问题） | config/cors.php:22-25 |
| P3 | **缓存注释与代码不一致**：`ProjectController.php:52` 注释"60s TTL"实际 `Cache::put(..., 30)` | ProjectController.php:54 |
| 注记 | **pc-desktop 为空脚手架**：工作区仅检出 electron-vite 配置 + package.json（`oa-security-desktop` v1.0.0）+ node_modules，**无 src/ 源码**，Electron 主进程/预加载逻辑无法在本次审查覆盖；建议确认该目录是否应包含在交付物中 | pc-desktop/ 目录清单 |
| 正面 | pre-commit 体系、零调试残留、零 SQL 拼接、导出转义——均优于同类项目平均水平 | 见 9.1 |

### 9.3 审查总体覆盖度（最终）

- 后端：17 个中间件全读；Auth/DataScope/审批/RBAC/调度/Job 全读；库存/资金/销售/考勤/项目五大服务与控制核验；`dd`/SQL 拼接/凭据/编码全库扫描。**80 控制器中约 70 个未逐行精读**，但均覆盖：路由权限挂载（routes/api/* 全量）、DataScope 归属、行锁模式、状态机骨架。
- 前端：架构/权限链路/存储/解包/XSS/导出全核查；32 域页面未逐页精读（以模式扫描兜底）。
- 工程：install.sh、oa-docker、oa-baremetal、deploy/、hooks/、pc-desktop 均核验；文档漂移与仓库卫生全量盘点。
- 测试：结构评估（单元/特性/E2E 分层，E2E 依赖真实 HTTP）。
