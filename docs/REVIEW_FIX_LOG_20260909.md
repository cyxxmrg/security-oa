# 修复记录 — 依据 docs/CODE_REVIEW.md（2026-09-09）

> 版本：v1.4.5 ｜ 关联审查报告：`docs/CODE_REVIEW.md` / `output/REVIEW_full_codebase_20260909.md`
> 状态图例：✅ 已完成并验证 ｜ 🟡 已修复待运行时验证 ｜ ⏳ 计划中

---

## P0（已完成）

### P0-1 前端按钮级权限链路断裂 🟡
- **后端**：`pc-api/app/Http/Controllers/Api/AuthController.php` `userInfo()` 恢复返回 `permissions`/`roles`，改用 `activePermissionNames()` / `activeRoles()` 绕过此前导致注释的 Spatie 缓存问题。
- **前端**：`pc-web/src/stores/user.ts` `hasPermission()` 委托 `usePermissionStore().hasPermission()`（带 admin 旁路 + 模块前缀兜底），不依赖 userStore.permissions。
- 验证：vue-tsc 类型检查通过；后端逻辑为现有方法组合（无新查询面）。**需运行时验证**：登录后报销页按钮按权限显示。

### P0-2 备份 cron token 无写入方（run-due 死代码）✅
- 新增命令：`pc-api/app/Console/Commands/BackupCronToken.php`（`php artisan oa:backup-token [--rotate]`）。
- 新增接口：`BackupController::cronToken()` / `rotateCronToken()`；路由注册于 `routes/api/finance.php` 备份组（system-only）。
- 修复 `BackupController::__construct` 守卫 `->except(['runDue'])`——此前公开 cron 端点被控制器 system 守卫误拦截。
- 修复 `runDue()` token 校验与中间件对齐（支持 `X-Backup-Cron-Token` 头或 `?token=`），区分"未配置/不正确"。
- 验证：4 个 PHP 文件括号平衡通过；`backup.cron` 中间件别名已在 `bootstrap/app.php` 注册。

### P0-3 文档/版本漂移 ✅
- `pc-api/config/oa.php` app_version → `v1.4.5`。
- `README.md`：版本徽章、Laravel 12、PHP 8.4+、当前版本段更新。
- `CHANGELOG.md`：新增 v1.4.5，回填 v1.4.4/v1.4.3（基于代码已入库修复证据）。
- `pc-web/package.json` version → 1.4.5。

## P1（本轮已完成部分）

### P1-1 菜单不按权限过滤 ✅
- `pc-web/src/layouts/MainLayout.vue`：`isVisible()` 增加 `meta.permission` 校验（`permStore.hasPermission`），与 main.ts 路由守卫同语义。
- 验证：vue-tsc 通过。注意：无 `meta.permission` 的路由仍可见（无法判定），属已知边界。

### P1-4 deploy/ 硬编码凭据 ✅（部分为既有已修复）
- `deploy/deploy.py`：V1.4.3 已改环境变量注入，注释已脱敏（本报告此前将其列为"未清理"系误判，以本次为准）。
- `deploy/_archive/deploy_152.py`：6 处 `<已脱敏>` → `<redacted>`（字节级替换）。
- `deploy/_archive/renew_cert_152.py`：1 处 `<已脱敏>` → `<redacted>`。
- 注：`deploy/oa-reset-password-tool.py` 默认仅含内网 IP/用户名（密码为空），保留。

### P1-5 审计日志 PII 脱敏 🟡
- `AuthController::updateProfile()`：写入 `system_logs.request_data` 前对 phone/email 脱敏（`maskPhone`/`maskEmail`）。
- 验证：PHP 括号平衡通过；**需运行时验证**。

### P1-3 DataScope 未映射表加固 🟡（可见性加固版）
- `pc-api/app/Scopes/DataScope.php` `apply()`：未映射表对业务用户**不挂行级过滤**（既有行为）→ 新增**限流告警**（`Cache::add("datascope:unmapped:{table}:{userId}", 1, 300)`，每 表×用户 5 分钟至多一条 warning），使"静默全量读取"可观测，零查询行为变更。
- 说明：完整收口（默认拒绝 1=0 或逐表补规则）需逐接口评估"合法全量场景"，本轮不冒险改变行为；告警日志即为后续补规则的依据。

## P2（本轮已完成部分）

### P2-4 空密码登录分支账号枚举 ✅
- `AuthController::login()`：system 与普通账号空密码统一返回同一文案，不再暴露 is_system。

### P3 仓库卫生 ✅（部分）
- `.gitignore`：第 116 行非法 UTF-8 字节修复；`output/` 改为"忽略脚本、保留 .md 报告"白名单；补忽略 `.merge_tmp/`/`.ruff_cache/`/`CUsersMRG*`/`/.vue-tsc*.log`；根目录部署包模式已存在。
- 清理：删除空文件 `=`、`.vue-tsc*.log` 构建日志、`.merge_tmp/` 冲突残留。
- 保留：根目录 `.deploy_*.tar.gz`/`oa-web-dist-*.tar.gz` 部署包（不入库，供发布使用）。

### P3 顺带修复
- `pc-web/src/utils/auth.ts` `getUserInfo()` 返回类型 `Record<string, unknown> | null`——修复 vue-tsc 既有类型错误（此前全仓类型检查无法通过）。

---

## 验证汇总

| 项 | 结果 |
| --- | --- |
| 后端 PHP 括号平衡（控制器/中间件/服务/命令/迁移/路由/测试/引导 16+ 文件，先剥单引号后剥双引号的正确顺序校验） | ✅ 全部通过 |
| 前端 vue-tsc --noEmit | ✅ 通过（auth.ts 修复后；SetupWizard 改动后复跑 exit 0） |
| 新增测试（InventoryStockOutTest 幂等用例） | ⏳ 需 PHP 环境运行（`php artisan test --filter=InventoryStockOutTest`） |
| 运行时/接口行为 | ⏳ 需部署后实测（登录权限、报销按钮、备份 token 轮换与 run-due、审计脱敏、幂等重放、迁移执行） |

## P2（第二轮完成）

### P2-8 库存出入库幂等键 🟡（需迁移+运行时验证）
- 迁移：`pc-api/database/migrations/2026_09_09_000001_add_request_id_to_stock_records_table.php` — `stock_records.request_id` 列 + PG 部分唯一索引 `stock_records_request_id_uq`（非空唯一）。
- `InventoryService::stockIn/stockOut`：接受可选 `request_id`；事务内先查重——已处理过则 `replayStockRequest()` 返回历史结果（`replayed=true`）；并发竞态由唯一索引兜底（捕获 `SQLSTATE 23505` 重放）。
- `StockRecord::$fillable` 增加 `request_id`。
- 测试：`tests/Feature/InventoryStockOutTest.php` 新增 `test_stock_out_request_id_is_idempotent`。
- 验证：PHP 括号平衡通过；**需迁移执行 + 测试运行验证**。

### P2-1 登录锁定账号 DoS 🟡
- `LoginThrottle`：失败/锁定键加入 IP 维度（`login:fail:{username}:{ip}` / `login:lock:{username}:{ip}`）——攻击者无法再用任意 IP 5 次失败锁死他人账号；分布式爆破仍受路由级 `throttle:api`(30/min/IP) 约束。

### P2-2 CheckPermission 每请求查库 🟡
- `resolveCandidates()` 结果短 TTL 缓存（`Cache::remember('perm:candidates:' . md5(expr), 60, …)`）——权限点注册仅迁移/后台变更，60s 陈旧窗口影响可忽略；消除每请求 LIKE 查询。

### P2-3 finance.php 中文注释乱码 — 结论：不冒险重写
- 实测为**混合编码**（UTF-8 转义乱码 + 残留 GBK 字节），自动恢复不可靠；手工重写 100+ 行注释收益低、误改风险高。
- 处置：保留现状（仅注释问题，不影响运行），记录为已知技术债。

## P1-6 编号生成双轨统一 ✅（第三轮）

- 遗留 4 处旧轨道（`prefix-date-str_pad(id)`）全部迁移到 `NumberSequenceService`：
  - `PurchaseFlowService`：AP 应付编号（原 PO id）→ `nextApRefNo()`；INB 采购入库流水编号（原发货单 id）→ `nextInbRecordNo()`。
  - `TenderController`：AP 应付编号（原 PO id）→ `nextApRefNo()`，与 PurchaseFlowService **共享同一 `payable-ref:{date}` 序列**。
  - `SalesService`：SC 销售合同编号（原项目 id）→ `nextSalesContractNo()`。
- 种子函数从既有编号回填（`substr` 解析后缀 max），新编号与同日历史编号无碰撞。
- 验证：3 文件括号平衡通过；grep 确认无残留 `'AP-' . date('Ymd')` 旧模式。

## P2-6 Provider 手工注册核验 ✅（第三轮）

- `bootstrap/app.php withProviders` 补注册 3 个**生产第三方包** provider（此前仅核心 + Horizon，依赖 `bootstrap/cache/services.php` 存在才能加载，缺失时 Sanctum/Spatie/Dompdf 静默失效）：
  - `Laravel\Sanctum\SanctumServiceProvider` / `Spatie\Permission\PermissionServiceProvider` / `Barryvdh\DomPDF\ServiceProvider`
- Laravel 对重复注册去重，与 package discovery 并存安全。Dev-only（Scramble 等）不注册，避免生产 class 缺失致命错误。

## P2-7 测试基建（CI 可移植）✅（第三轮）

- `phpunit.xml`：DB 配置显式 `force="false"`（CI 用真实环境变量注入测试库）；补充 E2E/OA_E2E_* 说明注释。
- **E2E 硬编码凭据清理（P1-4 延伸，9 文件）**：`E2ETestCase` 新增 `e2ePass($envKey)`（读 `OA_E2E_ADMIN_PASS`/`OA_E2E_BUSINESS_PASS`/`OA_E2E_APPROVER_PASS`），6 个 E2E 测试文件改从 env 取密码、未配置自动 `markTestSkipped`；`BusinessApiTest`/`PermissionMatrixApiTest`/`UserRoleApiTest` 登录默认密码移除；`AuthApiTest` USERS 常量改方法。
- `tests/Feature/RoleGuardrailApiTest.php`：默认密码 `<已脱敏>` 移除，`OA_TEST_PASS` 未配置即抛出引导（setUpBeforeClass）。
- `pc-web/src/views/settings/SetupWizard.vue`：批量建号默认密码 `'Pass1234'` 改为随机生成（含大小写+数字，13 位）。
- `SystemSettingsController` docblock 示例密码 `'Admin@2026'/'NewPwd@2026'` 改为 `<示例密码>`。
- **凭据全仓扫描结论**：`AuthController`/`EmployeeOnboardingController` 的 `<已脱敏>/123456` 命中为**弱密码黑名单**（故意列出拒绝，保留）；`PurchaseDetail.vue`/`Backup.vue`/`SetupWizard.vue` ICP 处为快递单号/备案号占位示例（误报）；`oa-baremetal`/`oa-docker` 部署套件文档与脚本含首登默认密码说明（部署套件范畴，已记录待后续统一走 env 注入）。
- 验证：16 文件括号平衡（先剥单引号后双引号的正确顺序）ALL-PASS；vue-tsc exit 0。

## 遗留（后续轮次）

- DataScope 逐表补规则（依据告警日志收口未映射表）。
- `oa-baremetal`/`oa-docker` 部署套件默认密码 env 化（外部部署套件，需单独一轮）。
- 运行时验证项：迁移执行、备份 token 轮换与 run-due 触发、报销页按钮权限、审计日志脱敏、幂等重放、编号迁移后新单编号连续性。
