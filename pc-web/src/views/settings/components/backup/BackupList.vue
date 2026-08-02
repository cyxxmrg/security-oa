<template>
  <div class="content-card" style="margin-top:20px">
    <div class="card-title">
      <span><el-icon color="#0C447C"><Files /></el-icon> 数据备份</span>
      <div class="card-title__actions">
        <el-button type="primary" :loading="backingUp" @click="emit('backup')">
          {{ backingUp ? '备份中...' : '手动备份' }}
        </el-button>
        <el-button @click="emit('refresh')">刷新</el-button>
      </div>
    </div>
    <el-table :data="backups" v-loading="loading" border stripe style="width:100%;">
      <el-table-column prop="time"     label="备份时间" width="180" />
      <el-table-column prop="size"     label="大小"     width="120" />
      <el-table-column prop="filename" label="文件名"  min-width="260" show-overflow-tooltip />
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" @click="emit('download', row)">下载</el-button>
          <el-button link type="danger"  size="small" @click="emit('delete', row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { Files } from '@element-plus/icons-vue'

defineProps<{
  backups: Record<string, unknown>[]
  loading: boolean
  backingUp: boolean
}>()
const emit = defineEmits<{
  (e: 'backup'): void
  (e: 'refresh'): void
  (e: 'download', row: Record<string, unknown>): void
  (e: 'delete', row: Record<string, unknown>): void
}>()
</script>
