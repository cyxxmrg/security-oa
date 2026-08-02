<script setup lang="ts">
/**
 * GanttTaskUpdateDialog — 更新任务进度 dialog (v0.3.14 C3)
 */
import { reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import type { GanttTask, TaskStatus } from '../ganttTypes'

const props = withDefaults(
  defineProps<{
    visible: boolean
    target: GanttTask | null
  }>(),
  { visible: false, target: null },
)

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit', payload: { id?: number; name: string; progress: number; status: TaskStatus; note: string }): void
}>()

const dialogVisible = ref(props.visible)
watch(() => props.visible, (v) => (dialogVisible.value = v))
const close = () => emit('update:visible', false)

const form = reactive({
  progress: 0,
  status: 'in-progress' as TaskStatus,
  note: '',
})

watch(
  () => props.target,
  (t) => {
    if (t) {
      form.progress = t.progress
      form.status = t.status
      form.note = ''
    }
  },
  { immediate: true },
)

const handleSubmit = () => {
  if (form.progress < 0 || form.progress > 100) {
    ElMessage.warning('进度必须在 0-100 之间')
    return
  }
  if (!props.target) return
  emit('submit', {
    name: props.target.name,
    progress: form.progress,
    status: form.status,
    note: form.note,
  })
}
</script>

<template>
  <el-dialog
    :model-value="dialogVisible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="更新任务进度"
    width="480px"
    :close-on-click-modal="false"
    @close="close"
  >
    <div v-if="target" class="target-info">
      任务：<b>{{ target.name }}</b> · 当前进度 <b>{{ target.progress }}%</b>
    </div>
    <el-form label-width="80px">
      <el-form-item label="进度">
        <el-input-number v-model="form.progress" :min="0" :max="100" :step="5" style="width: 100%" />
        <el-progress :percentage="form.progress" :stroke-width="8" style="margin-top: 8px" />
      </el-form-item>
      <el-form-item label="状态">
        <el-select v-model="form.status" style="width: 100%">
          <el-option label="未开始" value="todo" />
          <el-option label="进行中" value="in-progress" />
          <el-option label="已完成" value="done" />
          <el-option label="延期" value="delayed" />
        </el-select>
      </el-form-item>
      <el-form-item label="更新备注">
        <el-input v-model="form.note" type="textarea" :rows="3" placeholder="本次更新的说明" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="close">取消</el-button>
      <el-button type="primary" @click="handleSubmit">提交更新</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.target-info {
  background: #E6F1FB;
  border-left: 3px solid #185FA5;
  padding: 8px 12px;
  margin-bottom: 16px;
  font-size: 13px;
  color: #303133;
  border-radius: 4px;
}
.target-info b { color: #0C447C; }
</style>
