// 招标详情页纯函数工具 (格式化 / tag 颜色映射)
// 从 Detail.vue 抽出, 子组件共用

export type TagType = '' | 'success' | 'warning' | 'info' | 'primary' | 'danger'

export const typeLabel = (t?: string) =>
  t === 'tender' ? '招标' : t === 'rfq' ? '询价' : t === 'negotiation' ? '议价' : '-'

export const statusTag = (s: string): TagType =>
  (({
    draft: 'info', pending_review: 'warning', open: 'success', awarded: 'success',
    closed: '', cancelled: 'danger', withdrawn: 'info', rejected: 'danger',
    bidding: 'warning', evaluating: 'primary',
  } as Record<string, TagType>)[s] || '')

export const bidStatusTag = (s: string): TagType =>
  (({
    draft: 'info', submitted: 'primary', shortlisted: 'warning',
    awarded: 'success', rejected: 'danger', withdrawn: 'info',
  } as Record<string, TagType>)[s] || '')

export const depositStatusTag = (s: string): TagType =>
  (({
    pending: 'info', paid: 'success', refunded: '', forfeited: 'danger', partial_refund: 'warning',
  } as Record<string, TagType>)[s] || '')

export const fmt = (s?: string) => (s ? s.replace('T', ' ').slice(0, 16) : '-')

export const formatSize = (b?: number) => (b ? (b / 1024).toFixed(1) + ' KB' : '-')
