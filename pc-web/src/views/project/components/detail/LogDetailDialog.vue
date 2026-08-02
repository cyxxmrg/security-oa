<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="施工日志详情"
    width="640px"
    destroy-on-close
  >
    <el-descriptions v-if="log" :column="2" border>
      <el-descriptions-item label="日期">{{ formatDate(log.date) }}</el-descriptions-item>
      <el-descriptions-item label="天气">{{ log.weather || '-' }}</el-descriptions-item>
      <el-descriptions-item label="工时">{{ log.work_hours || 0 }} h</el-descriptions-item>
      <el-descriptions-item label="操作员">{{ log.operator_name || '-' }}</el-descriptions-item>
      <el-descriptions-item label="施工内容" :span="2">{{ log.content || '-' }}</el-descriptions-item>
      <el-descriptions-item label="问题记录" :span="2">{{ log.problems || '-' }}</el-descriptions-item>
    </el-descriptions>
  </el-dialog>
</template>

<script setup lang="ts">
defineProps<{ visible: boolean; log: Record<string, unknown> }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void }>()

function formatDate(d: string) {
  return d ? d.slice(0, 10) : '-'
}
</script>
