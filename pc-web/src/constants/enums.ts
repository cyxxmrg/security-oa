/**
 * V1.2.10 — 前端 enum 常量单一真相源
 *
 * 背景:
 *   前后端 enum 不一致多次踩坑 (stock_records.type 前端 inbound/outbound vs 后端 in/out,
 *   导致 1489 条数据全被前端 filter 掉)。此文件固定前端用的合法值,
 *   新增页面/下拉/filter 必须从这里导入, 不要硬编码字符串。
 *
 * 后端真相来源:
 *   - pc-api/app/Enums/*.php (PHP enum class)
 *   - pc-api/database/migrations/ 的 ->enum() / CHECK 约束
 *   - 117 服务器实际数据 (PG 无 CHECK 约束, 实际值由 seeder 决定)
 *
 * 注意:
 *   - stock_records.type 后端 migration 定义是 in/out/transfer/check,
 *     但 117 服务器 seeder 灌的是 inbound/outbound/sale/scrap/request/return (V1.2.9u 修正),
 *     前端以下拉/filter 为准用后者。152 展示机数据未确认, 不动。
 */

// ============ stock_records.type (库存出入库) ============
// 117 实际数据: inbound 1089 / outbound 100 / request 100 / return 100 / sale 50 / scrap 50
export const STOCK_RECORD_TYPES = {
  INBOUND: 'inbound',      // 入库
  OUTBOUND: 'outbound',    // 出库
  SALE: 'sale',            // 销售出库
  SCRAP: 'scrap',          // 报废
  REQUEST: 'request',      // 领料申请
  RETURN: 'return',        // 退料
  TRANSFER: 'transfer',    // 调拨 (后端 migration 有, 117 暂无数据)
  CHECK: 'check',          // 盘点 (后端 migration 有, 117 暂无数据)
} as const

export const STOCK_RECORD_TYPE_LABELS: Record<string, string> = {
  inbound: '入库',
  outbound: '出库',
  sale: '销售出库',
  scrap: '报废',
  request: '领料',
  return: '退料',
  transfer: '调拨',
  check: '盘点',
}

// 入库单页面 filter (InboundOrder.vue)
export const INBOUND_FILTER_TYPES = [STOCK_RECORD_TYPES.INBOUND, STOCK_RECORD_TYPES.RETURN]
// 出库单页面 filter (OutboundOrder.vue)
export const OUTBOUND_FILTER_TYPES = [STOCK_RECORD_TYPES.OUTBOUND, STOCK_RECORD_TYPES.SALE, STOCK_RECORD_TYPES.SCRAP]

// ============ work_orders.status (工单状态) ============
// 后端 Enums/WorkOrderStatus.php: pending/assigned/in_progress/resolved/closed/cancelled/converted_to_repair
// ⚠️ 没有 'completed' (用 resolved)
export const WORK_ORDER_STATUS = {
  PENDING: 'pending',
  ASSIGNED: 'assigned',
  IN_PROGRESS: 'in_progress',
  RESOLVED: 'resolved',      // 完成 (不是 completed!)
  CLOSED: 'closed',
  CANCELLED: 'cancelled',
  CONVERTED_TO_REPAIR: 'converted_to_repair',
} as const

export const WORK_ORDER_STATUS_LABELS: Record<string, string> = {
  pending: '待处理',
  assigned: '已指派',
  in_progress: '处理中',
  resolved: '已完成',
  closed: '已关闭',
  cancelled: '已取消',
  converted_to_repair: '转维修',
}

// ============ work_orders.priority ============
export const WORK_ORDER_PRIORITY = {
  LOW: 'low',
  MEDIUM: 'medium',
  HIGH: 'high',
  URGENT: 'urgent',
} as const

// ============ projects.stage (项目阶段, 7 个值) ============
// 后端 migration 2024_01_02_000001: initiation/inquiry/contract/purchase/construction/settlement/warranty
export const PROJECT_STAGE = {
  INITIATION: 'initiation',
  INQUIRY: 'inquiry',
  CONTRACT: 'contract',
  PURCHASE: 'purchase',
  CONSTRUCTION: 'construction',
  SETTLEMENT: 'settlement',
  WARRANTY: 'warranty',
} as const

// ============ projects.status ============
export const PROJECT_STATUS = {
  PENDING: 'pending',
  IN_PROGRESS: 'in_progress',
  COMPLETED: 'completed',
  CANCELLED: 'cancelled',
} as const

// ============ projects.type ============
export const PROJECT_TYPE = {
  CAMERA: 'camera',
  ACCESS_CONTROL: 'access_control',
  ALARM: 'alarm',
  COMPREHENSIVE: 'comprehensive',
  NETWORK: 'network',
  CLOUD_PLATFORM: 'cloud_platform',
} as const

// ============ customers.category (小写!) ============
export const CUSTOMER_CATEGORY = {
  VIP: 'vip',       // 小写, 后端 CHECK 约束
  NORMAL: 'normal',
  POTENTIAL: 'potential',
} as const

// ============ finance_invoices.status ============
// ⚠️ 没有 'paid'
export const FINANCE_INVOICE_STATUS = {
  DRAFT: 'draft',
  ISSUED: 'issued',
  CANCELLED: 'cancelled',
} as const

// ============ repair_orders.status ============
// ⚠️ 没有 'completed'/'diagnosing'
export const REPAIR_ORDER_STATUS = {
  RECEIVED: 'received',
  SENT_FOR_REPAIR: 'sent_for_repair',
  IN_REPAIR: 'in_repair',
  REPAIRED: 'repaired',     // 完成 (不是 completed!)
  SENT_BACK: 'sent_back',
  SHIPPED_BACK: 'shipped_back',
  CLOSED: 'closed',
  CANCELLED: 'cancelled',
} as const
