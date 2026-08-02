<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="修改公司名称"
    width="500px"
    destroy-on-close
    :close-on-click-modal="false"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-width="100px" @submit.prevent>
      <el-form-item label="公司名称" prop="name">
        <el-input v-model="form.name" placeholder="如：XX科技有限公司" maxlength="64" show-word-limit />
      </el-form-item>
      <el-alert type="info" :closable="false" show-icon>
        <template #title>修改后会同时同步到侧边栏系统名称与所有用到公司名的地方</template>
      </el-alert>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('submit')">确认</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'

export interface CompanyForm { name: string }

defineProps<{
  visible: boolean
  form: CompanyForm
  submitting: boolean
}>()

const formRef = ref()
const rules = {
  name: [
    { required: true, message: '请输入公司名称', trigger: 'blur' },
    { max: 64, message: '最多 64 字', trigger: 'blur' },
  ],
}
defineExpose({ formRef })

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit'): void
}>()
</script>
