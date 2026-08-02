<template>
  <div class="content-card">
    <div class="card-title">
      <span><el-icon color="#0C447C"><Document /></el-icon> {{ title }}</span>
      <span class="muted">{{ hint }}</span>
    </div>
    <el-form :model="settings" label-width="140px" v-loading="saving">
      <slot :settings="settings" />
      <el-form-item>
        <el-button type="primary" :icon="Check" :loading="saving" @click="emit('save')">保存设置</el-button>
        <el-button :icon="RefreshLeft" @click="emit('reset')">恢复默认</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup lang="ts">
import { Document, Check, RefreshLeft } from '@element-plus/icons-vue'

defineProps<{
  title: string
  hint: string
  settings: Record<string, unknown>
  saving: boolean
}>()
const emit = defineEmits<{
  (e: 'save'): void
  (e: 'reset'): void
}>()
</script>

<style lang="scss" scoped>
.card-title { display: flex; justify-content: space-between; align-items: center; font-size: 16px; font-weight: 600; margin-bottom: 16px; }
.card-title :deep(.el-icon) { vertical-align: middle; margin-right: 6px; }
.muted { font-size: 12px; color: #909399; font-weight: 400; }
</style>
