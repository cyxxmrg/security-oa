<template>
  <transition name="install-fade">
    <div v-if="visible" class="install-banner" @click="install">
      <div class="install-left">
        <el-icon class="install-icon"><Promotion /></el-icon>
        <div class="install-text">
          <div class="install-title">📲 安装到桌面</div>
          <div class="install-sub">快速访问, 离线可用</div>
        </div>
      </div>
      <div class="install-actions">
        <el-button type="primary" size="small" @click.stop="install">安装</el-button>
        <el-button size="small" text @click.stop="dismiss">稍后</el-button>
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Promotion } from '@element-plus/icons-vue'

const visible = ref(false)
let deferredEvent: Record<string, unknown> | null = null
const DISMISS_KEY = 'pwa_install_dismissed'
const DISMISS_DAYS = 7

const install = async () => {
  if (deferredEvent) {
    deferredEvent.prompt()
    const choice = await deferredEvent.userChoice
    if (choice.outcome === 'accepted') {
      ElMessage.success('已添加到桌面!')
    }
    deferredEvent = null
    visible.value = false
  } else {
    // iOS Safari
    ElMessage.info('请点击分享按钮 → 添加到主屏幕')
  }
}

const dismiss = () => {
  visible.value = false
  localStorage.setItem(DISMISS_KEY, Date.now().toString())
}

const checkDismissed = (): boolean => {
  const ts = localStorage.getItem(DISMISS_KEY)
  if (!ts) return false
  const days = (Date.now() - Number(ts)) / (1000 * 60 * 60 * 24)
  return days < DISMISS_DAYS
}

onMounted(() => {
  if (checkDismissed()) return

  // 监听 beforeinstallprompt
  window.addEventListener('beforeinstallprompt', (e: Record<string, unknown>) => {
    e.preventDefault()
    deferredEvent = e
    visible.value = true
  })

  // 已安装就隐藏
  if (window.matchMedia('(display-mode: standalone)').matches) {
    visible.value = false
  }
})
</script>

<style scoped lang="scss">
.install-banner {
  position: fixed; bottom: 16px; left: 16px; right: 16px;
  background: linear-gradient(135deg, #409EFF 0%, #67C23A 100%);
  color: #fff; padding: 12px 16px; border-radius: 12px;
  display: flex; justify-content: space-between; align-items: center;
  z-index: 9999; box-shadow: 0 4px 16px rgba(64,158,255,0.4);
  cursor: pointer;
}
.install-left { display: flex; gap: 12px; align-items: center; }
.install-icon { font-size: 28px; }
.install-title { font-size: 14px; font-weight: 600; }
.install-sub { font-size: 11px; opacity: 0.85; margin-top: 2px; }
.install-actions { display: flex; gap: 8px; }
:deep(.install-actions .el-button) { color: #fff; }
:deep(.install-actions .el-button--primary) { background: rgba(255,255,255,0.95); color: #409EFF; border: none; }
:deep(.install-actions .el-button--primary:hover) { background: #fff; }

.install-fade-enter-active, .install-fade-leave-active { transition: all 0.3s; }
.install-fade-enter-from, .install-fade-leave-to { opacity: 0; transform: translateY(20px); }

@media (min-width: 768px) {
  .install-banner { left: auto; right: 24px; max-width: 380px; }
}
</style>
