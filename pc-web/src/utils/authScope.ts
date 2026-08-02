/**
 * V0.4.6 B 数据权限 — 前端角色判定
 *
 * 复用后端 AuthScope::classify() 逻辑 (PC 端按 username 前缀):
 *  - admin*    → admin   (全量)
 *  - fin_*     → finance (全量)
 *  - sales_* / tech_mgr / proj_mgr / sales_mgr → manager
 *  - 其他     → user
 */
export type UserRole = 'admin' | 'finance' | 'manager' | 'user'

/** 全量通过的角色 (admin/finance 一律放行) */
export const UNRESTRICTED_ROLES: UserRole[] = ['admin', 'finance']

/**
 * 根据当前 userInfo 判定角色
 *  优先看 userInfo.roles (后端返回), 兜底用 username 前缀
 */
export function classifyRole(user: Record<string, unknown>): UserRole {
  if (!user) return 'user'
  const roles: string[] = Array.isArray(user.roles) ? user.roles : []
  if (roles.includes('admin')) return 'admin'
  if (roles.includes('finance')) return 'finance'
  if (roles.includes('manager')) return 'manager'
  // 兜底: username 前缀
  const username = String(user.username || '')
  if (username === 'admin' || username.startsWith('admin')) return 'admin'
  if (username.startsWith('fin_')) return 'finance'
  if (username.startsWith('sales_') || ['tech_mgr', 'proj_mgr', 'sales_mgr'].includes(username)) {
    return 'manager'
  }
  return 'user'
}

export function isAdmin(user: Record<string, unknown>): boolean   { return classifyRole(user) === 'admin' }
export function isFinance(user: Record<string, unknown>): boolean { return classifyRole(user) === 'finance' }
export function isManager(user: Record<string, unknown>): boolean { return classifyRole(user) === 'manager' }
export function isUnrestricted(user: Record<string, unknown>): boolean {
  return UNRESTRICTED_ROLES.includes(classifyRole(user))
}

/** 是否允许"看全部"scope 切换 (只有 admin/finance 允许) */
export function canViewAll(user: Record<string, unknown>): boolean {
  return isUnrestricted(user)
}
