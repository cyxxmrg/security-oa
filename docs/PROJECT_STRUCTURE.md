# Security OA 工程导览

本文档面向第一次进入仓库的开发者和交付人员，说明代码边界、关键入口以及常见任务应该从哪里开始。

## 顶层目录

```text
security-oa/
|-- pc-web/                  Vue 3 Web 客户端
|-- pc-api/                  Laravel 业务服务端
|-- docs/                    工程与交付文档
|-- output/                  经确认纳入版本的验证报告
|-- install.sh               Ubuntu 一键安装入口
|-- CHANGELOG.md             版本变更记录
|-- README.md                产品与工程总览
`-- LICENSE                  开源许可
```

## Web 客户端

`pc-web` 负责工作台、业务页面、交互状态、前端权限和 API 调用适配。

| 路径 | 职责 | 进入场景 |
| --- | --- | --- |
| `src/main.ts` | 创建 Vue 应用并注册路由、状态与全局指令 | 排查应用启动和全局插件 |
| `src/App.vue` | 顶层配置、路由过渡和页面加载状态 | 修改全局语言、骨架屏和应用级交互 |
| `src/router/` | 路由树、页面元数据和访问守卫 | 新增页面、菜单入口或路由权限 |
| `src/layouts/` | 主框架、导航、面包屑和用户操作区 | 修改全局布局和菜单行为 |
| `src/views/` | 按业务域组织的页面实现 | 开发客户、项目、采购、库存等功能 |
| `src/components/` | 跨业务复用组件 | 抽取稳定的通用交互 |
| `src/stores/` | Pinia 状态与系统配置 | 处理登录用户、菜单和配置缓存 |
| `src/utils/request.ts` | HTTP 客户端、鉴权和统一错误处理 | 排查接口、Token 与错误提示 |
| `src/utils/permission.ts` | 前端权限判断 | 控制按钮和局部能力可见性 |
| `vite.config.ts` | 构建、代理、按需组件和分包策略 | 调整构建性能和开发代理 |

### 页面组织原则

- 页面按业务域放入 `src/views/<domain>/`，避免按 UI 类型拆散业务上下文。
- 页面只负责编排交互；可复用表单、表格和详情块下沉到同域 `components/`。
- API 请求统一经过 `src/utils/request.ts`，不在组件内自行创建 Axios 实例。
- 路由、菜单和权限标识保持同一业务命名，避免出现页面可访问但菜单不可见的漂移。

## Laravel 服务端

`pc-api` 提供鉴权、业务 API、审批编排、数据统计、后台任务和系统治理能力。

| 路径 | 职责 | 进入场景 |
| --- | --- | --- |
| `routes/api.php` | API 总入口 | 查找接口分组及中间件边界 |
| `routes/api/` | 按业务域拆分的路由文件 | 新增或调整领域接口 |
| `app/Http/Controllers/Api/` | 请求编排与响应输出 | 定位具体 API 行为 |
| `app/Http/Requests/` | 参数校验和授权前置判断 | 增加字段或校验规则 |
| `app/Services/` | 事务、状态机和跨模型业务逻辑 | 修改采购、库存、资金或审批流转 |
| `app/Models/` | Eloquent 模型、关系和数据类型 | 理解表关系和字段转换 |
| `app/Http/Middleware/` | 鉴权、数据范围、审计和性能监控 | 排查权限与请求链路 |
| `app/Jobs/` | 队列任务 | 处理通知、导出和异步告警 |
| `app/Console/Commands/` | 运维及数据命令 | 健康检查、统计刷新和数据维护 |
| `database/migrations/` | 数据库结构演进 | 变更表、索引和约束 |
| `database/seeders/` | 初始化和演示数据 | 准备环境基础数据 |
| `tests/Feature/` | 业务接口与流程回归 | 验证跨模块行为 |
| `config/oa.php` | OA 版本与运行阈值 | 调整系统版本和治理参数 |

### 服务端调用边界

```text
Route
  -> Middleware
    -> FormRequest
      -> Controller
        -> Domain Service
          -> Model / Database
        -> API Response
```

- Controller 负责请求编排，不承载复杂状态流转。
- 涉及库存、资金、审批或多表写入的逻辑进入 Service，并使用数据库事务。
- 权限既要经过中间件，也要在资源级操作中验证数据归属。
- 新增业务状态时，同步检查枚举、数据库约束、前端标签和统计口径。

## 关键业务链路

| 链路 | 前端入口 | 服务端重点 |
| --- | --- | --- |
| 客户到商机 | `views/customer`、`views/sales` | `CustomerController`、`SalesService` |
| 项目到施工 | `views/project`、`views/construction` | `ProjectController`、施工领域 Services |
| 采购到入库 | `views/purchase`、`views/inventory` | `PurchaseFlowService`、`InventoryService` |
| 领料到成本 | `views/inventory/MaterialRequest.vue` | `OperationApprovalController`、库存流水 |
| 报销到付款 | `views/expense`、`views/finance` | `ExpenseController`、财务审批与账户流水 |
| 加班到审批 | `views/attendance/Overtime.vue` | `AttendanceController`、`OperationApprovalController` |
| 工单到维修 | `views/service`、`views/maintenance` | 工单、维修、物流和备件服务 |

## 常见任务入口

| 任务 | 建议检查顺序 |
| --- | --- |
| 新增业务页面 | 路由 -> 页面 -> API 调用 -> 权限标识 -> 菜单 |
| 新增业务接口 | 路由 -> FormRequest -> Controller -> Service -> Feature Test |
| 修改审批流程 | 审批模板 -> Flow Service -> 审批 Controller -> 业务表回写 -> 测试 |
| 修改统计口径 | 来源单据 -> 聚合查询/物化视图 -> API -> 看板展示 -> 数据校验 |
| 修改库存流转 | 单据状态 -> 库存事务 -> 流水 -> 结存 -> 项目成本 |
| 修改角色权限 | 权限定义 -> 角色授权 -> 中间件 -> 数据范围 -> 前端控制 |

## 验证基线

```bash
# 前端生产构建
cd pc-web
npm ci
npm run build

# 后端功能测试
cd pc-api
php artisan test

# Laravel 生产缓存
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

涉及审批、采购、库存或资金的修改，除自动化测试外，还应在测试环境完成页面操作，并核对业务表、审批表、库存流水或资金流水是否一致。
