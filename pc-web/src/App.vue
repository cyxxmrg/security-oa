<template>
  <PageLoader :loading="isRouteLoading" />
  <router-view v-slot="{ Component, route }">
    <transition name="app-fade" mode="out-in">
      <div :key="route.path" class="app-route-wrap">
        <AppSkeleton v-if="showSkeleton" :type="skeletonType" :rows="skeletonRows" />
        <component v-else :is="Component" />
      </div>
    </transition>
  </router-view>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSystemConfigStore } from '@/stores/systemConfig'
import AppSkeleton from '@/components/AppSkeleton.vue'
import PageLoader from '@/components/PageLoader.vue'

const route = useRoute()
const router = useRouter()

// 路由切换 loading 状态
const isRouteLoading = ref(false)
let loadTimer: Record<string, unknown> | null = null
let offAfter: Record<string, unknown> | null = null

// 骨架屏:基于路由 meta.skeleton
const showSkeleton = ref(false)
const skeletonType = ref<'table' | 'card' | 'detail' | 'form'>('table')
const skeletonRows = ref(5)

function applySkeletonMeta() {
  const meta = route.meta as Record<string, unknown>
  if (meta?.skeleton) {
    showSkeleton.value = true
    skeletonType.value = meta.skeleton.type || 'table'
    skeletonRows.value = meta.skeleton.rows || 5
  } else {
    showSkeleton.value = false
  }
}

// 启动路由 loading + 骨架屏
function startRouteLoading() {
  isRouteLoading.value = true
  applySkeletonMeta()
  // 兜底:如果 800ms 还没结束,自动收起(防 loading 卡死)
  clearTimeout(loadTimer)
  loadTimer = setTimeout(() => { isRouteLoading.value = false }, 3000)
}

function endRouteLoading() {
  isRouteLoading.value = false
  clearTimeout(loadTimer)
  // 骨架屏至少显示 200ms,避免闪烁
  if (showSkeleton.value) {
    setTimeout(() => { showSkeleton.value = false }, 200)
  } else {
    showSkeleton.value = false
  }
}

onMounted(() => {
  const store = useSystemConfigStore()
  const name = store.sysConfig.systemName || 'OA 办公系统'
  document.title = name
  // 监听路由切换
  router.beforeEach(() => {
    startRouteLoading()
  })
  offAfter = router.afterEach(() => {
    endRouteLoading()
  })
})

onBeforeUnmount(() => {
  clearTimeout(loadTimer)
  offAfter?.()
})
</script>

<style lang="scss">
.app-route-wrap {
  width: 100%;
  height: 100%;
}
.app-fade-enter-active,
.app-fade-leave-active {
  transition: opacity 0.2s ease;
}
.app-fade-enter-from,
.app-fade-leave-to {
  opacity: 0;
}
</style>
