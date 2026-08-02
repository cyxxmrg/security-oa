// customer/CustomerMap 子组件共享 types
// v0.3.24 从 views/customer/CustomerMap.vue 抽出

export interface MapCustomer {
  id: number
  name: string
  category?: 'vip' | 'normal' | 'potential' | string
  categoryLabel: 'VIP' | '普通' | '潜在'
  industry?: string
  city?: string
  address?: string
  contact?: string
  phone?: string
  longitude?: number
  latitude?: number
  projectCount?: number
  lastFollowAt?: string
  color: string
  mapX: number
  mapY: number
}

export type MapType = 'amap' | 'baidu' | 'tencent'

// ============ 枚举 ============

export const CATEGORY_OPTIONS = [
  { value: 'VIP', label: 'VIP客户' },
  { value: '普通', label: '普通客户' },
  { value: '潜在', label: '潜在客户' },
]

export const INDUSTRY_OPTIONS = [
  { value: '政府机关', label: '政府机关' },
  { value: '金融行业', label: '金融行业' },
  { value: '教育行业', label: '教育行业' },
  { value: '商业地产', label: '商业地产' },
]

export const MAP_TYPE_OPTIONS = [
  { value: 'amap', label: '高德地图' },
  { value: 'baidu', label: '百度地图' },
  { value: 'tencent', label: '腾讯地图' },
]

// ============ 工具函数 ============

const AVATAR_COLORS = ['#0C447C', '#1D9E75', '#BA7517', '#534AB7', '#A32D2D']
export const avatarColor = (id: number) => AVATAR_COLORS[id % AVATAR_COLORS.length]

export const categoryLabel = (c: string): 'VIP' | '普通' | '潜在' => {
  if (c === 'vip') return 'VIP'
  if (c === 'potential') return '潜在'
  return '普通'
}

export const categoryType = (cat: string): 'success' | 'info' | 'warning' => {
  if (cat === 'VIP') return 'warning'
  if (cat === '潜在') return 'info'
  return 'success'
}

export const mapTypeName = (t: MapType): string => {
  if (t === 'amap') return '高德地图'
  if (t === 'baidu') return '百度地图'
  return '腾讯地图'
}

// 经纬度转地图坐标的简单 hash（无真实经纬度时 fallback）
export function fakeXY(id: number): { x: number; y: number } {
  const seed = (id * 17 + 7) % 100
  return { x: 15 + (seed * 7) % 70, y: 15 + (seed * 13) % 70 }
}

// 把后端 customer 转成 map-ready 格式
export function normalizeCustomer(c: Record<string, unknown>): MapCustomer {
  const xy = c.longitude && c.latitude
    ? { x: 15 + ((c.longitude + 180) / 360) * 70, y: 15 + ((90 - c.latitude) / 180) * 70 }
    : fakeXY(c.id)
  return {
    ...c,
    color: avatarColor(c.id),
    mapX: Math.round(xy.x),
    mapY: Math.round(xy.y),
    contact: c.contact || (c.contacts && c.contacts[0]?.name) || '',
    phone: c.phone || (c.contacts && c.contacts[0]?.phone) || '',
    projectCount: c.project_count || 0,
    lastFollowAt: c.last_follow_at || '—',
    categoryLabel: categoryLabel(c.category),
  }
}
