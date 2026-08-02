<template>
  <el-dialog
    :model-value="visible"
    title="转为施工项目"
    width="1500px"
    :close-on-click-modal="false"
    @update:model-value="$emit('update:visible', $event)"
  >
    <el-form
      ref="formRef"
      :model="formData"
      :rules="formRules"
      label-width="100px"
    >
      <el-form-item label="项目名称">
        <el-input :model-value="formData.name" disabled />
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="项目经理" prop="manager_id">
            <el-select
              v-model="formData.manager_id"
              placeholder="请选择"
              filterable
              style="width: 100%"
            >
              <el-option
                v-for="u in managerOptions"
                :key="u.id"
                :label="u.name"
                :value="u.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="项目预算" prop="budget">
            <el-input-number
              v-model="formData.budget"
              :min="0"
              :step="1000"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="开始日期" prop="start_date">
            <el-date-picker
              v-model="formData.start_date"
              type="date"
              placeholder="选择日期"
              style="width: 100%"
              value-format="YYYY-MM-DD"
            />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="结束日期" prop="end_date">
            <el-date-picker
              v-model="formData.end_date"
              type="date"
              placeholder="选择日期"
              style="width: 100%"
              value-format="YYYY-MM-DD"
            />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="团队成员" prop="team_member_ids">
        <el-select
          v-model="formData.team_member_ids"
          multiple
          placeholder="请选择团队成员（至少 1 人）"
          filterable
          style="width: 100%"
        >
          <el-option
            v-for="u in memberOptions"
            :key="u.id"
            :label="u.name"
            :value="u.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="备注">
        <el-input
          v-model="formData.notes"
          type="textarea"
          :rows="2"
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="$emit('update:visible', false)">取消</el-button>
      <el-button
        type="success"
        :loading="submitting"
        @click="handleConfirm"
      >
        确认转施工
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { FormInstance, FormRules } from 'element-plus'
import { PoolItem, EmployeeOption } from '../types'

export interface ConvertFormData {
  name: string
  manager_id: number | null
  budget: number
  start_date: string
  end_date: string
  team_member_ids: number[]
  notes: string
}

const props = defineProps<{
  visible: boolean
  submitting: boolean
  target: PoolItem | null
  managerOptions: EmployeeOption[]
  memberOptions: EmployeeOption[]
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'confirm', data: ConvertFormData): void
}>()

const formRef = ref<FormInstance>()
const formData = reactive<ConvertFormData>({
  name: '',
  manager_id: null,
  budget: 0,
  start_date: '',
  end_date: '',
  team_member_ids: [],
  notes: '',
})

const formRules: FormRules = {
  manager_id: [{ required: true, message: '请选择项目经理', trigger: 'change' }],
  budget: [{ required: true, message: '请输入项目预算', trigger: 'blur' }],
  start_date: [{ required: true, message: '请选择开始日期', trigger: 'change' }],
  end_date: [{ required: true, message: '请选择结束日期', trigger: 'change' }],
  team_member_ids: [
    { type: 'array', required: true, min: 1, message: '至少选择 1 名团队成员', trigger: 'change' },
  ],
}

// 当 target 变化时初始化表单
watch(
  () => props.target,
  (row) => {
    if (!row) return
    const today = new Date()
    const defaultEnd = new Date(today.getTime() + 90 * 24 * 3600 * 1000)
    Object.assign(formData, {
      name: row.name || '',
      manager_id: null,
      budget: Number(row.contract_amount || 0),
      start_date: today.toISOString().slice(0, 10),
      end_date: defaultEnd.toISOString().slice(0, 10),
      team_member_ids: [],
      notes: '',
    })
  },
  { immediate: true },
)

const handleConfirm = async () => {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  // 拷贝一份 reactive 状态给父
  emit('confirm', { ...formData })
}
</script>
