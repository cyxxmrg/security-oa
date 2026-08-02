import { get, post, put, del } from '@/utils/request'

// ===================== 项目预算 (Construction Budget) 8 端点 =====================
export const construction = {
  // 预算列表
  listBudgets: (params: Record<string, unknown>) => get('/construction/budgets', params),
  getBudgetSummary: (projectId: number) => get(`/construction/budgets/summary/${projectId}`),
  getBudget: (id: number) => get(`/construction/budgets/${id}`),
  createBudget: (data: unknown) => post('/construction/budgets', data),
  updateBudget: (id: number, data: unknown) => put(`/construction/budgets/${id}`, data),
  approveBudget: (id: number) => post(`/construction/budgets/${id}/approve`),
  reviseBudget: (id: number, data: unknown) => post(`/construction/budgets/${id}/revise`, data),
  deleteBudget: (id: number) => del(`/construction/budgets/${id}`),
}

// ===================== V0.4.3 施工团队 (Teams) 7 端点 =====================
export const teamApi = {
  list:        (params: Record<string, unknown>) => get('/construction/teams', params),
  show:        (id: number) => get(`/construction/teams/${id}`),
  create:      (data: unknown) => post('/construction/teams', data),
  update:      (id: number, data: unknown) => put(`/construction/teams/${id}`, data),
  remove:      (id: number) => del(`/construction/teams/${id}`),
  addMembers:  (id: number, members: Record<string, unknown>[]) => post(`/construction/teams/${id}/members`, { members }),
  removeMember:(id: number, memberId: number) => del(`/construction/teams/${id}/members/${memberId}`),
}

// ===================== V0.4.3 开工单 (Commencement Orders) 7 端点 =====================
export const commencementApi = {
  list:     (params: Record<string, unknown>) => get('/construction/commencement-orders', params),
  show:     (id: number) => get(`/construction/commencement-orders/${id}`),
  create:   (data: unknown) => post('/construction/commencement-orders', data),
  update:   (id: number, data: unknown) => put(`/construction/commencement-orders/${id}`, data),
  approve:  (id: number) => post(`/construction/commencement-orders/${id}/approve`),
  start:    (id: number) => post(`/construction/commencement-orders/${id}/start`),
  complete: (id: number) => post(`/construction/commencement-orders/${id}/complete`),
}

// ===================== V0.4.3 施工日志 (Logs) 7 端点 =====================
export const logApi = {
  list:           (params: Record<string, unknown>) => get('/construction/logs', params),
  show:           (id: number) => get(`/construction/logs/${id}`),
  create:         (data: unknown) => post('/construction/logs', data),
  update:         (id: number, data: unknown) => put(`/construction/logs/${id}`, data),
  submit:         (id: number) => post(`/construction/logs/${id}/submit`),
  updateProgress: (id: number, data: unknown) => post(`/construction/logs/${id}/progress`, data),
  overdue:        (params: Record<string, unknown>) => get('/construction/logs/overdue', params),
}

// ===================== V0.4.3 整改工单 (Rectifications) 4 端点 — V0.4.4 占位 =====================
export const rectificationApi = {
  list:     (params: Record<string, unknown>) => get('/construction/rectifications', params),
  show:     (id: number) => get(`/construction/rectifications/${id}`),
  create:   (data: unknown) => post('/construction/rectifications', data),
  complete: (id: number, data?: unknown) => post(`/construction/rectifications/${id}/complete`, data),
}

// ===================== V0.4.3 工序字典 (Work Processes) 4 端点 =====================
export const workProcessApi = {
  list:   (params: Record<string, unknown>) => get('/construction/work-processes', params),
  create: (data: unknown) => post('/construction/work-processes', data),
  update: (id: number, data: unknown) => put(`/construction/work-processes/${id}`, data),
  remove: (id: number) => del(`/construction/work-processes/${id}`),
}

// ===================== V0.4.3 施工发包 (External Works) 7 端点 =====================
export const externalWorkApi = {
  list:     (params: Record<string, unknown>) => get('/construction/external-works', params),
  show:     (id: number) => get(`/construction/external-works/${id}`),
  create:   (data: unknown) => post('/construction/external-works', data),
  update:   (id: number, data: unknown) => put(`/construction/external-works/${id}`, data),
  close:    (id: number) => post(`/construction/external-works/${id}/close`),
  submitBid:(id: number, data: unknown) => post(`/construction/external-works/${id}/bids`, data),
  listBids: (id: number) => get(`/construction/external-works/${id}/bids`),
  award:    (id: number, data: unknown) => post(`/construction/external-works/${id}/award`, data),
}
