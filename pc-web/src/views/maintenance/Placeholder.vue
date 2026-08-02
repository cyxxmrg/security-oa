<template>
  <div class="placeholder">
    <el-icon class="icon" :size="48"><Tools /></el-icon>
    <h2>{{ title }}</h2>
    <p class="hint">V0.5.5 维修中心正在建设中…</p>
    <el-button type="primary" @click="$router.back()">返回</el-button>
  </div>
</template>

<script setup lang="ts">
import { Tools } from '@element-plus/icons-vue'
defineProps<{ title?: string }>()
</script>

<style scoped>
.placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
  color: #909399;
}
.icon { color: #409EFF; margin-bottom: 16px; }
h2 { color: #303133; margin: 0 0 8px; font-size: 20px; }
.hint { margin-bottom: 16px; }
</style>
