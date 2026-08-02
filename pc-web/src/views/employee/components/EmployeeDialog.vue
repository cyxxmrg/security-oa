<template>
  <el-dialog
    :model-value="visible"
    :title="target ? '编辑员工' : '新建员工'"
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
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="姓名" prop="name">
            <el-input v-model="form.name" placeholder="请输入姓名" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="登录账号" prop="username">
            <el-input v-model="form.username" placeholder="字母数字组合" :disabled="!!target" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col v-if="!target" :span="12">
          <el-form-item label="初始密码" prop="password">
            <el-input v-model="form.password" type="password" placeholder="默认 123456" show-password />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="角色" prop="role_id">
            <el-select v-model="form.role_id" placeholder="请选择员工角色" style="width: 100%" filterable clearable>
              <el-option v-for="r in roles" :key="r.id" :label="roleDisplayName(r)" :value="r.id">
                <span>{{ roleDisplayName(r) }}</span>
                <span v-if="r.name" style="float: right; color: #909399; font-size: 12px">{{ r.name }}</span>
              </el-option>
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="部门" prop="department_id">
            <el-select v-model="form.department_id" placeholder="请选择" style="width: 100%">
              <el-option v-for="d in deptList" :key="d.id" :label="d.name" :value="d.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="岗位" prop="position_id">
            <el-select
              v-model="form.position_id"
              placeholder="请选择"
              style="width: 100%"
              filterable
            >
              <el-option
                v-for="p in posList"
                :key="p.id"
                :label="`${p.name} (${deptNameOf(p.department_id)})`"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="手机号" prop="phone">
            <el-input v-model="form.phone" placeholder="请输入手机号" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="邮箱" prop="email">
            <el-input v-model="form.email" placeholder="请输入邮箱" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="入职日期">
            <el-date-picker
              v-model="form.hire_date"
              type="date"
              placeholder="选择日期"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="状态">
            <el-radio-group v-model="form.is_active">
              <el-radio :value="true">在职</el-radio>
              <el-radio :value="false">离职</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="技能标签">
            <el-select
              v-model="localSkillIds"
              multiple
              filterable
              allow-create
              default-first-option
              placeholder="请选择或输入技能标签"
              style="width: 100%"
              :loading="loadingSkillOptions"
            >
              <el-option
                v-for="tag in skillOptions"
                :key="tag.id"
                :label="tag.name"
                :value="tag.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
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
import type { EmployeeForm } from '../orgTypes'

const props = defineProps<{
  visible: boolean
  submitting: boolean
  target: Record<string, unknown> | null
  roles: { id: number; name: string; display_name?: string }[]
  deptList: { id: number; name: string }[]
  posList: { id: number; name: string; department_id: number }[]
  selectedSkillIds: number[]
  skillOptions: { id: number; name: string }[]
  loadingSkillOptions: boolean
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit', data: { form: EmployeeForm; selectedSkillIds: number[]; isEdit: boolean }): void
}>()

const formRef = ref<FormInstance>()
const form = reactive<EmployeeForm>({
  name: '',
  username: '',
  password: '',
  role_id: null,
  department_id: null,
  position_id: null,
  phone: '',
  email: '',
  hire_date: '',
  is_active: true,
})

// 内部维护 selectedSkillIds (响应式拷贝)
const localSkillIds = ref<(number | string)[]>([])

const rules: FormRules = {
  name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  username: [{ required: true, message: '请输入登录账号', trigger: 'blur' }],
  department_id: [{ required: true, message: '请选择部门', trigger: 'change' }],
  position_id: [{ required: true, message: '请选择岗位', trigger: 'change' }],
}

const deptNameOf = (id?: number | null) => {
  if (!id) return ''
  return props.deptList.find((d) => d.id === id)?.name || ''
}

const roleNameMap: Record<string, string> = {
  system_admin: '系统管理员',
  admin: '业务管理员',
  manager: '部门经理',
  finance: '财务',
  user: '普通员工',
  sales_manager: '销售经理',
}

const roleDisplayName = (role: { name: string; display_name?: string }) => {
  return role.display_name || roleNameMap[role.name] || role.name
}

const resetForm = () => {
  form.name = ''
  form.username = ''
  form.password = ''
  form.role_id = null
  form.department_id = null
  form.position_id = null
  form.phone = ''
  form.email = ''
  form.hire_date = ''
  form.is_active = true
  localSkillIds.value = []
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
    form.username = row.username || ''
    form.password = ''
    form.role_id = row.role_id || (row.roles?.[0]?.id ?? null)
    form.department_id = row.department_id || null
    form.position_id = row.position_id || null
    form.phone = row.phone || ''
    form.email = row.email || ''
    form.hire_date = row.hire_date || row.profile?.hire_date || ''
    form.is_active = row.is_active ?? true
  },
  { immediate: true },
)

// 当外部 selectedSkillIds 变化 (父组件 loadEmployeeSkills) 时同步到本地
watch(
  () => props.selectedSkillIds,
  (ids) => {
    localSkillIds.value = [...(ids || [])]
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
  emit('submit', {
    form: { ...form },
    selectedSkillIds: localSkillIds.value.map((id) => Number(id)),
    isEdit: !!props.target,
  })
}
</script>
