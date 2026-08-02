import { ref, onUnmounted } from 'vue'
import { ElMessageBox, ElMessage } from 'element-plus'
import router from '@/router'
import { useUserStore } from '@/stores/user'
import { removeToken, removeUserInfo } from '@/utils/auth'
import { get as httpGet } from '@/utils/request'

/**
 * 用户无操作监控 + 自动登出
 *
 * 行为:
 *  - 监听鼠标点击/移动、键盘、滚动、触摸事件
 *  - 默认 30 分钟(从后端 system_settings 拉)无操作 → 自动登出
 *  - 最后 60 秒弹窗提示,可点"继续操作"重置
 *  - 路由在白名单(/login)时停止计时
 *
 * 配置来源优先级:
 *  1. 调用方传入 options (开发态/测试)
 *  2. 后端 GET /api/settings/idle-config (生产 - 由系统设置→数据管理维护)
 *  3. 内置默认 30 分钟 / 60 秒
 *
 * 关键设计:
 *  - 只在 window 上挂一个 listener,避免每页面都开计时
 *  - 倒计时用 setInterval 驱动,不是 setTimeout,这样暂停/恢复更精准
 *  - 弹窗用 ElMessageBox,关闭后重新计时
 *  - 配置异步加载,加载完成前不会误登出(用 DEFAULT)
 */

export interface IdleTimerOptions {
  /** 无操作超时时间(毫秒),不传则用后端配置 / 默认 */
  timeout?: number
  /** 提前多少毫秒弹窗提示 */
  warningBefore?: number
  /** 是否启用(可以在测试或特殊场景关掉) */
  enabled?: boolean
}

const DEFAULT_TIMEOUT = 30 * 60 * 1000 // 30 分钟
const DEFAULT_WARNING = 60 * 1000      // 提前 60 秒提示

// 标记是否已经启动全局监听(单例模式)
let globalListenerInstalled = false
let lastActivityTs = Date.now()
let tickTimer: number | null = null
let warningShown = false
let warningBox: Record<string, unknown> | null = null

// 当前生效的配置(由 syncIdleConfigFromServer 异步写入)
let currentEnabled = true
let currentTimeoutMs = DEFAULT_TIMEOUT
let currentWarningMs = DEFAULT_WARNING

// 防止并发拉配置
let configPromise: Promise<void> | null = null

const activityEvents = [
  'mousedown', 'mousemove', 'keydown', 'scroll',
  'touchstart', 'touchend', 'click', 'wheel',
  'pointerdown', 'pointermove'
]

// throttle: 至少 1 秒内只更新一次活动时间
let lastThrottleTs = 0
function onActivity() {
  const now = Date.now()
  if (now - lastThrottleTs < 1000) return
  lastThrottleTs = now
  lastActivityTs = now
}

function getTimeLeft(): number {
  return Math.max(0, currentTimeoutMs - (Date.now() - lastActivityTs))
}

function isWhitelisted(): boolean {
  const path = router.currentRoute.value.path
  return path === '/login' || path.startsWith('/error/') || path.startsWith('/legal/')
}

function doLogout(reason: string) {
  // 清理 store
  try {
    const userStore = useUserStore()
    userStore.logout()
  } catch { /* 忽略 */ }
  removeToken()
  removeUserInfo()

  ElMessage.warning(reason)
  const redirect = router.currentRoute.value.fullPath
  // 用 location 强制刷新,清空所有内存状态
  window.location.href = `/login?redirect=${encodeURIComponent(redirect)}&reason=idle`
}

function tick() {
  if (isWhitelisted()) return

  const timeLeft = getTimeLeft()

  // 已经超期 → 强制登出
  if (timeLeft <= 0) {
    stopIdleMonitor()
    doLogout('长时间未操作,已自动登出')
    return
  }

  // 弹窗时机
  if (timeLeft <= currentWarningMs && !warningShown) {
    warningShown = true
    const seconds = Math.ceil(timeLeft / 1000)
    const minutes = Math.floor((currentTimeoutMs - timeLeft) / 60000)
    ElMessageBox.confirm(
      `您已 ${minutes} 分钟无操作,${seconds} 秒后将自动登出。\n点击"继续操作"可保持登录。`,
      '会话即将过期',
      {
        confirmButtonText: '继续操作',
        cancelButtonText: '立即登出',
        type: 'warning',
        showClose: false,
        closeOnClickModal: false,
        closeOnPressEscape: false
      }
    )
      .then(() => {
        // 用户选择继续操作 → 重置计时
        lastActivityTs = Date.now()
        warningShown = false
      })
      .catch(() => {
        // 用户选择"立即登出" 或 关闭
        stopIdleMonitor()
        doLogout('已主动登出')
      })
      .finally(() => {
        warningBox = null
      })
  }
}

function startTickTimer() {
  stopTickTimer()
  tickTimer = window.setInterval(tick, 1000)
}

function stopTickTimer() {
  if (tickTimer) {
    clearInterval(tickTimer)
    tickTimer = null
  }
  warningShown = false
  if (warningBox) {
    warningBox.close?.()
    warningBox = null
  }
}

/**
 * 异步从后端拉最新配置
 * - 失败 / 未登录 走默认
 * - 同一时刻只发一个请求(去重)
 */
async function syncIdleConfigFromServer() {
  if (configPromise) return configPromise
  configPromise = (async () => {
    try {
      const res = await httpGet('/settings/idle-config')
      // 拦截器已解包: res = { enabled, timeout_minutes, warning_seconds, timeout_ms, warning_ms }
      if (res && typeof res === 'object' && typeof res.timeout_ms === 'number') {
        currentEnabled    = !!res.enabled
        currentTimeoutMs  = res.timeout_ms
        currentWarningMs  = typeof res.warning_ms === 'number' ? res.warning_ms : DEFAULT_WARNING
      }
    } catch (e) {
      // 静默失败:保留当前值 / 默认
      console.warn('[useIdleTimer] sync idle config failed, keep current', e)
    } finally {
      configPromise = null
    }
  })()
  return configPromise
}

export function startIdleMonitor(options: IdleTimerOptions = {}) {
  // 1. 安装全局事件监听(单例)
  if (!globalListenerInstalled) {
    activityEvents.forEach(evt => {
      window.addEventListener(evt, onActivity, { passive: true, capture: true })
    })
    globalListenerInstalled = true
  }

  // 2. 异步拉后端配置(options 显式传值时优先)
  if (options.timeout !== undefined) {
    currentTimeoutMs = options.timeout
  } else {
    syncIdleConfigFromServer()
  }
  if (options.warningBefore !== undefined) {
    currentWarningMs = options.warningBefore
  }
  if (options.enabled !== undefined) {
    currentEnabled = options.enabled
  }

  // 3. 如果禁用了,直接停掉(且不启动 tick)
  if (!currentEnabled) {
    stopTickTimer()
    return
  }

  // 4. 重置时间 + 启动 tick
  lastActivityTs = Date.now()
  warningShown = false
  startTickTimer()
}

export function stopIdleMonitor() {
  stopTickTimer()
  // 注意: 不要卸载全局 listener(卸载后下次启动还要重装)
}

export function resetIdleTimer(options: IdleTimerOptions = {}) {
  // 主动重置(比如登录成功后 / 配置改动后)
  if (options.timeout !== undefined) currentTimeoutMs = options.timeout
  if (options.warningBefore !== undefined) currentWarningMs = options.warningBefore
  if (options.enabled !== undefined) currentEnabled = options.enabled
  lastActivityTs = Date.now()
  warningShown = false
  if (warningBox) {
    warningBox.close?.()
    warningBox = null
  }
  // 配置变了 → 重新拉
  syncIdleConfigFromServer().finally(() => {
    if (currentEnabled) startTickTimer()
    else stopTickTimer()
  })
}

/** 暴露当前配置(供调试/展示) */
export function getCurrentIdleConfig() {
  return {
    enabled: currentEnabled,
    timeoutMs: currentTimeoutMs,
    warningMs: currentWarningMs,
  }
}

export function useIdleTimer(options: IdleTimerOptions = {}) {
  const enabled = ref(options.enabled ?? true)
  const timeLeft = ref(0)

  const interval = setInterval(() => {
    if (!enabled.value) return
    timeLeft.value = getTimeLeft()
  }, 1000)

  onUnmounted(() => {
    clearInterval(interval)
  })

  return {
    enabled,
    timeLeft,
    start: () => startIdleMonitor(options),
    stop: stopIdleMonitor,
    reset: () => resetIdleTimer(options)
  }
}
