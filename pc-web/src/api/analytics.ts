/**
 * V1.2.7 E3: BI 报表前端 API
 *
 * 6 大报表 + 刷新状态 + PDF 导出
 * 数据来自物化视图 (凌晨 02:30 刷新), 服务端 5min Redis 缓存
 */
import { get } from '@/utils/request'
import { openAuthenticatedFile } from '@/utils/privateFile'

/** 月度营收 */
export function getRevenue(params?: { start?: string; end?: string; industry?: string }) {
  return get('/analytics/revenue', params)
}

/** 销售漏斗 (近 N 周) */
export function getSalesFunnel(params?: { weeks?: number }) {
  return get('/analytics/sales-funnel', params)
}

/** 项目健康度 */
export function getProjectHealth(params?: { color?: 'green' | 'yellow' | 'red'; limit?: number }) {
  return get('/analytics/project-health', params)
}

/** 客户 RFM */
export function getCustomerRfm(params?: { segment?: string; limit?: number }) {
  return get('/analytics/customer-rfm', params)
}

/** 库存周转 */
export function getInventoryAging(params?: { status?: string }) {
  return get('/analytics/inventory-aging', params)
}

/** 财务利润表 */
export function getFinancePnl(params?: { start?: string; end?: string }) {
  return get('/analytics/finance-pnl', params)
}

/** 物化视图刷新状态 */
export function getRefreshStatus() {
  return get('/analytics/refresh-status')
}

/** 通过 Bearer Token 获取 PDF，避免令牌进入 URL、代理日志和浏览器历史。 */
export function exportAnalyticsPdf(report: string, template: 'executive' | 'full' | 'deep' = 'executive') {
  const url = `/api/analytics/export/pdf?report=${encodeURIComponent(report)}&template=${encodeURIComponent(template)}`
  return openAuthenticatedFile(url, `OA报表_${report}.pdf`, true)
}
