<template>
  <el-dialog
    v-model="visible"
    title="添加跟进记录"
    width="600px"
    destroy-on-close
  >
    <el-form :model="form" label-width="80px">
      <el-form-item label="跟进方式">
        <el-select v-model="form.type" style="width:100%">
          <el-option
            v-for="o in typeOptions"
            :key="o.value"
            :label="o.label"
            :value="o.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="跟进内容">
        <el-input
          v-model="form.content"
          type="textarea"
          :rows="4"
          placeholder="请输入跟进内容"
        />
      </el-form-item>
      <el-form-item label="下次跟进">
        <el-date-picker
          v-model="form.next_follow_up_date"
          type="date"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="下次备注">
        <el-input
          v-model="form.next_follow_up_note"
          placeholder="如：续约洽谈"
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
import type { FollowForm } from './types'
import { FOLLOW_TYPE_OPTIONS } from './types'

// v0.3.20 抽自 customer/Detail.vue:248-274
const props = defineProps<{
  modelValue: boolean
  loading: boolean
  form: FollowForm
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'submit'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const typeOptions = FOLLOW_TYPE_OPTIONS
</script>
