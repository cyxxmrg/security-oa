<template>
  <div class="error-page">
    <div class="error-content">
      <div class="error-code">{{ code }}</div>
      <div class="error-title">{{ title }}</div>
      <p class="error-desc">{{ desc }}</p>
      <div class="error-actions">
        <el-button type="primary" @click="goHome">
          <el-icon><HomeFilled /></el-icon>
          返回首页
        </el-button>
        <el-button @click="goBack">
          <el-icon><Back /></el-icon>
          返回上一页
        </el-button>
        <el-button v-if="showRetry" @click="retry">
          <el-icon><Refresh /></el-icon>
          重试
        </el-button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

defineProps<{
  code: string | number
  title: string
  desc: string
  showRetry?: boolean
}>()

const router = useRouter()

function goHome() { router.push('/') }
function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/')
}
function retry() { window.location.reload() }
</script>

<style scoped lang="scss">
.error-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f5f7fa 0%, #ebeef5 100%);
  padding: 24px;
}
.error-content {
  text-align: center;
  max-width: 560px;
  .error-code {
    font-size: 144px;
    font-weight: 800;
    line-height: 1;
    background: linear-gradient(135deg, #0C447C 0%, #1D9E75 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 16px;
    letter-spacing: -4px;
  }
  .error-title {
    font-size: 24px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 12px;
  }
  .error-desc {
    font-size: 14px;
    color: #909399;
    line-height: 1.6;
    margin-bottom: 32px;
  }
  .error-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
  }
}
</style>
