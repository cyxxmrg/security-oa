<script setup lang="ts">
/**
 * QuoteErrorState — 报价单加载错误兜底 (v0.3.14 B3)
 * 网络/服务器异常时显示，可重试
 */
import { WarningFilled, Refresh } from '@element-plus/icons-vue'

defineProps<{
  /** 错误标题 */
  title?: string
  /** 错误详情 */
  message?: string
  /** 重试按钮 loading */
  retrying?: boolean
}>()

const emit = defineEmits<{
  (e: 'retry'): void
}>()

const handleRetry = () => emit('retry')
</script>

<template>
  <div class="error-state">
    <el-icon :size="48" color="#F56C6C">
      <WarningFilled />
    </el-icon>
    <div class="es-title">{{ title || '加载失败' }}</div>
    <div class="es-message">{{ message || '请检查网络或联系管理员' }}</div>
    <el-button type="primary" :icon="Refresh" :loading="retrying" @click="handleRetry" plain>
      重试
    </el-button>
  </div>
</template>

<style scoped>
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 48px 16px;
  min-height: 240px;
}
.es-title {
  font-size: 16px;
  font-weight: 500;
  color: #303133;
  margin-top: 4px;
}
.es-message {
  font-size: 13px;
  color: #909399;
  text-align: center;
  max-width: 320px;
  line-height: 1.5;
}
</style>
