<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="上传文件"
    width="500px"
    :close-on-click-modal="false"
    :show-close="false"
  >
    <div v-for="f in queue" :key="f.id" style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;margin-bottom:4px;gap:8px">
        <span :title="f.name" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ f.name }}</span>
        <span :style="{ color: f.status==='error' ? '#A32D2D' : f.status==='done' ? '#1D9E75' : '#0C447C' }" style="font-variant-numeric:tabular-nums">
          {{ f.status === 'done' ? '已完成' : f.status === 'error' ? (f.error || '失败') : f.status === 'canceled' ? '已取消' : f.progress + '%' }}
        </span>
        <el-button
          v-if="f.status === 'uploading'"
          text
          type="danger"
          size="small"
          @click="emit('cancel', f.id)"
        >取消</el-button>
        <el-button
          v-else-if="f.status === 'error' || f.status === 'canceled'"
          text
          type="primary"
          size="small"
          @click="emit('retry', f.id)"
        >重试</el-button>
      </div>
      <el-progress
        :percentage="f.status === 'canceled' ? 0 : f.progress"
        :status="f.status === 'done' ? 'success' : f.status === 'error' || f.status === 'canceled' ? 'exception' : undefined"
        :stroke-width="14"
        :show-text="false"
      />
      <div v-if="f.status === 'uploading' && f.speed" style="font-size:11px;color:#9ca3af;margin-top:2px">
        {{ f.speed }} · 剩余 {{ f.eta }}
      </div>
    </div>
    <template #footer>
      <el-button
        v-if="hasUploading"
        text
        @click="emit('cancelAll')"
      >全部取消</el-button>
      <el-button
        v-if="allDone"
        type="primary"
        @click="emit('close')"
      >关闭</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ visible: boolean; queue: Record<string, unknown>[] }>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'close'): void
  (e: 'cancel', id: string): void
  (e: 'cancelAll'): void
  (e: 'retry', id: string): void
}>()
const allDone = computed(() => props.queue.length > 0 && props.queue.every(f => f.status === 'done' || f.status === 'error' || f.status === 'canceled'))
const hasUploading = computed(() => props.queue.some(f => f.status === 'uploading'))
</script>
