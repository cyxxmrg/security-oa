/**
 * V0.5.0 L1+L2 前端授权
 *
 * 提供:
 *  - permissionStore  (Pinia, 存当前用户权限列表)
 *  - v-permission     (自定义指令, 隐藏无权限的菜单/按钮)
 *  - hasPermission()  (工具函数)
 *
 * 流程:
 *  1) 登录后从 /api/auth/me 拿用户信息 (含 roles 列表)
 *  2) 从 /api/permissions/my 拿所有权限 name
 *  3) 缓存到 permissionStore
 *  4) 组件用 v-permission="'project.view'" 或 :disabled="!hasPermission('x')"
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getUserInfo } from './auth'
import http from './request'

export const usePermissionStore = defineStore('permission', () => {
  const permissions = ref<string[]>([])
  const roles = ref<string[]>([])
  const loaded = ref(false)

  async function load() {
    if (loaded.value) return
    try {
      const user = getUserInfo()
      if (user && Array.isArray(user.roles)) {
        // 兼容两种形态: ['admin','user'] 或 [{name:'admin'}, {name:'user'}]
        roles.value = user.roles.map((r: Record<string, unknown>) => (typeof r === 'string' ? r : r?.name)).filter(Boolean)
      }
      const res = await http.get('/permissions/my')
      const list = Array.isArray(res) ? res : (res?.data || [])
      permissions.value = list.map((p: Record<string, unknown>) => typeof p === 'string' ? p : p.name).filter(Boolean)
      loaded.value = true
    } catch (e) {
      // 静默失败 — 视为无权限
      permissions.value = []
      loaded.value = true
    }
  }

  function hasPermission(perm: string): boolean {
    if (roles.value.includes('admin') || roles.value.includes('system_admin')) return true
    if (permissions.value.includes(perm)) return true
    // 兼容 'customer.view' → 'customer' 这种 DB 里只存 module name 的旧 perms
    const [module] = perm.split('.')
    if (module && permissions.value.includes(module)) return true
    return false
  }

  function hasAnyPermission(perms: string[]): boolean {
    if (roles.value.includes('admin') || roles.value.includes('system_admin')) return true
    return perms.some(p => hasPermission(p))
  }

  function reset() {
    permissions.value = []
    roles.value = []
    loaded.value = false
  }

  return { permissions, roles, loaded, load, hasPermission, hasAnyPermission, reset }
})

/**
 * 全局函数 (供模板用, 避免每次 import store)
 * 优先级: admin > 显式权限 > false
 */
export function hasPermission(perm: string): boolean {
  const store = usePermissionStore()
  return store.hasPermission(perm)
}

/**
 * v-permission 指令
 *
 * 用法:
 *   <el-button v-permission="'project.create'">新建</el-button>
 *   <div v-permission="['project.view', 'project.edit']">有任一权限即显示</div>
 *
 * 修饰符:
 *   .any  默认: 数组 = 全部需满足
 *   .or   数组 = 任一满足
 *
 *   <el-button v-permission.or="['x', 'y']">有任一权限即显示</el-button>
 */
export const permissionDirective = {
  mounted(el: HTMLElement, binding: { value: string | string[]; modifiers?: Record<string, boolean> }) {
    applyPermission(el, binding)
  },
  updated(el: HTMLElement, binding: { value: string | string[]; modifiers?: Record<string, boolean> }) {
    applyPermission(el, binding)
  },
}

function applyPermission(el: HTMLElement, binding: { value: string | string[]; modifiers?: Record<string, boolean> }) {
  const { value, modifiers } = binding
  const perms = Array.isArray(value) ? value : [value]
  const store = usePermissionStore()

  // admin / system_admin 永远显示
  if (store.roles.includes('admin') || store.roles.includes('system_admin')) return

  const isOrMode = modifiers?.or
  const has = isOrMode
    ? perms.some(p => store.permissions.includes(p))
    : perms.every(p => store.permissions.includes(p))

  if (!has) {
    el.style.display = 'none'
    el.setAttribute('aria-hidden', 'true')
  } else {
    el.style.display = ''
    el.removeAttribute('aria-hidden')
  }
}
