<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="添加施工日志" width="540px" destroy-on-close
  >
    <el-form :model="form" label-width="100px">
      <el-form-item label="日期">
        <el-date-picker v-model="form.date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
      </el-form-item>
      <el-form-item label="天气">
        <el-select v-model="form.weather" placeholder="请选择" style="width: 100%">
          <el-option label="晴"   value="晴" />
          <el-option label="多云" value="多云" />
          <el-option label="雨"   value="雨" />
          <el-option label="雪"   value="雪" />
        </el-select>
      </el-form-item>
      <el-form-item label="施工内容">
        <el-input v-model="form.content" type="textarea" :rows="3" />
      </el-form-item>
      <el-form-item label="工时(h)">
        <el-input-number v-model="form.work_hours" :min="0" :max="24" :step="0.5" />
      </el-form-item>
      <el-form-item label="问题记录">
        <el-input v-model="form.problems" type="textarea" :rows="2" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('submit')">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
export interface AddLogForm {
  date: string
  weather: string
  content: string
  work_hours: number
  problems: string
}

defineProps<{
  visible: boolean
  form: AddLogForm
  submitting: boolean
}>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit'): void
}>()
</script>
