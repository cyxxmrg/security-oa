<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="isEdit ? '编辑团队' : '新建团队'"
    width="1200px"
    :close-on-click-modal="false"
    @open="handleOpen"
  >
    <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="团队名称" prop="team_name">
            <el-input v-model="formData.team_name" placeholder="如：第一施工队" maxlength="100" show-word-limit />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="团队类型" prop="team_type">
            <el-select v-model="formData.team_type" placeholder="请选择" style="width: 100%">
              <el-option label="自有团队" value="internal" />
              <el-option label="外包团队" value="outsource" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="负责人" prop="leader_name">
            <el-select v-if="formData.team_type === 'internal'" v-model="formData.leader_name" placeholder="请选择员工" filterable style="width:100%">
              <el-option v-for="u in employeeOptions" :key="u.id" :label="u.name" :value="u.name" />
            </el-select>
            <el-input v-else v-model="formData.leader_name" placeholder="姓名" maxlength="50" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="联系电话" prop="leader_phone">
            <el-input v-model="formData.leader_phone" placeholder="手机号" maxlength="20" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="主要工种" prop="specialty">
            <el-input v-model="formData.specialty" placeholder="如: 弱电、电工" maxlength="200" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="关联项目">
            <el-select v-model="formData.project_id" placeholder="选择项目（可选）" filterable clearable style="width:100%">
              <el-option v-for="p in projectOptions" :key="p.id" :label="`${p.name} (${p.code || '#'+p.id})`" :value="p.id" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="备注">
        <el-input v-model="formData.remark" type="textarea" :rows="3" maxlength="1000" show-word-limit />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { get } from '@/utils/request'
import type { Team } from '../../types'

const props = defineProps<{
  visible: boolean
  editing?: Team | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'save', payload: Record<string, unknown>): void
}>()

const formRef = ref()
const saving = ref(false)
const isEdit = computed(() => !!props.editing?.id)

// 项目列表
const projectOptions = ref<{ id: number; name: string; code?: string }[]>([])
const loadProjects = async () => {
  try {
    const res = await get('/projects', { per_page: 200 })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    projectOptions.value = (d as { id: number; name: string; code?: string }[]) || []
  } catch { projectOptions.value = [] }
}

// 员工列表（自有团队选择负责人用）
const employeeOptions = ref<{ id: number; name: string }[]>([])
const loadEmployees = async () => {
  try {
    const res = await get('/employees', { per_page: 200 })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    employeeOptions.value = (d as { id: number; name: string }[]) || []
  } catch { employeeOptions.value = [] }
}

const formData = reactive({
  project_id: null as number | null,
  team_name: '',
  team_type: 'internal',
  leader_name: '',
  leader_phone: '',
  specialty: '',
  status: 'active',
  remark: '',
})

const formRules = {
  team_name:   [{ required: true, message: '请输入团队名称', trigger: 'blur' }],
  team_type:   [{ required: true, message: '请选择团队类型', trigger: 'change' }],
  leader_name: [{ required: true, message: '请输入负责人姓名', trigger: 'blur' }],
  leader_phone:[{ required: true, message: '请输入联系电话', trigger: 'blur' }],
}

const resetForm = () => {
  formData.project_id = null
  formData.team_name = ''
  formData.team_type = 'internal'
  formData.leader_name = ''
  formData.leader_phone = ''
  formData.specialty = ''
  formData.status = 'active'
  formData.remark = ''
}

const fillFromEditing = (row: Team) => {
  if (!row) { resetForm(); return }
  formData.project_id = row.project_id ?? null
  formData.team_name = row.team_name || ''
  formData.team_type = row.team_type || 'internal'
  formData.leader_name = row.leader_name || ''
  formData.leader_phone = row.leader_phone || ''
  formData.specialty = row.specialty || ''
  formData.status = row.status || 'active'
  formData.remark = row.remark || ''
}

const handleOpen = () => {
  loadProjects()
  loadEmployees()
  if (props.editing) fillFromEditing(props.editing)
  else resetForm()
}

onMounted(() => { loadProjects(); loadEmployees() })

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    emit('save', {
      project_id: formData.project_id || null,
      team_name: formData.team_name,
      team_type: formData.team_type,
      leader_name: formData.leader_name,
      leader_phone: formData.leader_phone,
      specialty: formData.specialty,
      status: formData.status,
      remark: formData.remark,
    })
  } finally {
    saving.value = false
  }
}
</script>