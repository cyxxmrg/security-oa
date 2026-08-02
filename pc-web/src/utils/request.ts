import axios, { type AxiosInstance, type AxiosRequestConfig, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { ElMessage } from 'element-plus'
import { getToken, removeToken } from './auth'
import router from '@/router'

export const service: AxiosInstance = axios.create({
  baseURL: '/api',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json'
  }
})

// 401 锁定状态 — 防止并发请求时重复弹框/跳页
let isHandling401 = false
function handle401(currentPath?: string) {
  if (isHandling401) return
  isHandling401 = true
  // 登录页/Login 接口触发的 401 — 不要踢回登录页（防止循环），把错误抛给调用方
  const isLoginPage = currentPath === '/login' || currentPath?.startsWith('/login?')
  if (isLoginPage) {
    isHandling401 = false
    return
  }
  ElMessage.error('登录已过期，请重新登录')
  removeToken()
  // 用 location 强制刷新,清掉一切残留状态(包括 Pinia store)
  const redirect = router.currentRoute.value.fullPath
  window.location.href = `/login?redirect=${encodeURIComponent(redirect)}`
}

// 请求拦截器
service.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = getToken()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    // FormData 上传时让浏览器自动设置 Content-Type（含 boundary）
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type']
    }
    // 关键: 清除 params 里无效值（undefined/NaN/null），避免 axios 序列化成 'undefined'/'NaN'/'null'
    if (config.params && typeof config.params === 'object') {
      const cleaned: Record<string, unknown> = {}
      for (const [k, v] of Object.entries(config.params)) {
        if (v === undefined || v === null) continue
        if (typeof v === 'number' && Number.isNaN(v)) continue
        if (typeof v === 'string' && (v === '' || v === 'undefined' || v === 'null' || v === 'NaN')) continue
        cleaned[k] = v
      }
      config.params = cleaned
    }
    // V0.4.6 B 数据权限: 用户选择"全部"时, 自动加 ?scope=all
    // 由 setScopeMode('all') 时写入 sessionStorage, 这里读取后追加
    try {
      const scopeMode = sessionStorage.getItem('oa:scopeMode')
      if (scopeMode === 'all' && config.params && !config.params.scope) {
        config.params.scope = 'all'
      }
    } catch { /* sessionStorage 可能不可用, 忽略 */ }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 响应拦截器
service.interceptors.response.use(
  (response: AxiosResponse) => {
    const res = response.data
    if (res && typeof res === 'object' && 'code' in res && res.code !== 0 && res.code !== 200) {
      // 401: token 失效(包在 200 响应里) — 静默踢回登录
      if (res.code === 401) {
        handle401()
        return Promise.reject(new Error(res.message || '未认证'))
      }
      ElMessage.error(res.message || '请求失败')
      return Promise.reject(new Error(res.message || 'Error'))
    }
    // V0.6.3 不再自动解包 {code,data}, 调用方必须 await ... .data 或 res.data 取值
    // 业务错误(code !== 0/200/401)已在上面 reject, 这里直接返回原始 res
    return res
  },  (error) => {
    const status = error.response?.status
    const message = error.response?.data?.message || error.message || '网络异常'
    const currentPath = router.currentRoute.value.path
    const isLoginPage = currentPath === '/login' || currentPath?.startsWith('/login?')

    // V1.2 fix: system 用户访问业务 API 会拿到 403 + "系统账号不能操作业务数据"
    // 这种是设计内行为 (路由守卫已经做过提示), 不应再弹错误 toast 二次提示
    const isSystemAccount403 = status === 403
      && (message?.includes('系统账号') || message?.includes('业务数据'))
      && !isLoginPage

    // 401: token 失效(HTTP 状态码) — 静默踢回登录（登录页除外，避免死循环）
    if (status === 401) {
      handle401(currentPath)
    } else if (status === 500 || status === 502 || status === 503 || status === 504) {
      // 5xx: 服务异常 → 跳 500 页面（登录页除外，直接抛错）
      if (isLoginPage) {
        // 登录页不跳错误页，错误由调用方处理
      } else {
        ElMessage.error('服务暂时不可用,正在跳转错误页...')
        if (router.currentRoute.value.path !== '/error/500') {
          router.push('/error/500')
        }
      }
    } else if (!error.response) {
      // 无响应 = 网络断开
      if (!isLoginPage) {
        ElMessage.error('网络连接断开')
        if (router.currentRoute.value.path !== '/error/network') {
          router.push('/error/network')
        }
      }
    } else if (!isSystemAccount403) {
      if (!isLoginPage) ElMessage.error(message)
    }
    // 统一给错误对象加上 status 字段（供调用方精细化处理）
    ;(error as Record<string, unknown>).status = status
    ;(error as Record<string, unknown>).serverMessage = message
    return Promise.reject(error)
  }
)

// 默认导出实例（保持兼容），同时也可以命名导入
export default service

/**
 * V0.4.6 — 设置当前 scope 模式 (列表页右上角 radio 切换)
 *  - 'mine' (默认): 只看自己 + 参与
 *  - 'all'        : 全量 (仅 admin/finance 允许, 其他人点会 403)
 */
export function setScopeMode(mode: 'mine' | 'all') {
  try { sessionStorage.setItem('oa:scopeMode', mode) } catch { /* */ }
}
export function getScopeMode(): 'mine' | 'all' {
  try {
    const v = sessionStorage.getItem('oa:scopeMode')
    return v === 'all' ? 'all' : 'mine'
  } catch { return 'mine' }
}
export function get<T = any>(url: string, params?: any, config?: AxiosRequestConfig): Promise<T> {
  return service.get(url, { params, ...config }) as Promise<T>
}

export function post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
  return service.post(url, data, config) as Promise<T>
}

export function put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
  return service.put(url, data, config) as Promise<T>
}

export function del<T = any>(url: string, params?: any, config?: AxiosRequestConfig): Promise<T> {
  return service.delete(url, { params, ...config }) as Promise<T>
}

export function patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
  return service.patch(url, data, config) as Promise<T>
}
