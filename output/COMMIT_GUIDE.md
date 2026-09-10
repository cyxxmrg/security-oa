# Security OA 变更提交指引（V1.4.3 安全审计批次）

> 适用：本仓库安全审查与修复批次的 git 提交。本机无 git，以下拆分与提交信息供你在仓库环境直接使用。
> 提交前务必确认 `pc-api/.env`、`pc-web/.env.*`、`*.tar.gz`、日志与临时文件未被跟踪（见文末检查清单）。

## 建议提交拆分（按逻辑单元，共 7 个）

### 1. 安全审查报告
```
git add output/REVIEW_security-audit.md
git commit -m "docs: 全库安全审查报告(五阶段+修复记录+补充核对)"
```

### 2. RBAC 提权护栏（P0）
```
git add pc-api/app/Http/Controllers/Api/RoleController.php
git commit -m "security: RBAC 提权护栏 - 内置高权限角色只读/禁改自身角色/禁授 admin/高敏感权限点仅 system 可注入"
```

### 3. 护栏回归测试（env 化账号）
```
git add pc-api/tests/Feature/RoleGuardrailApiTest.php
git commit -m "test: RBAC 护栏回归测试 7 用例(5 负向 403 + 2 正向放行), 账号经 OA_TEST_USER/OA_TEST_PASS 覆盖"
```

### 4. 权限注册迁移（vehicle / fuel 拆分）
```
git add pc-api/routes/api/finance.php \
        pc-api/database/migrations/2026_09_06_000000_register_vehicle_route_permissions.php \
        pc-api/database/migrations/2026_09_06_000001_register_fuel_card_edit_permission.php
git commit -m "security: fuel-cards 读写拆分为 vehicle.fuel(读)/vehicle.fuel.edit(写); 幂等注册 vehicle.create/edit 权限点"
```

### 5. 权限字典与绑定表修正
```
git add pc-api/database/seeders/PermissionRoleSeeder.php \
        pc-api/app/Http/Controllers/Api/SystemSettingsController.php
git commit -m "fix: PermissionRoleSeeder 清空重建机制下补 vehicle.fuel.edit 字典; wipe-data 权限绑定改写入生效表 role_has_permissions"
```

### 6. deploy 凭据清理
```
git add deploy/_archive/ deploy/deploy.py
git commit -m "security: 归档含硬编码凭据的 152 系列脚本; deploy.py ssh 凭据改环境变量注入"
```

### 7. install.sh 26.04 部署适配 + guardrail 清理命令
```
git add install.sh pc-api/app/Console/Commands/CleanGuardrailTempRoles.php
git commit -m "feat: install.sh 适配 Ubuntu 26.04(官方源 Node); 新增 oa:clean-guardrail-roles 清理测试残留角色"
```

## 提交前检查清单
```bash
git status --porcelain          # 核对变更面
git ls-files pc-api/.env        # 必须为空(勿提交 .env)
git diff --stat                 # 确认无意外大文件(*.tar.gz/node_modules/dist)
```

## 备注
- 服务器 192.168.1.122 已部署本批次改动并通过真实环境验证（护栏 7/7、业务冒烟、fuel 读写隔离、权限注册/绑定核查）。
- 潜在行为变化（需产品确认）：业务管理员不能再调整内置 admin 角色/自身角色权限，admin 角色授予与高敏感权限点仅 system 账号可操作。
