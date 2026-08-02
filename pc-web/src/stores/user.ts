import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getToken, setToken, clearAuth, getUserInfo, setUserInfo } from '@/utils/auth'
import { post, get } from '@/utils/request'
import { usePermissionStore } from '@/utils/permission'
import router from '@/router'

export const useUserStore = defineStore('user', () => {
  const token = ref<string>(getToken() || '')
  const userInfo = ref<Record<string, unknown>>(getUserInfo() || null)
  const permissions = ref<string[]>([])
  const roles = ref<string[]>([])

  // 登录
  async function login(loginForm: { username: string; password: string }) {
    const res = await post('/auth/login', loginForm)
    // V0.6.3 axios 拦截器不再解包 {code,data} → res = {code,data:{token,user}} — 显式取 res.data
    const payload = res?.data ?? {}
    token.value = payload.token
    setToken(payload.token)
    if (payload.user) {
      userInfo.value = payload.user
      // V0.5.0: 同步从 /auth/me 拉 roles (后端 user 结构不含 roles)
      try {
        const me = await get('/auth/me')
        const meData = me?.data ?? me
        if (meData?.roles) {
          userInfo.value.roles = meData.roles
        }
        if (meData?.user) {
          // V1.1: 同步 is_system / user_type 字段 (路由守卫依赖)
          if (meData.user.user_type) userInfo.value.user_type = meData.user.user_type
          if (meData.user.is_system !== undefined) userInfo.value.is_system = meData.user.is_system
          // V1.2: 同步 must_change_password
          if (meData.user.must_change_password !== undefined) {
            userInfo.value.must_change_password = meData.user.must_change_password
          }
          Object.assign(userInfo.value, meData.user)
        }
      } catch (e) {
        // ignore — fallback username prefix
      }
      // V1.1: 兜底, 老 token 没 user_type 字段
      if (!userInfo.value.user_type) userInfo.value.user_type = 'business'
      if (userInfo.value.is_system === undefined) userInfo.value.is_system = false
      if (userInfo.value.must_change_password === undefined) userInfo.value.must_change_password = false
      // V1.2: 顶层标记也同步到 userInfo
      if (payload.force_change_password !== undefined) {
        userInfo.value.must_change_password = payload.force_change_password
      }
      if (payload.force_init_wizard !== undefined) {
        userInfo.value.system_initialized = !payload.force_init_wizard
      }
      setUserInfo(userInfo.value)
      // 触发 permission store 加载
      const permStore = usePermissionStore()
      permStore.reset()
      await permStore.load()
    }
    return res
  }

  // 获取用户信息
  async function getUserInfoAction() {
    try {
      const res = await get('/auth/userinfo')
      // V0.6.3: 显式取 res.data
      const payload = res?.data ?? {}
      userInfo.value = payload.user
      permissions.value = payload.permissions || []
      roles.value = payload.roles || []
      setUserInfo(payload.user)
      return res
    } catch (e: unknown) {
      // V1.2 fix: 兜底 — system 用户的 userType 已通过 /auth/login 顶层透出, 不应再卡死登录
      // 只在 /auth/userinfo 失败时静默回退到 login 阶段已写入的 userInfo, 不抛错
      console.warn('[userStore] getUserInfoAction failed, fallback to login data:', e?.message)
      return null
    }
  }

  // 退出登录
  async function logout() {
    try {
      await post('/auth/logout')
    } finally {
      clearAuth()
      token.value = ''
      userInfo.value = null
      permissions.value = []
      roles.value = []
      router.push('/login')
    }
  }

  // 检查权限
  function hasPermission(permission: string): boolean {
    return permissions.value.includes(permission)
  }

  function hasRole(role: string): boolean {
    return roles.value.includes(role)
  }

  return {
    token,
    userInfo,
    permissions,
    roles,
    login,
    getUserInfoAction,
    logout,
    hasPermission,
    hasRole
  }
})
