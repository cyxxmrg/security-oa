<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="`添加成员 (${teamName})`"
    width="640px"
    :close-on-click-modal="false"
    @open="handleOpen"
  >
    <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
      <el-form-item label="用户" prop="user_id">
        <el-select
          v-model="formData.user_id"
          placeholder="请选择员工"
          filterable
          remote
          :remote-method="searchUser"
          :loading="searchLoading"
          style="width: 100%"
        >
          <el-option
            v-for="u in userOptions"
            :key="u.id"
            :label="`${u.name}${u.dept ? ' (' + u.dept + ')' : ''}`"
            :value="u.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="岗位" prop="role">
        <el-select v-model="formData.role" placeholder="请选择" style="width: 100%">
          <el-option v-for="r in roleOptions" :key="r.value" :label="r.label" :value="r.value" />
        </el-select>
      </el-form-item>
      <el-form-item label="工种" prop="specialty">
        <el-input v-model="formData.specialty" placeholder="如：电工 / 焊工" maxlength="30" />
      </el-form-item>
      <el-form-item label="日工资" prop="daily_wage">
        <el-input-number v-model="formData.daily_wage" :min="0" :precision="2" :step="50" style="width: 100%" />
      </el-form-item>
      <el-form-item label="入职日期" prop="join_date">
        <el-date-picker
          v-model="formData.join_date"
          type="date"
          value-format="YYYY-MM-DD"
          style="width: 100%"
        />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="saving" @click="handleSave">添加</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { getUserList } from '@/api/modules'
import { unwrapList } from '@/utils/response'
import type { UserOption } from '../../types'

const props = defineProps<{
  visible: boolean
  teamName: string
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'save', payload: Record<string, unknown>): void
}>()

const formRef = ref()
const saving = ref(false)
const userOptions = ref<UserOption[]>([])
const searchLoading = ref(false)

const roleOptions = [
  { value: 'leader',     label: '队长' },
  { value: 'foreman',    label: '工长' },
  { value: 'worker',     label: '工人' },
  { value: 'apprentice', label: '学徒' },
  { value: 'driver',     label: '司机' },
]

const formData = reactive({
  user_id: null as number | null,
  role: 'worker',
  specialty: '',
  daily_wage: 0,
  join_date: '',
})

const formRules = {
  user_id: [{ required: true, message: '请选择用户', trigger: 'change' }],
  role:    [{ required: true, message: '请选择岗位', trigger: 'change' }],
  join_date: [{ required: true, message: '请选择入职日期', trigger: 'change' }],
}

const resetForm = () => {
  formData.user_id = null
  formData.role = 'worker'
  formData.specialty = ''
  formData.daily_wage = 0
  formData.join_date = ''
}

const searchUser = async (kw: string) => {
  searchLoading.value = true
  try {
    const res = await getUserList({ keyword: kw, per_page: 50 })
    userOptions.value = unwrapList(res)
  } catch {
    userOptions.value = []
  } finally {
    searchLoading.value = false
  }
}

const handleOpen = async () => {
  resetForm()
  await searchUser('')
}

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    emit('save', {
      user_id: formData.user_id,
      role: formData.role,
      specialty: formData.specialty,
      daily_wage: Number(formData.daily_wage || 0),
      join_date: formData.join_date,
    })
  } finally {
    saving.value = false
  }
}
</script>
