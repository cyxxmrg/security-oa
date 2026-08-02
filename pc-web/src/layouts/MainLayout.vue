<template>
  <el-container class="main-layout">
    <!-- 侧边栏 -->
    <el-aside :width="appStore.sidebarCollapsed ? '64px' : '240px'" class="sidebar">
      <div class="logo-bar">
        <div class="logo" @click="router.push('/')">
          <div class="logo-icon">OA</div>
          <transition name="fade">
            <span v-if="!appStore.sidebarCollapsed" class="logo-text">{{ systemConfigStore.shortName }}</span>
          </transition>
        </div>
        <!-- 企业邮箱入口 — 链接到 mail.example.com, 新标签页打开 -->
        <el-tooltip content="企业邮箱 (mail.example.com)" placement="bottom">
          <a
            class="email-entry"
            :class="{ 'is-loading': emailLoading, 'is-disabled': emailDisabled }"
            :href="emailDisabled || emailLoading ? 'javascript:void(0)' : 'https://mail.example.com'"
            target="_blank"
            rel="noopener noreferrer"
            :aria-disabled="emailDisabled"
            :tabindex="emailDisabled ? -1 : 0"
            @click="onEmailClick"
          >
            <el-icon v-if="emailLoading" :size="18" class="email-entry__icon is-spin">
              <LoadingIcon />
            </el-icon>
            <el-icon v-else :size="18" class="email-entry__icon">
              <MessageIcon />
            </el-icon>
          </a>
        </el-tooltip>
      </div>
      <el-scrollbar>
        <el-menu
          :default-active="activeMenu"
          :collapse="appStore.sidebarCollapsed"
          :collapse-transition="false"
          :unique-opened="false"
          router
          background-color="#0C447C"
          text-color="rgba(255,255,255,0.75)"
          active-text-color="#fff"
        >
          <template v-for="route in menuRoutes" :key="route.path">
            <el-sub-menu v-if="route.children && route.children.length > 1" :index="route.path">
              <template #title>
                <el-icon v-if="route.meta?.icon && route.meta?.title?.length === 3"><component :is="route.meta.icon" /></el-icon>
                <span>{{ route.meta?.title }}</span>
              </template>
              <el-menu-item
                v-for="child in route.children"
                :key="child.path"
                :index="child.path"
              >
                <el-icon v-if="child.meta?.icon && child.meta?.title?.length === 3"><component :is="child.meta.icon" /></el-icon>
                <template #title>{{ child.meta?.title }}</template>
              </el-menu-item>
            </el-sub-menu>
            <el-menu-item v-else :index="route.children?.[0]?.path || route.path">
              <el-icon v-if="route.meta?.icon && route.meta?.title?.length === 3"><component :is="route.meta.icon" /></el-icon>
              <template #title>{{ route.meta?.title }}</template>
            </el-menu-item>
          </template>
        </el-menu>
      </el-scrollbar>

      <!-- 侧边栏底部：版本号 + 版权 (版本号从后端 /settings.version 动态读取, 源在 pc-api/config/oa.php::app_version) -->
      <div class="sidebar-footer" :class="{ 'is-collapsed': appStore.sidebarCollapsed }">
        <div class="sidebar-footer-version">{{ systemConfigStore.settings.version }}</div>
        <div class="sidebar-footer-copyright">{{ systemConfigStore.settings.copyright }}</div>
      </div>
    </el-aside>

    <!-- 右侧内容区 -->
    <el-container class="right-container">
      <!-- 顶栏 -->
      <el-header class="header">
        <div class="header-left">
          <el-icon class="collapse-btn" @click="appStore.toggleSidebar">
            <component :is="appStore.sidebarCollapsed ? 'Expand' : 'Fold'" />
          </el-icon>
          <el-breadcrumb separator="/">
            <el-breadcrumb-item v-for="item in breadcrumbs" :key="item.path">
              <span :class="{ 'is-link': item.path }" @click="item.path && router.push(item.path)">
                {{ item.title }}
              </span>
            </el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="header-right">
          <!-- 搜索 -->
          <el-tooltip content="全局搜索" placement="bottom">
            <el-icon class="header-action"><Search /></el-icon>
          </el-tooltip>
          <!-- 消息 -->
          <el-tooltip content="消息中心" placement="bottom">
            <el-badge :value="unreadCount" :max="99" :hidden="unreadCount === 0" class="header-action">
              <el-icon class="header-action__icon" @click="goToMessage"><Bell /></el-icon>
            </el-badge>
          </el-tooltip>
          <!-- 全屏 -->
          <el-tooltip content="全屏" placement="bottom">
            <el-icon class="header-action" @click="toggleFullscreen"><FullScreen /></el-icon>
          </el-tooltip>
          <!-- 用户下拉 -->
          <el-dropdown trigger="click" @command="handleCommand">
            <div class="user-info">
              <el-avatar :size="32" class="user-avatar">
                {{ userStore.userInfo?.name?.charAt(0) || 'U' }}
              </el-avatar>
              <span class="user-name">{{ userStore.userInfo?.name || '用户' }}</span>
              <el-icon><ArrowDown /></el-icon>
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="profile">
                  <el-icon><User /></el-icon>个人信息
                </el-dropdown-item>
                <el-dropdown-item command="password">
                  <el-icon><Lock /></el-icon>修改密码
                </el-dropdown-item>
                <el-dropdown-item divided command="logout">
                  <el-icon><SwitchButton /></el-icon>退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <!-- 主内容区 -->
      <el-main class="main-content">
        <router-view v-slot="{ Component }">
          <!-- V1.0 修复: 移除 fade-transform transition — 在快速 keep-alive + cachedViews 空场景下,
               enter-from 的 opacity:0 状态会卡住, 导致页面空白. 直接渲染. -->
          <keep-alive :include="cachedViews">
            <component :is="Component" :key="route.path" />
          </keep-alive>
        </router-view>
      </el-main>
    </el-container>

    <!-- V0.5.7 块6 — PWA 安装提示 (右下角) -->
    <PwaInstallBanner />

    <!-- 移动端访问提示 -->
    <el-dialog
      v-model="deviceCheck.dialogVisible.value"
      title="请使用 PC 端访问"
      width="420px"
      :close-on-click-modal="false"
      :show-close="true"
      @close="deviceCheck.closeDialog"
    >
      <div class="mobile-dialog">
        <el-icon :size="48" color="#BA7517"><Warning /></el-icon>
        <p>检测到您正在使用移动设备访问本系统。</p>
        <p>为获得最佳体验,请使用 <strong>PC 浏览器(Chrome/Edge)1920×1080</strong> 以上分辨率访问。</p>
        <p class="mobile-dialog__tip">移动端适配版本正在规划中,敬请期待。</p>
      </div>
      <template #footer>
        <el-button @click="deviceCheck.closeDialog">我知道了</el-button>
        <el-button type="primary" @click="deviceCheck.closeDialog">继续访问</el-button>
      </template>
    </el-dialog>
  </el-container>
</template>

<script setup lang="ts">
import { computed, watch, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useUserStore } from '@/stores/user'
import { useSystemConfigStore } from '@/stores/systemConfig'
import { ElMessageBox } from 'element-plus'
import { get as httpGet } from '@/utils/request'
import { useDeviceCheck } from '@/composables/useDeviceCheck'
import PwaInstallBanner from '@/components/PwaInstallBanner.vue'
import { Message as MessageIcon, Loading as LoadingIcon } from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const userStore = useUserStore()
const systemConfigStore = useSystemConfigStore()
const cachedViews = ref<string[]>([])

// 移动端检测
const deviceCheck = useDeviceCheck()

// 动态页面标题
function updateDocumentTitle() {
  const baseTitle = systemConfigStore.sysConfig.systemName || '安防运维OA办公系统'
  document.title = baseTitle
}
onMounted(updateDocumentTitle)
watch(() => systemConfigStore.sysConfig.systemName, updateDocumentTitle)

// ========== 顶栏消息红点 ==========
const unreadCount = ref(0)
let unreadTimer: ReturnType<typeof setInterval> | null

async function loadUnreadCount() {
  try {
    const res = await httpGet('/notifications/unread-count')
    // 解包后 res = {count: 3}
    if (res && typeof res === 'object' && 'count' in res) {
      unreadCount.value = Number(res.count) || 0
    }
  } catch (e: unknown) {
    // V1.2 fix: system 用户访问业务 API (如 /notifications) 会被 ensure_business 拦截
    // 这时 403 不应弹错误 toast (路由守卫已经做过提示), 这里直接静默
    if (e?.status === 403) {
      unreadCount.value = 0
    } else {
      /* 未登录或网络异常: 静默 */
    }
  }
}

function goToMessage() {
  // 跳到消息中心（路由已配 alias '/message'）
  router.push('/message')
}

// ========== 企业邮箱入口 (mail.example.com) ==========
// 设计要求: 完整 4 状态 (default / hover / disabled / loading) — 与现有 token 保持一致
// 不加新依赖, 复用 Element Plus Message / Loading 图标
const emailLoading = ref(false)    // 加载中 (点击后短暂, 防止重复点击)
const emailDisabled = ref(false)   // 禁用 (预留: 可按权限/维护窗口动态切换)
const MAIL_URL = 'https://mail.example.com'

function onEmailClick(e: MouseEvent) {
  if (emailDisabled.value) {
    e.preventDefault()
    return
  }
  if (emailLoading.value) {
    e.preventDefault()
    return
  }
  // 打开新标签页前做一次短暂 loading 反馈, 300ms 后释放
  // 防止用户狂点 + 给视觉反馈
  emailLoading.value = true
  setTimeout(() => {
    emailLoading.value = false
  }, 600)
}

onMounted(() => {
  loadUnreadCount()
  // V1.4.1: 登录后主动拉取系统设置(版本号/版权), 保证侧边栏版本号显示后端真实值, 不再依赖进数据管理页才刷新
  systemConfigStore.fetchSettings()
  // 每 60s 拉一次
  unreadTimer = setInterval(loadUnreadCount, 60000)
})

// 侧边栏菜单路由（过滤掉隐藏的顶级项和子项）
// V1.2.13: key 用首个 path 作为父菜单的 url path (用于 :index 和 :default-openeds)
const MENU_GROUPS = [
  { name: '工作台', icon: 'Odometer', path: 'dashboard', paths: ['dashboard', 'project-overview', 'analytics', 'message', 'screen'] },
  { name: '销售中心', icon: '', path: 'customer', paths: ['customer', 'sales'] },
  { name: '项目管理', icon: '', path: 'project', paths: ['project'] },
  { name: '施工管理', icon: '', path: 'construction', paths: ['construction'] },
  { name: '工序管理', icon: '', path: 'process', paths: ['process'] },
  { name: '维修中心', icon: '', path: 'after-sales', paths: ['after-sales'] },
  { name: '采购管理', icon: '', path: 'purchase-collab', paths: ['purchase-collab'] },
  { name: '仓库管理', icon: '', path: 'inventory', paths: ['inventory'] },
  { name: '财务中心', icon: '', path: 'finance', paths: ['finance', 'expense'] },
  { name: '行政人事', icon: '', path: 'employee', paths: ['employee', 'attendance', 'vehicle', 'knowledge', 'disk'] },
  { name: '审批中心', icon: '', path: 'approval', paths: ['approval'] },
  { name: '系统设置', icon: '', path: 'settings', paths: ['settings'] },
]

const menuRoutes = computed(() => {
  const userType = (userStore.userInfo as Record<string, unknown>)?.user_type ?? 'business'
  const isSystem = userType === 'system'
  const mainRoute = router.options.routes.find(r => r.path === '/')
  const allChildren = mainRoute?.children || []

  const isVisible = (item: Record<string, unknown>) => {
    if (!item.meta?.title || item.meta?.hidden || item.meta?.hideInMenu) return false
    if (item.meta?.systemOnly && !isSystem) return false
    if (item.meta?.businessOnly && isSystem) return false
    return true
  }

  return MENU_GROUPS.map(group => {
    const items = group.paths.flatMap(p => {
      const route = allChildren.find(r => r.path === p)
      if (!route || !isVisible(route)) return []
      const children = (route.children || []).filter(isVisible)
      if (children.length === 0) {
        return [{ path: `/${route.path}`, meta: route.meta }]
      }
      // V1.2.13: 处理子路由 redirect (如 after-sales/work-orders → /maintenance/work-orders)
      const toPath = (c: Record<string, unknown>): string => {
        if (typeof c.redirect === 'string' && c.redirect.startsWith('/')) return c.redirect as string
        return `/${route.path}/${c.path}`
      }
      if (children.length === 1) {
        return [{ path: toPath(children[0]), meta: children[0].meta }]
      }
      return children.map(c => ({ path: toPath(c), meta: c.meta }))
    })
    return {
      // V1.2.13: 用 url path 作为父菜单的 key 和 index (不要用中文 group.name)
      path: group.path,
      meta: { title: group.name, icon: group.icon },
      children: items,
    }
  }).filter(g => g.children.length > 0)
})

// 当前激活菜单
const activeMenu = computed(() => {
  return route.path
})

// 面包屑
const breadcrumbs = computed(() => {
  const crumbs: { path: string; title: string }[] = [{ path: '', title: '首页' }]
  const matched = route.matched.filter(item => item.meta?.title)
  matched.forEach(item => {
    crumbs.push({ path: item.path, title: item.meta.title as string })
  })
  return crumbs
})

// 菜单索引（处理只有1个子路由的情况）
function getMenuIndex(route: Record<string, unknown>): string {
  if (route.children?.length === 1) {
    return `/${route.path}/${route.children[0].path}`
  }
  return `/${route.path}`
}

// 全屏切换
function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen()
  } else {
    document.exitFullscreen()
  }
}

// 用户下拉命令
function handleCommand(command: string) {
  switch (command) {
    case 'profile':
      router.push('/settings/profile')
      break
    case 'password':
      router.push('/settings/password')
      break
    case 'logout':
      ElMessageBox.confirm('确定要退出登录吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        userStore.logout()
      })
      break
  }
}
</script>

<style lang="scss" scoped>
.main-layout {
  height: 100vh;
  overflow: hidden;
}

.sidebar {
  background: #0C447C;
  transition: width 0.3s ease;
  overflow: hidden;
  display: flex;
  flex-direction: column;

  .logo-bar {
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px 0 16px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
    gap: 8px;

    .logo {
      display: flex;
      align-items: center;
      cursor: pointer;
      flex: 1;
      min-width: 0;
      overflow: hidden;

      .logo-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #1D9E75, #7fdbca);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
        font-weight: 700;
        flex-shrink: 0;
      }

      .logo-text {
        color: white;
        font-size: 16px;
        font-weight: 600;
        margin-left: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
    }

    // 企业邮箱入口 — 4 状态: default / hover / disabled / loading
    .email-entry {
      flex-shrink: 0;
      width: 32px;
      height: 32px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      color: rgba(255,255,255,0.75);
      background: transparent;
      text-decoration: none;
      transition: background 0.2s ease, color 0.2s ease, opacity 0.2s ease;
      cursor: pointer;
      outline: none;

      &:hover {
        background: rgba(255,255,255,0.12);
        color: #fff;
      }

      &:active {
        background: rgba(255,255,255,0.18);
      }

      &:focus-visible {
        box-shadow: 0 0 0 2px rgba(127, 219, 202, 0.6);
      }

      &.is-disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
      }

      &.is-loading {
        cursor: wait;
        pointer-events: none;
        color: #7fdbca;
      }

      &__icon {
        display: block;

        &.is-spin {
          animation: email-entry-spin 1s linear infinite;
        }
      }
    }
  }

  @keyframes email-entry-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  :deep(.el-menu) {
    border-right: none;
    padding: 8px 0;

    .el-menu-item {
      margin: 2px 8px;
      border-radius: 6px;
      height: 44px;
      line-height: 44px;

      &.is-active {
        background: linear-gradient(135deg, rgba(29,158,117,0.3), rgba(29,158,117,0.15)) !important;
        position: relative;

        &::before {
          content: '';
          position: absolute;
          left: 0;
          top: 50%;
          transform: translateY(-50%);
          width: 3px;
          height: 20px;
          background: #1D9E75;
          border-radius: 0 2px 2px 0;
        }
      }

      &:hover {
        background: rgba(255,255,255,0.08) !important;
      }
    }

    .el-sub-menu {
      .el-sub-menu__title {
        margin: 2px 8px;
        border-radius: 6px;
        height: 44px;
        line-height: 44px;

        &:hover {
          background: rgba(255,255,255,0.08) !important;
        }
      }
    }
  }

  .sidebar-footer {
    flex-shrink: 0;
    padding: 10px 12px;
    border-top: 1px solid rgba(255,255,255,0.08);
    background: rgba(0,0,0,0.08);
    color: rgba(255,255,255,0.55);
    line-height: 1.4;
    transition: padding 0.3s ease;
  }

  .sidebar-footer-version {
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    letter-spacing: 0.4px;
  }

  .sidebar-footer-copyright {
    font-size: 11px;
    color: rgba(255,255,255,0.5);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .sidebar-footer.is-collapsed {
    padding: 10px 4px;
    text-align: center;

    .sidebar-footer-copyright {
      display: none;
    }
  }
}

.right-container {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.header {
  height: 56px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  border-bottom: 1px solid #ebeef5;
  flex-shrink: 0;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  z-index: 10;

  .header-left {
    display: flex;
    align-items: center;
    gap: 16px;

    .collapse-btn {
      font-size: 20px;
      cursor: pointer;
      color: #606266;
      transition: color 0.3s;

      &:hover {
        color: #0C447C;
      }
    }
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 8px;

    .header-action {
      font-size: 18px;
      cursor: pointer;
      color: #606266;
      padding: 8px;
      border-radius: 6px;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;

      &:hover {
        background: #f5f7fa;
        color: #0C447C;
      }

      &__icon {
        cursor: pointer;
        font-size: 18px;
        &:hover { color: #0C447C; }
      }
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      padding: 4px 12px 4px 4px;
      border-radius: 20px;
      transition: background 0.3s;
      margin-left: 8px;

      &:hover {
        background: #f5f7fa;
      }

      .user-avatar {
        background: linear-gradient(135deg, #0C447C, #1D9E75);
        color: white;
        font-size: 14px;
      }

      .user-name {
        font-size: 14px;
        color: #333;
      }
    }
  }
}

.main-content {
  flex: 1;
  overflow-y: auto;
  background: #f5f7fa;
  padding: 0;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.fade-transform-enter-active,
.fade-transform-leave-active {
  transition: all 0.3s;
}
.fade-transform-enter-from {
  opacity: 0;
  transform: translateX(-10px);
}
.fade-transform-enter-to {
  opacity: 1;
  transform: translateX(0);
}
.fade-transform-leave-to {
  opacity: 0;
  transform: translateX(10px);
}

.mobile-dialog {
  text-align: center;
  padding: 8px 0;
  .el-icon { margin-bottom: 16px; }
  p { margin: 8px 0; color: #606266; line-height: 1.6; }
  strong { color: #0C447C; }
  &__tip {
    margin-top: 16px;
    padding: 8px 12px;
    background: #fdf6ec;
    border-radius: 4px;
    color: #BA7517;
    font-size: 13px;
  }
}
</style>
