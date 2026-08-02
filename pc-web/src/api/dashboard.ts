import { get, post } from '@/utils/request'

export function getWorkbenchData() {
  return get('/dashboard/workbench')
}

export function getDashboardData() {
  return get('/dashboard/stats')
}

export function getTodoList(params?: Record<string, unknown>) {
  return get('/dashboard/todo', params)
}

export function getProjectProgress() {
  return get('/dashboard/project-progress')
}

export function getServiceStats() {
  return get('/dashboard/service-stats')
}

export function getRevenueTrend(params?: Record<string, unknown>) {
  return get('/dashboard/revenue-trend', params)
}

// V0.4.5 新增 - 8 图块总览
export function getOverview() {
  return get('/dashboard/overview')
}

export function getWarrantyStats() {
  return get('/dashboard/warranty-stats')
}
