// 甘特图共享类型 + 工具函数 — v0.3.14 C3

export type TaskStatus = 'done' | 'in-progress' | 'todo' | 'delayed'

export interface GanttTask {
  name: string
  category: string
  startDate: string
  endDate: string
  duration: number
  progress: number
  owner: string
  status: TaskStatus
  isMilestone?: boolean
  startOffset: number
}

export interface GanttDate {
  day: number
  month: number
  date: Date
  full: string
}

export const STATUS_COLOR: Record<TaskStatus, string> = {
  done: '#1D9E75',
  'in-progress': '#0C447C',
  delayed: '#A32D2D',
  todo: '#909399',
}

export const STATUS_LABEL: Record<TaskStatus, string> = {
  done: '已完成',
  'in-progress': '进行中',
  delayed: '延期',
  todo: '未开始',
}

export const STATUS_TAG_TYPE: Record<TaskStatus, 'success' | 'primary' | 'info' | 'danger'> = {
  done: 'success',
  'in-progress': 'primary',
  delayed: 'danger',
  todo: 'info',
}

/** 渲染进度条状态：完成/延期按状态取色，进度按区间取色 */
export const progressStatusOf = (t: GanttTask): 'success' | 'warning' | 'exception' | '' => {
  if (t.status === 'done') return 'success'
  if (t.status === 'delayed') return 'exception'
  if (t.progress >= 60) return 'success'
  if (t.progress >= 30) return 'warning'
  return ''
}

export const cellWidth = 40
