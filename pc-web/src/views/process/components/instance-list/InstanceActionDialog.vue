<template>
  <el-dialog
    v-model="visible"
    :title="title"
    width="540px"
    destroy-on-close
  >
    <el-form
      :model="form"
      label-width="90px"
      :rules="rules"
      ref="formRef"
    >
      <el-form-item
        v-if="reasonKey"
        :label="reasonLabel"
        :prop="reasonKey"
      >
        <el-select
          v-if="reasonOptions && reasonOptions.length"
          v-model="form[reasonKey]"
          placeholder="请选择"
          style="width: 100%"
        >
          <el-option
            v-for="o in reasonOptions"
            :key="o"
            :label="o"
            :value="o"
          />
        </el-select>
        <el-input
          v-else
          v-model="form[reasonKey]"
          placeholder="请输入"
        />
      </el-form-item>
      <el-form-item :label="commentLabel || '备注'" :prop="commentKey">
        <el-input
          v-model="form[commentKey]"
          type="textarea"
          :rows="3"
          :placeholder="commentPlaceholder || '请输入备注'"
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="loading" @click="emit('submit')">确认</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'

// v0.3.19 抽自 process/InstanceList.vue:156-173 + 456-537
const props = defineProps<{
  modelValue: boolean
  loading: boolean
  title: string
  reasonLabel: string
  reasonKey: string
  reasonOptions?: string[]
  commentLabel?: string
  commentKey: string
  commentPlaceholder?: string
  form: Record<string, unknown>
  rules: Record<string, unknown>
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
