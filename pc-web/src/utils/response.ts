/**
 * V1.2.10 — 统一 API 响应解包 helper
 *
 * 背景:
 *   - 后端响应拦截器 (utils/request.ts L82) V0.6.3 起不再自动解包 {code, data, message}
 *   - 导致 135+ 个页面各写各的 fallback (res?.data ?? res / Array.isArray(res.data) / res?.data?.data 等)
 *   - 多次踩坑: paginate 包装未解包、list 字段未解包、统计字段未解包
 *
 * 后端 4 种响应形状:
 *   1. 裸数组      res = [..]                       (老接口/部分 list 端点)
 *   2. 单值包裹    res = {code, data: <obj|arr>}     (常规接口)
 *   3. 分页包裹    res = {code, data: {data:[..], total, current_page, ...}}  (paginate)
 *   4. 统计包裹    res = {code, data: {summary, low_stock, ...}}  (BI/看板)
 *
 * 使用:
 *   import { unwrapList, unwrapPaginate, unwrapItem, unwrapStats } from '@/utils/response'
 *
 *   // 列表
 *   const resp = await getStockRecords(params)
 *   const { list, total } = unwrapPaginate(resp)
 *   rows.value = list
 *
 *   // 数组 (非分页)
 *   const resp = await getTags()
 *   tags.value = unwrapList(resp)
 *
 *   // 单值
 *   const resp = await getCustomer(id)
 *   customer.value = unwrapItem(resp)
 *
 *   // 统计
 *   const resp = await getDashboardStats()
 *   const stats = unwrapStats(resp)
 */

/** 永远返回数组 — 用于"只要列表"的场景 */
export function unwrapList(res: Record<string, unknown>): Record<string, unknown>[] {
  // 形状 1: 裸数组
  if (Array.isArray(res)) return res
  // 形状 3: 分页包裹 — 取内层 data
  if (res?.data?.data && Array.isArray(res.data.data)) return res.data.data
  // 形状 2: 单值包裹且 data 是数组
  if (res?.data && Array.isArray(res.data)) return res.data
  // V1.2.10 fix: data 内层用 items/list/rows 字段 (供应商/施工发包等)
  if (res?.data?.items && Array.isArray(res.data.items)) return res.data.items
  if (res?.data?.list && Array.isArray(res.data.list)) return res.data.list
  if (res?.data?.rows && Array.isArray(res.data.rows)) return res.data.rows
  // 兜底: 可能是 {list: [...]} / {rows: [...]} / {items: [...]} (已解包一层)
  if (res?.list && Array.isArray(res.list)) return res.list
  if (res?.rows && Array.isArray(res.rows)) return res.rows
  if (res?.items && Array.isArray(res.items)) return res.items
  // 分页内层但少了外层 code 包装
  if (res?.data && Array.isArray(res.data?.data)) return res.data.data
  return []
}

/** 返回分页对象 — 用于表格分页场景，永远有 list + total */
export interface PaginatedResult {
  list: Record<string, unknown>[]
  total: number
  current_page?: number
  per_page?: number
  last_page?: number
  [key: string]: unknown
}

export function unwrapPaginate(res: Record<string, unknown>): PaginatedResult {
  // 形状 3: 分页包裹 {code, data: {data, total, ...}}
  if (res?.data?.data && Array.isArray(res.data.data)) {
    const p = res.data
    return {
      list: p.data,
      total: Number(p.total ?? p.data.length ?? 0),
      current_page: p.current_page,
      per_page: p.per_page,
      last_page: p.last_page,
      ...p, // 保留 from / to / path 等字段
    }
  }
  // V1.2.10 fix: data 内层用 items 字段 {code, data: {items, total}}
  if (res?.data?.items && Array.isArray(res.data.items)) {
    const p = res.data
    return {
      list: p.items,
      total: Number(p.total ?? p.items.length ?? 0),
      current_page: p.current_page ?? p.page,
      per_page: p.per_page,
      last_page: p.last_page,
    }
  }
  // 形状 1: 裸数组
  if (Array.isArray(res)) {
    return { list: res, total: res.length }
  }
  // 形状 2: 单值包裹且 data 是数组
  if (res?.data && Array.isArray(res.data)) {
    return { list: res.data, total: Number(res.total ?? res.data.length ?? 0) }
  }
  // 已经是 paginate 对象 (无 code 包装)
  if (res?.data && Array.isArray(res.data) && typeof res.total === 'number') {
    return { list: res.data, total: res.total }
  }
  // 兜底
  return { list: unwrapList(res), total: 0 }
}

/** 返回单个对象/值 — 用于详情接口 */
export function unwrapItem<T = any>(res: Record<string, unknown>): T {
  // 形状 2: 单值包裹
  if (res && typeof res === 'object' && 'code' in res && 'data' in res) {
    return res.data as T
  }
  // 已经是裸对象 / 裸值
  return res as T
}

/** 返回统计对象 — 用于 BI/看板接口，永远返回对象 */
export function unwrapStats<T = Record<string, unknown>>(res: Record<string, unknown>): T {
  // 形状 4: 统计包裹
  if (res && typeof res === 'object' && 'code' in res && 'data' in res) {
    return (res.data ?? {}) as T
  }
  // 已经是裸对象
  if (res && typeof res === 'object') return res as T
  return {} as T
}

/** 从响应里取 total 字段 — 用于只关心总数的场景 */
export function unwrapTotal(res: Record<string, unknown>): number {
  if (res?.data?.total != null) return Number(res.data.total)
  if (res?.total != null) return Number(res.total)
  if (Array.isArray(res?.data)) return res.data.length
  if (Array.isArray(res)) return res.length
  return 0
}

/**
 * 旧 API 兼容: 返回 {list, total, raw}
 * 用于从 fallback 模式逐步迁移到 helper 的过渡期
 * raw 保留原始响应, 万一需要其他字段
 */
export function unwrap(res: Record<string, unknown>): { list: Record<string, unknown>[]; total: number; raw: Record<string, unknown>; item: Record<string, unknown>; stats: Record<string, unknown> } {
  return {
    list: unwrapList(res),
    total: unwrapTotal(res),
    item: unwrapItem(res),
    stats: unwrapStats(res),
    raw: res,
  }
}
