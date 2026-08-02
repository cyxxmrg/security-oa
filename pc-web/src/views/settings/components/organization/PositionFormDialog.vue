<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="title"
    width="480px"
    destroy-on-close
  >
    <el-form :model="form" label-width="100px">
      <el-form-item label="岗位名称" required>
        <el-input v-model="form.name" placeholder="请输入岗位名称" maxlength="64" show-word-limit />
      </el-form-item>
      <el-form-item label="所属部门" required>
        <el-select v-model="form.department_id" placeholder="请选择部门" style="width: 100%;">
          <el-option v-for="d in deptList" :key="d.id" :label="d.name" :value="d.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="级别">
        <el-select v-model="form.level" placeholder="请选择级别" style="width: 100%;">
          <el-option label="P1 - 高级专家" value="P1" />
          <el-option label="P2 - 专家"       value="P2" />
          <el-option label="P3 - 高级"        value="P3" />
          <el-option label="P4 - 中级"        value="P4" />
          <el-option label="P5 - 初级"        value="P5" />
          <el-option label="P6 - 助理"        value="P6" />
        </el-select>
      </el-form-item>
      <el-form-item label="岗位描述">
        <el-input v-model="form.description" type="textarea" :rows="3" placeholder="请输入岗位描述" maxlength="255" show-word-limit />
      </el-form-item>
      <el-form-item label="排序号">
        <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="saving" @click="emit('save')">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
defineProps<{
  visible: boolean
  title: string
  form: Record<string, unknown>
  deptList: Record<string, unknown>[]
  saving: boolean
}>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'save'): void
}>()
</script>
