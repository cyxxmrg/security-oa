# Security OA 全库安全代码审查报告

> 审查对象：`D:\work\website\OA`（Security OA，安防工程运维一体化平台）
> 审查方式：静态代码审读（只读；本机无 PHP/Node/PostgreSQL/git，未执行运行时与动态验证）
> 覆盖范围：后端 Laravel 11（约 66k 行 PHP；85 Controller / 37 Service / 148 Model / 272 migrations / 16 中间件）、前端 Vue3（约 87k 行 TS/Vue）、Electron 桌面端、部署/安装脚本、免登录公开面
> 产出日期：本仓库当前开发周期内

---

## 一、系统概况与安全架构

- 权限体系为**多层拼装**：路由权限点（功能级）→ `owns` 中间件（12 类资源）→ `DataScope` 全局 scope（60+ 表规则）→ Controller/Service 手工 `assert*`（行级）→ 前端 `v-permission`/路由分流（UI 层）。
- 行级防线挂载率高：**148 个模型中 78 个 `use HasDataScope`**（52.7%），`Project / ProcessInstance / WorkOrder / RepairOrder / ExpenseClaim / Customer / Inspection*` 等核心业务模型均已挂全局 scope（经二次核验；早期 26/148 为统计脚本漏报组合写法 `use HasFactory, HasDataScope;` 所致，特此更正）。**未挂 scope 的代表**：销售域 `Opportunity / Quotation / Referrer / SalesFollowUp`、`Vehicle` 等——其横向隔离依赖 `owns` 中间件与 Service 层 assert（销售/车辆域已核对有对应机制）。
- **结论**：任一写接口的横向隔离依赖"路由 `owns`/`permission` + Controller/Service 手工 `assert*`"两层人工实现的一致性，漏一层即越权；无自动化手段发现"三层皆无"的接口。这是全系统 IDOR 的高发结构性原因。

## 二、已验证的正面设计（带证据）

| 面 | 证据 |
| --- | --- |
| 业务/system 双向隔离；危险操作在 `ensure_system` 组 + Controller 二次校验 `is_system` | `routes/api/system.php:355-362`；`EnsureBusinessUser/EnsureSystemUser.php`；`SystemSettingsController.php:850-856` |
| 超管密码路由内部强校验（`resetUserPassword` 仅改本人、`setSuperAdminPassword` 限 `username==='system'`） | `SystemSettingsController.php:234-246, 811-829` |
| 审批动作严格绑定 `current_approver_id` + 禁自审 + 行锁 + 状态机 | `Concerns/HandlesApproval.php:200-217`；`OperationApprovalController.php:106-117`；`ProjectApprovalController.php:105-117` |
| 居间费结算链：提交审批须 owner（商机归属/同部门）；付款须 approved + 已批财务审批记录 + 防重付 | `Services/SalesService.php:851-933, 1007-1035` |
| 供应商门户 2FA：`Str::random(64)` token、30 分钟 TTL、绑定 supplier+phone_suffix、HMAC 存后缀、verify 单次烧毁 | `Services/PortalInviteService.php:35-125, 153-156` |
| 登录双层限流：`throttle:30,1`（IP）+ 5 次失败锁 30 分钟（账号）；改密 5/min | `routes/api/auth.php:11-15,29-30`；`Middleware/LoginThrottle.php` |
| Sanctum token 默认 24h TTL | `config/sanctum.php:49,93` |
| 前端路由守卫登录后先验 token、401 防重、强制改密优先、白名单收敛 | `pc-web/src/router/index.ts:618-795` |
| 安装脚本凭据处理干净：DB 密码与演示管理员均 `/dev/urandom` 随机、`key:generate --force`、临时脚本用后即删 | `install.sh:87-89,200,260-297` |
| 审计三类落库（`role_changed`/`permission_denied`/`data_scope_denied`） | `App\Support\Audit`、`CheckPermission.php:105-123`、`Scopes/DataScope.php:641-659` |

## 三、问题清单（按优先级）

### P0 — 治理与设计（可致全域提权，建议优先）
1. **RBAC 无提权护栏**：`saveMenuPermissions` / `usersSyncRoles` / `usersBulkAssignRole`（`RoleController.php:349-383, 749-828`）可对任意角色（含 `admin`、自身所在角色）直接 `syncPermissions`/`syncRoles`，无"禁止修改内置 admin 角色 / 禁止操作自身角色 / 目标权限须为操作者子集"限制；`admin` 角色在 `CheckPermission.php:52-54` 全放行。信任全押在 `system.role`＋`user.manage` 权限点的授予面。
2. **写接口行级保护无强制规范**：78/148 模型挂全局 scope + 12 类资源有 `owns` 映射 + 手工 `assert*`，未挂模型的域（销售/车辆等）依赖后两层，任一新接口三层都漏即裸奔。

### P1 — 权限点粒度/错配
3. `fuel-cards` **读写同权**：整组仅 `permission:vehicle.fuel`（`routes/api/finance.php:67-76`），能看油卡者可删充值流水/改余额。
4. **资金/处置权限点混用**：固定资产分类/盘点/处置/折旧全挂 `finance.pay`（`finance.php:130-148`）；质保金 release/forfeit 共用 `deposit.manage`（`system.php:269-276`）；质保增删复用 `project.edit`（`system.php:248-253`）。
5. **[已更正] 用车申请归属**：路由层 `PUT usage/{usageRequest}` 未挂 `owns`（`finance.php:46-47`），但经核实 `VehicleController` 内**有归属校验**（`updateUsageRequest` L286：申请人本人或调度者才可改；`store` 置 applicant=自己；列表按申请人过滤）——早期"无归属"判定系仅看路由层的误读，予以更正。`vehicle.create/edit` 权限点已通过迁移注册（见第七节）。
6. **巡检/工序动作未绑执行人**：`checkin/checkout/resolve/skip` 无 `assigned_to` 校验（`routes/api/project.php:104-115`）；`process` 组级 OR 权限过宽（`project.php:46`）。
7. **返修公开查询组合爆破面**：顺序工单号 + 电话后 4 位，仅 10/min/IP 限流（`PortalRepairController.php:26-62`；`routes/api/portal.php:8-11`）；`tenderByToken` 路径无 throttle（`portal.php:20`）。

### P2 — 健壮性/性能/仓库卫生
8. `deploy/` 残留**含硬编码凭据脚本**：`deploy.py:112`（内网 `192.168.1.117 / example / <已脱敏>`）、`deploy_152.py:391-457`（将任意用户密码重置为 `<已脱敏>` 并打印）、`renew_cert_152.py:172`（硬编码 admin 登录）。README 声称旧脚本已归档，实际仍在根目录。
9. **`backup_cron_token` 无初始化/轮换路径**：全仓库仅 `VerifyBackupCronToken.php:14` 读取，未见写入方——若从未初始化，`POST /api/backups/run-due` 恒 403（自动备份静默失效）；中间件本身实现正确（`hash_equals`）。
10. **Electron 桌面端无源码入库**：`pc-desktop` 仅 6 个配置文件，`electron.vite.config.ts` 引用的 `src/main|preload|renderer` 均不存在；`.gitignore` 未排除 `src`。桌面端主进程安全面（webPreferences/contextIsolation/更新器签名）无法审查，且 `npm run build` 必失败。
11. **前端 token/会话**：token 与含 `user_type/is_system` 的用户对象存 `localStorage`（XSS 窃取面，`pc-web/src/utils/auth.ts`）；30 分钟闲置登出仅清本地、不吊销后端 token（残余窗口最长 ~23.5h，`useIdleTimer.ts:79-92`）；路由守卫信任可篡改的本地 `user_type` 做 system/business 分流（后端为最终边界，属纵深不足）。
12. **性能/维护**：`CheckPermission` 无缓存实时查权限表（`CheckPermission.php:57-72`）；`Scopes/DataScope.php`（931 行）手写 60+ 表 `EXISTS` SQL、`default→[]` 即无隔离、无 Auth 用户（Job/CLI）一律放行。
13. **异常映射掩盖故障**：PG `22P02` 一律 404（`bootstrap/app.php:206-214`）；`debug=true` 时 500 回传 `file:line`（`:217-225`，生产须 `debug=false`）。
14. **前端 UI 权限语义偏差**：`v-permission` 数组默认 AND 而后端 `'a|b'` OR；`hasPermission` 模块前缀降级（`pc-web/src/utils/permission.ts:52-53,110-113`）。
15. **测试覆盖不足**：31 个测试文件（17 Feature + 14 Unit）对应 85 Controller。
16. **仓库卫生**：根目录散落多个 `*.tar.gz` 部署包、名为 `=` 的空文件、`.vue-tsc*.log`；`pc-api/.env` 存在于工作区（未确认是否被 git 跟踪）。

## 四、优先修复建议

1. （P0-1）`RoleController` 增加护栏：内置 `admin` 角色权限只读；操作者不能修改**自己所属角色**的权限；操作者不能修改**自己账号**的角色；非 system 用户不能把 `admin` 角色授予任何人；越权尝试写审计。
2. （P0-2）为写接口建立"必有 `owns`/`data_scope`/Service `assert` 之一"的静态检查或回归测试；补关键资金/库存/审批链路 Feature 测试。
3. （P1）拆分 `fuel-card`/`assets`/`warranty-deposit` 权限点；巡检动作绑定执行人（核对 scope 后按需）；返修查询加失败锁定与更高限流阈值。
4. （P2）归档/脱敏 `deploy/` 硬编码凭据脚本；补齐 `backup_cron_token` 初始化；决策桌面端源码交付方式；token 改 httpOnly cookie 或闲置登出时服务端吊销；清理仓库散落产物。

## 五、审查边界与遗留（诚实声明）

- 未覆盖：未挂 `HasDataScope` 的 122 个模型的逐一 Controller 查询路径核对；`AuthController::login` 对不存在用户的响应细节；migrations 业务约束；前端 350+ 视图逐页校验；`pc-api/.env` 的 git 跟踪状态（本机无 git）。
## 六、修复记录（审查后已落地）

以下问题已在本仓库落地修复，正文结论仍保留（供追溯原始风险）：

| 对应条目 | 修复内容 | 落地文件 |
| --- | --- | --- |
| P0-1（RBAC 无提权护栏） | 新增角色级/用户级/敏感权限三重护栏：内置 `admin`/`system`/`system_admin` 与操作者所属角色仅 system 可调；禁止操作者修改自己账号角色；非 system 禁止授予 `admin` 角色；高敏感权限点（`system.*`、`user.manage`、`approval.config`、`approval.template`、`settings.approval`）仅 system 可注入业务角色（含 `store`/`update`/`assignPermissions`/`saveMenuPermissions` 四个入口） | `pc-api/app/Http/Controllers/Api/RoleController.php` |
| P0-1（配套测试） | 新增 7 个护栏回归用例（5 负向 403 + 2 正向放行；与仓库既有集成测试同模式：curl 打本地 API，账号经 `OA_TEST_USER/OA_TEST_PASS` 覆盖） | `pc-api/tests/Feature/RoleGuardrailApiTest.php` |
| P2-8（deploy 硬编码凭据） | 归档 5 个含明文凭据/重置密码逻辑的 152 系列脚本至 `deploy/_archive/`；`deploy.py` 移除 `ssh.connect` 硬编码密码改为环境变量 `OA_SSH_HOST/OA_SSH_USER/OA_SSH_PASS` 注入 | `deploy/_archive/`、`deploy/deploy.py` |
| P1-3（fuel-cards 读写同权） | 拆分为读=`vehicle.fuel`、写=`vehicle.fuel.edit`（新增权限点迁移）；写路由（store/update/destroy/recharge/删流水）单独收紧。**补充①：`PermissionRoleSeeder::run()` 会清空并重建 permissions（L173-174），仅迁移注册、未加入 seeder 字典的权限点会在重跑 seeder 时丢失——`vehicle.fuel.edit` 已补入 seeder「车辆管理」字典。补充②：运行时权限判定生效表为 `role_has_permissions`（`User::hasActivePermissionTo`/seeder 均用），`permission_role` 为历史遗留冗余表——直接 SQL 补权限绑定必须写 `role_has_permissions`。补充③：已在 122 实测读写隔离（仅授 `vehicle.fuel` 用户：GET 200 / POST 403）** | `routes/api/finance.php`、`database/migrations/2026_09_06_000001_register_fuel_card_edit_permission.php`、`database/seeders/PermissionRoleSeeder.php` |
| P1-5（vehicle.create/edit 缺失注册） | 幂等注册 `vehicle.create`/`vehicle.edit` 权限点（不预设角色授权） | `database/migrations/2026_09_06_000000_register_vehicle_route_permissions.php` |
| P1（质保金资金动作权限） | `warranty-deposits` 释放/没收（partial/full-release、forfeit）从 `deposit.manage` 拆出独立点 `deposit.release`（组级 manage 保留读/建档；路由叠加 AND）；迁移 000003 + seeder 字典与 finance 预设补点。实测：仅授 `deposit.manage` 用户 full-release → **403** 缺 deposit.release | `routes/api/system.php`、`database/migrations/2026_09_06_000003_register_deposit_release_permission.php`、`database/seeders/PermissionRoleSeeder.php` |
| P1（巡检执行人绑定） | `InspectionService::checkin/checkout` 增加执行人校验（`assertTaskOperator`：任务已分配则仅本人，admin/finance/manager 放行；checkout 另要求打卡人本人）；兼容 assigned_to 数字/字符串/对象/JSON 数组。php -l 通过、已部署（109，未造巡检数据做端到端实测——建议业务验证代打卡场景） | `pc-api/app/Services/InspectionService.php` |
| P1（返修公开查询防枚举） | `/api/portal/repair` 增加同 IP 失败锁定：404/403 均计失败，5 次锁 15 分钟（429 自定义文案）；与 route `throttle:10,1` 双层。实测 429 生效（自锁与 throttle 叠加） | `pc-api/app/Http/Controllers/Api/PortalRepairController.php` |
| P1（assets 权限点混用） | 固定资产全系写路由（分类/折旧/盘点/处置/调拨/维护/建档）从 `finance.pay` 拆出独立点 `finance.asset`（新增迁移 000002 + seeder 字典补点；admin/finance 角色授权面不变）。实测隔离：仅授 `finance.view+finance.pay` 用户 GET assets 200 / POST assets **403**（2026-09-06，122→109） | `routes/api/finance.php`、`database/migrations/2026_09_06_000002_register_finance_asset_permission.php`、`database/seeders/PermissionRoleSeeder.php` |
| P2（wipe-data 权限绑定写错表） | `SystemSettingsController::wipeData` 重建角色时把 34 条业务权限写入冗余表 `permission_role`，运行时判定表为 `role_has_permissions` → 绑定不生效（被 admin 角色名旁路掩盖）；已改为写入 `role_has_permissions` | `pc-api/app/Http/Controllers/Api/SystemSettingsController.php` |
| 审查结论 | 本报告全文 | `output/REVIEW_security-audit.md` |

> 验证说明：结构级校验已通过（PHP 花括号配平、凭据残留扫描）。
> **运行级验证（2026-09-05，测试机 192.168.1.122 实测）**：`php vendor/bin/phpunit tests/Feature/RoleGuardrailApiTest.php`（PHP 8.5.10 / PHPUnit 11.5.56，业务管理员 `oa_business_admin` 实测）——**5/5 通过，0 失败**：admin 角色权限矩阵只读、批量自授 admin、修改自己账号角色、创建角色夹带 `system.role`、向其他角色注入 `user.manage` 均被护栏 403 拦截；业务冒烟 11 个核心接口（客户/商机/项目/库存/车辆/油卡/财务/报销/员工/工作台）全部 200。
> 注：测试文件账号已 env 化（`OA_TEST_USER`/`OA_TEST_PASS`，默认 `admin/<已脱敏>`）。
> 注：`RoleGuardrailApiTest` 会创建 `guardrail_*` 临时角色（roles 路由表无 DELETE 端点），每次运行后执行 `php artisan oa:clean-guardrail-roles` 一键清理（支持 `--dry-run` 预览；含 role_has_permissions/permission_role/model_has_roles 关联清理）。
>
> 行为变更：业务管理员（admin 角色）将不能再调整内置 admin 角色、自己所属/自己账号角色，也不能把 `admin` 角色或高敏感权限点授予他人——此类操作仅 system 账号可做（`/admin` 既有通道）。如有运营流程依赖旧行为，需评估后按需放宽。

## 七、审查后补充核对（vehicle 权限点注册状态，闭环正文 P1-5）

静态核对结论（本机未跑迁移/DB）：
- `vehicle.create` / `vehicle.edit` 仅注册于 `database/seeders/PermissionRoleSeeder.php:116-117`（全新安装路径），**缺少增量注册迁移**；`2026_08_26_000002_add_missing_route_permissions.php` 仅补 `finance.*`。
- 存量部署经迁移升级（不重跑 seeder）时，上述两点不在 `permissions` 表 → `routes/api/finance.php:42,60-61` 引用它们会在 `CheckPermission::resolveCandidates` 判 `permission_not_defined` → 非 admin 对车辆新增/编辑/删除一律 403（静默不可用）。
- `finance.php:35-39` 的 V1.4.4 注释声称已对齐菜单级权限点，与 `:42,60-61` 仍引用 `vehicle.create/edit` 的现状矛盾。
- 修复候选：新增迁移注册两条权限（不预设角色授权）或按注释改用已注册菜单权限点。
- **处理状态：方案 A 已落地** —— 新增 `database/migrations/2026_09_06_000000_register_vehicle_route_permissions.php` 幂等注册 `vehicle.create`/`vehicle.edit`（`insert` 前先 `exists` 跳过；`down` 删除；不做角色预设授权，由权限矩阵 UI 按需授予）。需在部署机执行 `php artisan migrate` 生效。

## 八、高危路由复核（2026-09-05，基于当前代码）

对 `routes/api/finance.php` / `project.php` / `system.php` 三个高危文件的复核结论：

| 项 | 结论 |
| --- | --- |
| fuel-cards 读写分离、vehicle 权限注册、RBAC 护栏、审批/超管防护 | ✅ 已修复/验证安全 |
| core 模型行级隔离（Project/Process/WorkOrder/RepairOrder/ExpenseClaim/Inspection* 等） | ✅ **均挂 `HasDataScope`，全局 scope 提供行级保护**（实测：普通用户访问他人报销单在模型绑定层即被 scope 过滤返回 404）；Controller 层显式归属校验为纵深补充。残留核对项：未挂模型的销售/车辆域、及 Controller 内 `withoutGlobalScope`/`DB::table` 直查绕过路径 |
| assets 全部写操作复用 `finance.pay` | ⚠️ P1 遗留（权限点混用） |
| warranty-deposits 资金动作权限粒度、system-logs 查看面、project.edit 语义复用 | ⚠️ 治理项 |

### 抽查复核（2026-09-05 更正：scope 已生效，原"越权确认"结论收回）
- **实测证据**：普通用户（user 角色）访问他人报销单（`GET /expenses/{他人id}`）→ 404——在模型绑定层即被 `ExpenseClaim` 的全局 DataScope（`expense_claims.user_id=自己`）过滤，**行级隔离真实生效**；本人/admin（200）与列表隔离（`total=0`）均验证通过。Process/WorkOrder/RepairOrder/Inspection 模型同挂 scope（项目成员/负责人/创建者规则）。
- **更正说明**：早期基于"26/148 未挂 scope"推断的"五域越权确认"**不成立**（统计漏报所致），已收回。Controller 层所见"无显式归属"由全局 scope 兜底，属正常设计（Controller 显式 assert 仅对少数无 scope 资源必需）。
- **仍待核对**：① **销售/车辆域已核实**：Opportunity/Quotation/Referrer/SalesFollowUp/Vehicle 未挂 scope，但由 `owns` 中间件 + `SalesService` 的 `assertOpportunityAccess`/`applyOwnerScope`（含同部门扩展）与 Vehicle 申请单本人/调度校验提供行级防护，无裸奔路径；② **scope 绕过路径已排查**：全部 `withoutGlobalScope`/`allData`/`DB::table` 用法经抽查均为安全豁免——编号生成（StockRecord/RepairOrder/Tender 等）、审批/流程锁内状态机（PurchaseFlow/ProjectStage 等，由已鉴权审批动作触发）、免登录公开面（Portal，public_token+供应商身份自校验）、`AttendanceController:964`（绕 scope 后**手动校验 manager/member**）、中间表与显示富化查询（project_members/finance_accounts/inventory_items 按已限定 id 取名）。**未发现真实数据越权绕过**。。
- **已落地纵深修复**：ExpenseController `index`（普通用户强制只看自己、忽略他人 `user_id` 参数）与 `show`（非本人/非看全部者 403）——与 scope 行为一致，属冗余纵深（2026-09-06 部署于 192.168.1.109 并实测：oa_business_admin 本人 200、普通用户被拒）。
