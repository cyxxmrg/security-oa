import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import zhCn from 'element-plus/dist/locale/zh-cn.mjs'
import 'element-plus/dist/index.css'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'
import App from './App.vue'
import router from './router'
// 闲置监控 composable — 这里导入,让 module 提早加载
import { stopIdleMonitor } from '@/composables/useIdleTimer'
// V0.5.0 L1+L2 授权中心
import { permissionDirective, usePermissionStore } from '@/utils/permission'
import './styles/index.scss'

const app = createApp(App)

// 注册所有 Element Plus 图标
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
}

app.use(createPinia())
app.use(router)
app.use(ElementPlus, { locale: zhCn, size: 'default' })

// V0.5.0 注册 v-permission 指令 (L1 菜单/L2 按钮)
app.directive('permission', permissionDirective)

// V0.5.0 路由守卫 — meta.permission 校验, 没权限的路由直接 reject
router.beforeEach(async (to, from, next) => {
  const permStore = usePermissionStore()
  if (to.meta?.permission) {
    if (!permStore.loaded) {
      await permStore.load()
    }
    if (!permStore.hasPermission(to.meta.permission as string)) {
      // 没权限 → 跳到 403
      next({ name: 'Forbidden' })
      return
    }
  }
  next()
})

// 浏览器关闭/刷新前清理(避免 tick 继续跑)
window.addEventListener('beforeunload', () => {
  stopIdleMonitor()
})

// V0.5.7 块6 — 注册 Service Worker (PWA)
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .then((reg) => console.info('[PWA] Service Worker registered:', reg.scope))
      .catch((err) => console.warn('[PWA] Service Worker registration failed:', err))
  })
}

app.mount('#app')
