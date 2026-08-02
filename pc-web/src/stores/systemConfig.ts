import { defineStore } from 'pinia'
import { ref, reactive, computed, watch } from 'vue'
import { get as httpGet, put as httpPut } from '@/utils/request'

/** 系统设置（标题/版权/备案号/公告/联系邮箱） */
export interface SystemSettings {
  /** 应用版本号 — 从后端 /settings 的 version 字段注入，源在 pc-api/config/oa.php::app_version */
  version: string
  system_name: string
  system_short_name: string
  copyright: string
  copyright_url: string
  announcement: string
  icp: string
  contact_email: string
}

const DEFAULT_SETTINGS: SystemSettings = {
  // 版本号兜底 (与 pc-api/config/oa.php::app_version 同步 — 首屏渲染就显示正确版本)
  // 部署前用 .workbuddy/sync_version.py 自动同步
  version: 'v1.4.1',
  system_name: '安防运维OA办公系统',
  system_short_name: '安防OA',
  copyright: '@2026zsk',
  copyright_url: 'https://www.example.com',
  announcement: '',
  icp: '粤ICP备2026000000号-1',
  contact_email: 'admin@example.com',
}

const STORAGE_KEY = 'oa-system-config'

function loadFromLocal(): SystemSettings {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const cached = JSON.parse(raw)
      // V1.4.1: 缓存优先 — 缓存里是后端最后写入的真实设置(含版本号),
      // 不再因"版本号与默认不一致"丢弃缓存(旧逻辑导致每次刷新都回退默认版本号)
      if (cached && typeof cached === 'object' && typeof cached.version === 'string') {
        return { ...DEFAULT_SETTINGS, ...cached }
      }
    }
  } catch (e) { /* noop */ }
  return { ...DEFAULT_SETTINGS }
}

export const useSystemConfigStore = defineStore('systemConfig', () => {
  // 本地 reactive（用于"标题编辑"实时预览）
  const settings = reactive<SystemSettings>(loadFromLocal())

  /** 拉取后端最新设置（应用启动时调一次） */
  async function fetchSettings() {
    try {
      const res = await httpGet('/settings')
      // request.ts 返回 {code:0, data:{system_name, copyright, ...}}, 需要解包 data
      const payload = res?.data ?? res
      if (payload && typeof payload === 'object') {
        Object.assign(settings, DEFAULT_SETTINGS, payload)
        // 持久化到 localStorage 作离线缓存
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(settings)) } catch (e) { /* noop */ }
      }
    } catch (e) {
      // 离线或未登录 — 用本地缓存
      console.warn('[systemConfig] fetch failed, use local cache', e)
    }
  }

  /** 写回后端 */
  async function saveSettings(patch: Partial<SystemSettings>): Promise<boolean> {
    try {
      Object.assign(settings, patch)
      const res = await httpPut('/settings', patch)
      // request.ts 返回 {code:0, data:{...}}, 需要解包 data
      const payload = res?.data ?? res
      if (payload && typeof payload === 'object') {
        // 同步最新值到 store + localStorage
        Object.assign(settings, payload)
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(settings)) } catch (e) { /* noop */ }
        return true
      }
      return false
    } catch (e) {
      console.error('[systemConfig] save failed', e)
      return false
    }
  }

  /** 短名称（用于侧边栏 / 大屏） */
  const shortName = computed(() => {
    const n = settings.system_short_name || settings.system_name || 'OA'
    return n.length > 8 ? n.slice(0, 8) : n
  })

  // 兼容旧字段（sysConfig.systemName）— 旧代码仍可能读
  const sysConfig = computed(() => ({
    systemName: settings.system_name,
    shortName: shortName.value,
  }))

  return {
    settings,
    sysConfig,
    shortName,
    fetchSettings,
    saveSettings,
  }
})
