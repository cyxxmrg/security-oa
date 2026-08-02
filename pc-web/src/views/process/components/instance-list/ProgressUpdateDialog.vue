<template>
  <el-dialog
    v-model="visible"
    title="更新工序进度"
    width="480px"
    destroy-on-close
  >
    <el-form :model="form" label-width="100px" ref="formRef">
      <el-form-item label="当前进度">
        <div class="current-progress">
          <el-progress
            :percentage="form.progress"
            :color="progressColor(form.progress)"
            :stroke-width="14"
            :format="(p: number) => p + '%'"
          />
        </div>
      </el-form-item>
      <el-form-item label="新进度">
        <el-slider
          v-model="form.progress"
          :min="0"
          :max="100"
          :step="5"
          show-input
          :show-input-controls="false"
          style="margin-right: 12px"
        />
      </el-form-item>
      <el-form-item label="进度说明">
        <el-input
          v-model="form.comment"
          type="textarea"
          :rows="3"
          placeholder="请说明本次进度变更情况"
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="loading" @click="emit('submit')">提交</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { progressColor } from './types'

// v0.3.19 抽自 process/InstanceList.vue:175-207 + 539-575
const props = defineProps<{
  modelValue: boolean
  loading: boolean
  form: { progress: number; comment: string }
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'submit'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})
</script>
