// customer/Detail 子组件共享 types
// v0.3.20 从 views/customer/Detail.vue 抽出

export interface Customer {
  id: number
  name: string
  credit_code?: string
  industry?: string
  category?: 'vip' | 'normal' | 'potential' | string
  source?: string
  status?: 'active' | 'inactive' | string
  province?: string
  city?: string
  district?: string
  address?: string
  longitude?: number
  latitude?: number
  tags?: string[]
  description?: string
  contacts?: Contact[]
  projects?: Project[]
  devices?: Device[]
  service_orders?: ServiceOrder[]
  receivables?: Receivable[]
  invoice_infos?: InvoiceInfo[]
}

export interface InvoiceInfo {
  id: number
  customer_id: number
  invoice_type: 'general' | 'special' | 'electronic' | string
  company_name: string
  tax_no: string
  register_address?: string
  register_phone?: string
  bank_name?: string
  bank_account?: string
  is_default?: boolean
  remark?: string
}

export interface Contact {
  id: number
  name: string
  position?: string
  phone?: string
  email?: string
  wechat?: string
  is_primary?: boolean
  notes?: string
}

export interface Project {
  id: number
  code?: string
  name: string
  type?: string
  stage?: string
  amount?: number
  start_date?: string
  end_date?: string
}

export interface Device {
  id: number
  device_name: string
  device_type?: string
  brand?: string
  model?: string
  serial_number?: string
  install_location?: string
  install_date?: string
  status?: string
}

export interface ServiceOrder {
  id: number
  code?: string
  type?: string
  title?: string
  priority?: string
  engineer?: string
  status?: string
}

export interface Receivable {
  id: number
  amount: number
  due_date?: string
  status?: string
}

export interface FollowRecord {
  id: number
  type: string  // visit/phone/wechat/email/other
  content: string
  user?: { name?: string; username?: string }
  next_follow_up_date?: string
  next_follow_up_note?: string
  created_at: string
}

export interface FollowForm {
  type: string
  content: string
  next_follow_up_date: string | null
  next_follow_up_note: string
}

// ============ 枚举/常量 ============

export const FOLLOW_TYPE_OPTIONS = [
  { value: 'phone', label: '电话拜访' },
  { value: 'visit', label: '上门拜访' },
  { value: 'wechat', label: '微信沟通' },
  { value: 'email', label: '邮件' },
  { value: 'other', label: '其他' },
]

// ============ 工具函数 ============

const AVATAR_COLORS = ['#0C447C', '#1D9E75', '#BA7517', '#534AB7', '#A32D2D']
export const avatarColor = (id: number) => AVATAR_COLORS[(id || 0) % AVATAR_COLORS.length]

export const displayCategory = (c: string): string => {
  if (c === 'vip') return 'VIP'
  if (c === 'potential') return '潜在'
  return '普通'
}

export const formatDate = (s: string): string => s ? s.replace('T', ' ').slice(0, 16) : '—'

// ============ 状态 tag 类型工具 ============

export type TagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'

export const categoryType = (cat: string): TagType => {
  if (cat === 'VIP') return 'warning'
  if (cat === '潜在') return 'info'
  return 'success'
}

const STAGE_TYPE_MAP: Record<string, TagType> = {
  立项: 'primary', 询价: 'info', 合同: 'warning', 采购: 'warning',
  施工: 'danger', 结算: 'success', 质保: 'success',
}
export const stageType = (s: string): TagType => STAGE_TYPE_MAP[s] || 'info'

export const deviceStatusType = (s: string): TagType => {
  if (s === '在线' || s === 'active' || s === '正常') return 'success'
  if (s === '故障' || s === 'offline') return 'danger'
  return 'info'
}

export const priorityType = (p: string): TagType => {
  if (p === '高' || p === 'urgent') return 'danger'
  if (p === '中' || p === 'high') return 'warning'
  return 'info'
}

export const serviceStatusType = (s: string): TagType => {
  if (s === '已完成' || s === 'completed') return 'success'
  if (s === '处理中' || s === 'in_progress') return 'warning'
  return 'info'
}

const TIMELINE_TYPE_MAP: Record<string, TagType> = {
  visit: 'primary', call: 'info', phone: 'info',
  online: 'warning', wechat: 'warning', email: 'warning',
  other: 'success',
}
export const timelineType = (t: string): TagType => TIMELINE_TYPE_MAP[t] || 'info'

const TYPE_LABEL_MAP: Record<string, string> = {
  visit: '上门拜访', call: '电话拜访', phone: '电话拜访',
  online: '在线沟通', wechat: '微信', email: '邮件', other: '其他',
}
export const typeLabel = (t: string): string => TYPE_LABEL_MAP[t] || t

// ============ 开票信息 枚举/工具 ============

export const INVOICE_TYPE_OPTIONS = [
  { value: 'general', label: '增值税普通发票' },
  { value: 'special', label: '增值税专用发票' },
  { value: 'electronic', label: '电子发票' },
]

export const invoiceTypeLabel = (t: string): string =>
  INVOICE_TYPE_OPTIONS.find((o) => o.value === t)?.label || t || '—'

export const invoiceTypeTagType = (t: string): TagType => {
  if (t === 'special') return 'warning'
  if (t === 'electronic') return 'primary'
  return 'info'
}
