# 安防运维OA系统 (Security OA)

> **V1.2.7** — 企业级安防运维综合办公管理系统
>
> PC Web 端 + Laravel API 端全栈 OA 系统

> 仓库仅保留系统本体代码与现行部署脚本，开发期调试工具、归档脚本、PRD/审计文档等不入仓。
> 当前最新版本：**V1.2.7**（架构整改：模型拆分 / 路由拆分 / 队列 / API 文档 / 性能监控三级告警）

## 技术栈

| 层 | 技术 |
|---|---|
| **前端** | Vue 3 + Element Plus + TypeScript + Vite + Pinia |
| **后端** | Laravel 11 (PHP 8.2+) |
| **数据库** | PostgreSQL 15+ |
| **认证** | Sanctum Token |

## 系统规模

| 指标 | 数量 |
|---|---|
| 数据库表 | **152 张** |
| 后端 Controller | **75 个** |
| 数据模型 | **56 个** |
| 业务服务层 | **21 个** |
| Migration 文件 | **144 个** |
| 前端 API 模块 | **14 个** |
| 前端页面 | **364 个 Vue** |
| 后端 PHP 文件 | **198 个** |

## 功能模块（16 大模块）

- 🖥️ 工作台 / 考勤管理 / 员工管理 / 客户管理
- 📋 项目管理 / 售后服务 / 报销管理 / 车辆管理
- 💰 库存管理 / 财务管理 / 网盘文件
- 📚 知识库 / 消息中心 / 数据大屏
- 📝 培训管理 / 审批中心 / 巡检计划 / 招标管理

## 项目结构

```
security-oa/
├── pc-api/                       # Laravel 13 后端
│   ├── app/Http/Controllers/Api/ # 75 个业务控制器
│   ├── app/Http/Middleware/      # EnsureBusinessUser / EnsureSystemUser
│   ├── app/Models/               # 56 个数据模型
│   ├── app/Observers/            # UserScheduleObserver 等
│   ├── app/Services/             # 21 个业务服务
│   ├── database/migrations/      # 152 个迁移文件
│   └── routes/api.php            # API 路由定义
├── pc-web/                       # Vue3 + Element Plus 前端
│   ├── src/views/                # 364 个 Vue 页面
│   ├── src/api/                  # 14 个 API 模块
│   └── src/components/           # 公共组件
├── pc-desktop/                   # Electron 桌面端脚手架
├── oa-docker/                    # Docker 一键部署包 (docker-compose)
├── deploy/                       # 现行部署脚本
│   ├── README.md                 # 部署说明
│   ├── deploy.py                 # 主部署入口（默认 117 主机）
│   ├── deploy_https_152.py       # 152 主机 HTTPS 部署
│   ├── fix_152_v3.py             # 152 主机现场修复
│   └── renew_cert_152.py         # 152 主机证书续签
├── README.md
├── .gitignore
└── .gitattributes
```

## 快速开始

### 环境要求

- Node.js >= 18
- PHP >= 8.2
- PostgreSQL >= 15
- Redis >= 6 (队列 + 缓存, P2-1 必装)
- Composer & npm/pnpm

### V1.2.7 队列启动 (新增)

```bash
# 队列 worker (推荐用 supervisor 守护)
cd pc-api
php artisan queue:work --queue=notifications,schedules,exports,default --tries=3

# Horizon 监控面板 (开发环境)
php artisan horizon       # http://localhost:8000/horizon

# 调度器 (生产需每分钟跑一次)
* * * * * cd /path/to/pc-api && php artisan schedule:run >> /dev/null 2>&1
```

### 本地前端

```bash
cd pc-web
npm install
npm run dev      # http://localhost:3000
```

### 本地后端

```bash
cd pc-api
composer install
cp .env.example .env       # 配置数据库凭据
php artisan key:generate
php artisan migrate --seed
php artisan serve          # http://localhost:8000
```

### 生产构建

```bash
# 前端构建
cd pc-web && npm run build    # 输出到 dist/

# 后端优化
cd pc-api && php artisan optimize
```

## 版本里程碑

| 版本 | 内容 | 状态 |
|---|---|---|
| **V1.2.7** | 架构整改: 模型拆分75个/路由拆10子文件/FormRequest/ScheduleService/队列+Horizon/Scramble文档/慢接口三级告警 | ✅ 完成 |
| V1.2.6 | admin 隔离 / system 首次登录 / 考勤自动排班 / Docker 部署包 | ✅ 已发布 |
| V1.2.5 | 商机赢单自动建居间费结算单 + 编辑模式回填 referrer | ✅ 完成 |
| V1.2.4 | super-admin 完整初始化 + 业务管理员权限全开 | ✅ 完成 |
| V1.0 | 正式发布 — 全量 16 模块 / 监控+备份+审计+缓存 | ✅ 完成 |
| V0.9 | 收尾优化 — TS类型清理 / 性能监控 / 审计治理 | ✅ 完成 |
| V0.8 | 运维三件套 — 性能监控 / 健康探针 / 自动备份 | ✅ 完成 |
| V0.7 | 巡检计划 — 现场打卡 + 异常转工单 | ✅ 完成 |
| V0.6 | 招标中心 — 审核/撤回/保证金/联动 | ✅ 完成 |
| V0.5 | 核心模块 — 售后/报销/车辆/库存/财务 | ✅ 完成 |
| V0.3-V0.4 | 基础框架 + 16模块骨架搭建 | ✅ 完成 |

## UI 设计

| 用途 | 颜色 |
|---|---|
| 主色 | `#0C447C` |
| 辅色 | `#1D9E75` |
| 警告 | `#BA7517` |
| 危险 | `#A32D2D` |
| 信息 | `#534AB7` |

## License

Private — All Rights Reserved
