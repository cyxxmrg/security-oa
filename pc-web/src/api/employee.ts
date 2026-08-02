import { get } from '@/utils/request'

// 仅保留销售线索/商机 owner 下拉用到的最小集
export function getEmployeeList(params?: Record<string, unknown>) { return get('/employees', params) }
