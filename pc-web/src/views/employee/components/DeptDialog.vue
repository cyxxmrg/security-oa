<template>
  <el-dialog
    :model-value="visible"
    :title="target ? '编辑部门' : '新增部门'"
    width="1500px"
    destroy-on-close
    :close-on-click-modal="false"
    @update:model-value="$emit('update:visible', $event)"
  >
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="100px"
    >
      <el-form-item label="部门名称" prop="name">
        <el-input v-model="form.name" placeholder="如：技术部" />
      </el-form-item>
      <el-form-item label="上级部门" prop="parent_id">
        <el-select
          v-model="form.parent_id"
          placeholder="选择上级部门"
          clearable
          style="width: 100%"
          :disabled="!!target"
        >
          <el-option
            v-for="d in allDeptOptions"
            :key="d.id"
            :label="d.name"
            :value="d.id"
          />
        </el-select>
        <div v-if="target" style="font-size: 12px; color: #909399; margin-top: 4px">
          不能修改上级部门
        </div>
      </el-form-item>
      <el-form-item label="部门负责人" prop="manager_id">
        <el-select
          v-model="form.manager_id"
          placeholder="选择负责人"
          clearable
          filterable
          style="width: 100%"
        >
          <el-option
            v-for="u in userList"
            :key="u.id"
            :label="`${u.name} (${u.username})`"
            :value="u.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="排序" prop="sort_order">
        <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="$emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">
        确认
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { FormInstance, FormRules } from 'element-plus'
import type { DeptForm } from '../orgTypes'

const props = defineProps<{
  visible: boolean
  submitting: boolean
  target: Record<string, unknown> | null
  allDeptOptions: { id: number; name: string }[]
  userList: { id: number; name: string; username: string }[]
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit', data: { form: DeptForm; isEdit: boolean }): void
}>()

const formRef = ref<FormInstance>()
const form = reactive<DeptForm>({
  name: '',
  parent_id: null,
  manager_id: null,
  sort_order: 0,
})

const rules: FormRules = {
  name: [{ required: true, message: '请输入部门名称', trigger: 'blur' }],
}

const resetForm = () => {
  form.name = ''
  form.parent_id = null
  form.manager_id = null
  form.sort_order = 0
}

watch(
  () => props.target,
  (row) => {
    if (!row) {
      resetForm()
      return
    }
    form.id = row.id
    form.name = row.name || ''
    form.parent_id = row.parent_id ?? null
    form.manager_id = row.manager_id ?? null
    form.sort_order = Number(row.sort_order ?? 0)
  },
  { immediate: true },
)

const handleSubmit = async () => {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }
  emit('submit', { form: { ...form }, isEdit: !!props.target })
}
</script>
