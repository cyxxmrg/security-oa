<template>
  <el-dialog
    :model-value="visible"
    :title="target ? '编辑岗位' : '新增岗位'"
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
      <el-form-item label="岗位名称" prop="name">
        <el-input v-model="form.name" placeholder="如：高级工程师" />
      </el-form-item>
      <el-form-item label="所属部门" prop="department_id">
        <el-select
          v-model="form.department_id"
          placeholder="选择部门"
          style="width: 100%"
        >
          <el-option
            v-for="d in allDeptOptions"
            :key="d.id"
            :label="d.name"
            :value="d.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="职级" prop="level">
        <el-select
          v-model="form.level"
          placeholder="选择职级"
          style="width: 100%"
        >
          <el-option
            v-for="l in POSITION_LEVEL_OPTIONS"
            :key="l.value"
            :label="l.label"
            :value="l.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="说明">
        <el-input
          v-model="form.description"
          type="textarea"
          :rows="2"
          placeholder="岗位说明"
        />
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
import { POSITION_LEVEL_OPTIONS, type PositionForm, type PositionLevel } from '../orgTypes'

const props = defineProps<{
  visible: boolean
  submitting: boolean
  target: Record<string, unknown> | null
  allDeptOptions: { id: number; name: string }[]
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit', data: { form: PositionForm; isEdit: boolean }): void
}>()

const formRef = ref<FormInstance>()
const form = reactive<PositionForm>({
  name: '',
  department_id: null,
  level: '' as PositionLevel | '',
  description: '',
  sort_order: 0,
})

const rules: FormRules = {
  name: [{ required: true, message: '请输入岗位名称', trigger: 'blur' }],
  department_id: [{ required: true, message: '请选择所属部门', trigger: 'change' }],
  level: [{ required: true, message: '请选择职级', trigger: 'change' }],
}

const resetForm = () => {
  form.name = ''
  form.department_id = null
  form.level = ''
  form.description = ''
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
    form.department_id = row.department_id || null
    form.level = (row.level || '') as PositionLevel | ''
    form.description = row.description || ''
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
