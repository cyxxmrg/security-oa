<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="isEdit ? '编辑技能标签' : '新增技能标签'"
    width="500px"
    destroy-on-close
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-width="80px">
      <el-form-item label="标签名称" prop="name">
        <el-input v-model="form.name" placeholder="请输入标签名称，如：Vue.js" />
      </el-form-item>
      <el-form-item label="标签分类" prop="category">
        <el-select v-model="form.category" placeholder="选择分类" style="width: 100%">
          <el-option label="安装调试"   value="install" />
          <el-option label="网络通信"   value="network" />
          <el-option label="云平台"     value="cloud" />
          <el-option label="设备维护"   value="maintain" />
          <el-option label="故障排查"   value="debug" />
          <el-option label="其他"       value="other" />
        </el-select>
      </el-form-item>
      <el-form-item label="标签颜色" prop="color">
        <el-color-picker v-model="form.color" />
        <div style="font-size:12px;color:#909399;margin-top:6px;">
          选个让标签在表格里清晰可辨的颜色
        </div>
      </el-form-item>
      <el-form-item label="说明">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('submit')">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'

interface SkillTagForm {
  name: string
  category: string
  color: string
  description: string
  [key: string]: unknown
}

defineProps<{
  visible: boolean
  isEdit: boolean
  form: SkillTagForm
  submitting: boolean
}>()
const formRef = ref()
const rules = {
  name:     [{ required: true, message: '请输入标签名称', trigger: 'blur' }],
  category: [{ required: true, message: '请选择分类',     trigger: 'change' }],
  color:    [{ required: true, message: '请选择颜色',     trigger: 'change' }],
}
defineExpose({ formRef })
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit'): void
}>()
</script>
