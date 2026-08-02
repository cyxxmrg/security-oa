import { ref, onMounted, onBeforeUnmount } from 'vue'

const MOBILE_BREAKPOINT = 768

/**
 * 移动端检测 + 弹窗提示
 * - window.innerWidth < 768 时弹 el-dialog 提示
 * - 自动监听 resize
 */
export function useDeviceCheck() {
  const isMobile = ref(false)
  const dialogVisible = ref(false)
  const dialogClosed = ref(false)  // 用户关闭后不再弹

  function check() {
    const wasMobile = isMobile.value
    isMobile.value = window.innerWidth < MOBILE_BREAKPOINT
    if (isMobile.value && !wasMobile && !dialogClosed.value) {
      dialogVisible.value = true
    }
  }

  function closeDialog() {
    dialogVisible.value = false
    dialogClosed.value = true
  }

  onMounted(() => {
    check()
    window.addEventListener('resize', check)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('resize', check)
  })

  return {
    isMobile,
    dialogVisible,
    closeDialog,
  }
}
