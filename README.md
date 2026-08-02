# 安防运维 OA 系统 (Security OA)

> **v1.4.2** — 安防运维企业综合办公管理系统
>
> PC Web 端（Vue3）+ Laravel API 端（pc-api）全栈 OA 系统

> 本仓库为开源发布的**纯核心源码**，包含前端与后端本体代码、必要配置及 `install.sh` 一键部署脚本；开发调试工具、归档文档等不入仓。

## 技术栈

| 层 | 技术 |
|---|---|
| **前端** | Vue 3 + Element Plus + TypeScript + Vite + Pinia |
| **后端** | Laravel 11 (PHP 8.5+) |
| **数据库** | PostgreSQL 15+ |
| **缓存/队列** | Redis |
| **认证** | Sanctum Token |
|**开发测试部署环境**|Ubuntu Server 26.04 LTS|

## 功能模块

- 工作台、老板看板、考勤管理、员工与组织管理
- 客户、商机、报价、项目、施工、巡检、售后与质保管理
- 采购、供应商、库存、工具使用与固定资产管理
- 财务、报销、车辆、网盘、知识库、消息与培训管理
- 业务、财务、项目审批中心及可配置审批流

## 仓库结构

本仓库仅包含可复用的核心源码：

```
security-oa/
├── pc-api/                       # Laravel 后端
│   ├── app/Http/Controllers/Api/ # 业务控制器
│   ├── app/Http/Middleware/      # EnsureBusinessUser / EnsureSystemUser
│   ├── app/Models/               # 数据模型
│   ├── app/Services/             # 业务服务层
│   ├── database/migrations/      # 数据库迁移
│   └── routes/api.php            # API 路由定义
├── pc-web/                       # Vue3 + Element Plus 前端
│   ├── src/views/                # Vue 页面
│   ├── src/api/                  # API 模块
│   └── src/components/           # 公共组件
├── README.md
├── .gitignore
└── .gitattributes
```

> 仓库内置 `install.sh` 提供 Ubuntu 26.04  一键部署（Nginx + PHP 8.5 + PostgreSQL + Redis + 前后端构建）。Docker / 桌面端部署方案可联系维护方获取。

## 快速开始

### 环境要求

- Node.js >= 18
- PHP >= 8.5
- PostgreSQL >= 15
- Redis >= 6（缓存 + 队列）
- Composer & npm/pnpm

### 本地前端

```bash
cd pc-web
npm install
npm run dev      # 开发服务器
```

### 本地后端

```bash
cd pc-api
composer install
cp .env.example .env       # 配置数据库凭据
php artisan key:generate
php artisan migrate
php artisan serve          # http://localhost
```

### 生产构建

```bash
# 前端构建（输出到 dist/）
cd pc-web && npm run build

# 后端优化
cd pc-api && php artisan optimize
```

## 一键部署（Ubuntu 服务器）

仓库内置 `install.sh`，可在干净的 Ubuntu 22.04 / 24.04 上自动完成全套安装与启动：

```bash
# 以 root 执行
sudo bash install.sh --domain oa.example.com
```

脚本自动安装 PHP 8.5（sury 源）、Composer、Node.js 20、Nginx、PostgreSQL、Redis，拉取源码、配置后端（随机生成数据库密码与应用密钥）、构建前端，并写入 Nginx 站点（80 端口同源托管前端与 `/api`）。

| 参数 | 说明 |
|---|---|
| `--domain <域名或IP>` | 指定访问地址（默认取本机 IP） |
| `--dir <路径>` | 安装目录（默认 `/var/www/oa`） |
| `--demo` | 额外创建演示管理员 `admin`（随机密码，结尾打印） |
| `--skip-clone` | 复用已有代码目录，跳过 git clone |
| `--force` | 目标目录已存在时强制覆盖重装 |

> 脚本不硬编码任何真实密码，数据库密码与应用密钥均随机生成并打印；部署后请及时修改默认凭据并启用 HTTPS。

## 版本里程碑

| 版本 | 内容 | 状态 |
|---|---|---|
| **v1.4.2** | 工具使用单新增领用/归还明细页，支持工具与记录精确查询 | ✅ 当前版本 |
| v1.4.1 | 修复前端版本号缓存及刷新显示错乱 | ✅ 完成 |
| v1.4.0 | 新增固定资产全生命周期管理 | ✅ 完成 |
| v1.3.6 | 修复库存流水物料名称显示，并完善工具数量流转 | ✅ 完成 |
| v1.3.2 | 源码发布及列表缓存失效修复 | ✅ 完成 |
| v1.2.7 | 架构整改：模型拆分 / 路由拆分 / 队列 / API 文档 / 性能监控三级告警 | ✅ 完成 |
| v1.2.6 | admin 隔离 / system 首次登录 / 考勤自动排班 / Docker 部署包 | ✅ 完成 |
| v1.2.5 | 商机赢单自动建居间费结算单 + 编辑模式回填 referrer | ✅ 完成 |
| v1.0 | 正式发布 — 全量 16 模块 / 监控 + 备份 + 审计 + 缓存 | ✅ 完成 |

## UI 设计

| 用途 | 颜色 |
|---|---|
| 主色 | `#0C447C` |
| 辅色 | `#1D9E75` |
| 警告 | `#BA7517` |
| 危险 | `#A32D2D` |
| 信息 | `#534AB7` |

## License

本项目以 **MIT License** 开源发布，详见 [LICENSE](./LICENSE)。
