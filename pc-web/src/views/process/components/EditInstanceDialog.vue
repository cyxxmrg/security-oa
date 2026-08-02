<template>
  <el-dialog
    :model-value="visible"
    title="编辑工序实例"
    width="560px"
    destroy-on-close
    @update:model-value="emit('update:visible', $event)"
    @closed="resetForm"
  >
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="100px"
    >
      <el-form-item label="工序名称" prop="name">
        <el-input v-model="form.name" placeholder="请输入工序名称" maxlength="100" />
      </el-form-item>
      <el-form-item label="负责人">
        <el-select
          v-model="form.foreman_id"
          placeholder="请选择负责人"
          filterable
          clearable
          style="width: 100%"
        >
          <el-option
            v-for="u in userOptions"
            :key="u.id"
            :label="u.name"
            :value="u.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="计划开始">
        <el-date-picker
          v-model="form.planned_start_date"
          type="date"
          placeholder="选择开始日期"
          value-format="YYYY-MM-DD"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="计划结束">
        <el-date-picker
          v-model="form.planned_end_date"
          type="date"
          placeholder="选择结束日期"
          value-format="YYYY-MM-DD"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="施工位置">
        <el-input v-model="form.location" placeholder="施工位置（可选）" maxlength="200" />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="form.description" type="textarea" :rows="3" placeholder="备注（可选）" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">
        保存
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { type FormInstance, type FormRules } from 'element-plus'

export interface InstanceEditForm {
  name: string
  foreman_id: number | null
  planned_start_date: string
  planned_end_date: string
  location: string
  description: string
}

const props = defineProps<{
  visible: boolean
  submitting: boolean
  target: Record<string, unknown> | null
  userOptions: { id: number; name: string }[]
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit', data: InstanceEditForm): void
}>()

const formRef = ref<FormInstance>()
const form = reactive<InstanceEditForm>({
  name: '',
  foreman_id: null,
  planned_start_date: '',
  planned_end_date: '',
  location: '',
  description: '',
})

const rules: FormRules = {
  name: [{ required: true, message: '请输入工序名称', trigger: 'blur' }],
}

watch(
  () => props.target,
  (row) => {
    if (!row) return
    form.name = row.name || row.template_name || ''
    form.foreman_id = row.foreman_id ?? null
    form.planned_start_date = row.planned_start_date || row.planned_start || ''
    form.planned_end_date = row.planned_end_date || row.planned_end || ''
    form.location = row.location || ''
    form.description = row.description || row.remark || ''
  },
  { immediate: true },
)

const resetForm = () => {
  formRef.value?.clearValidate()
  form.name = ''
  form.foreman_id = null
  form.planned_start_date = ''
  form.planned_end_date = ''
  form.location = ''
  form.description = ''
}

const handleSubmit = async () => {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }
  emit('submit', { ...form })
}
</script>
