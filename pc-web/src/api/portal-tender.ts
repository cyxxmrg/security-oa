/**

 * V0.6.0 招标中心 — 供应商公开门户 API
 *
 * ⚠️ 这组端点是免登录的, 路由层 noAuth, 不能走 utils/request (会带空 Bearer)
 * 全部用 fetch 直连 /api/portal/...
 */

// 通用 API 负载类型 — 替代 any, 强制调用方做类型守卫
export type ApiPayload = Record<string, unknown>;

async function postJson<T = any>(url: string, data?: ApiPayload): Promise<T> {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: data ? JSON.stringify(data) : undefined,
  })
  const json = await res.json()
  if (json.code !== 0) {
    const err = new Error(json.message || '请求失败') as Error & { code?: number; data?: unknown }
    err.code = json.code
    err.data = json.data
    throw err
  }
  return json.data
}

async function getJson<T = any>(url: string): Promise<T> {
  const res = await fetch(url, { method: 'GET' })
  const json = await res.json()
  if (json.code !== 0) {
    const err = new Error(json.message || '请求失败') as Error & { code?: number; data?: unknown }
    err.code = json.code
    throw err
  }
  return json.data
}

export interface PublicTender {
  id: number
  code: string
  name: string
  description?: string
  type: string
  status: string
  status_label?: string
  required_items?: Record<string, unknown>[]
  deadline?: string
  open_at?: string
  project?: { id: number; name: string }
  attachments?: Array<{ id: number; name: string; url: string; size: number; mime: string; category: string }>
  public_token: string
}

export interface PublicBid {
  id: number
  code: string
  tender_project_id: number
  supplier_id: number
  total_amount: number
  lead_time_days?: number
  technical_proposal?: string
  remark?: string
  status: string
  status_label?: string
  submitted_at?: string
  items?: Array<{ id: number; name: string; spec?: string; unit?: string; quantity: number; unit_price: number; total_price: number }>
}

export const portalApi = {
  /** 通过 token 拿招标公开信息 */
  getTender: (token: string) => getJson<PublicTender>(`/api/portal/t/${token}`),

  /** 我方对该招标的投标 (cookie/session 关联 supplier_id) */
  myBid: (token: string, supplierId: number) =>
    getJson<PublicBid>(`/api/portal/t/${token}/my-bid?supplier_id=${supplierId}&phone_suffix=${encodeURIComponent(sessionStorage.getItem('portal_phone_suffix') || '')}`),

  /** 提交/更新投标 */
  submitBid: (token: string, data: {
    supplier_id: number
    total_amount: number
    lead_time_days?: number
    technical_proposal?: string
    remark?: string
    phone_suffix?: string
    items?: Array<{ name: string; spec?: string; unit?: string; quantity: number; unit_price: number }>
  }) => postJson<PublicBid>(`/api/portal/t/${token}/bids`, {
    ...data,
    phone_suffix: data.phone_suffix || sessionStorage.getItem('portal_phone_suffix') || '',
  }),

  /** 供应商用手机号查自己的邀请 */
  listInvitations: (phone: string) => getJson<{
    supplier: { id: number; name: string; phone: string } | null
    invitations: Array<{ id: number; code: string; name: string; status: string; deadline: string; public_token: string }>
  }>(`/api/portal/invitations?phone=${encodeURIComponent(phone)}`),

  /** V0.6.3 供应商门户首页: 供应商档案 + 历史投标 + 简报 */
  supplierInfo: (phone: string) => getJson<{
    supplier: { id: number; code: string; name: string; phone: string; email?: string; type?: string; status?: string; rating?: number } | null
    stats: { invitation_count: number; bid_count: number; won_count: number; active_tender: number }
    bids: Array<{ id: number; tender_id: number; total_amount: number; status: string; created_at: string }>
  }>(`/api/portal/supplier/info?phone=${encodeURIComponent(phone)}`),
}
