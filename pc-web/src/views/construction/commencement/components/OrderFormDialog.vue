<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="isEdit ? '编辑开工单' : '新建开工单'"
    width="900px"
    :close-on-click-modal="false"
    @open="handleOpen"
  >
    <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="所属项目" prop="project_id">
            <el-select
              v-model="formData.project_id"
              placeholder="请选择项目"
              filterable
              :disabled="isEdit"
              style="width: 100%"
            >
              <el-option
                v-for="p in projectOptions"
                :key="p.id"
                :label="`${p.code ? p.code + ' - ' : ''}${p.name || ''}`"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="施工团队" prop="team_id">
            <el-select
              v-model="formData.team_id"
              placeholder="请选择团队"
              filterable
              clearable
              style="width: 100%"
            >
              <el-option
                v-for="t in teamOptions"
                :key="t.id"
                :label="`${t.team_name || t.name || '#'+t.id}${t.leader_name ? ' (' + t.leader_name + ')' : ''}`"
                :value="t.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="计划开工" prop="planned_start_date">
              <el-date-picker
              v-model="formData.planned_start_date"
              type="date"
              value-format="YYYY-MM-DD"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="计划完工" prop="planned_end_date">
              <el-date-picker
              v-model="formData.planned_end_date"
              type="date"
              value-format="YYYY-MM-DD"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="安全要求">
            <el-input v-model="formData.safety_requirements" type="textarea" :rows="2" placeholder="可选" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="施工内容" prop="work_content">
        <el-input
          v-model="formData.work_content"
          type="textarea"
          :rows="4"
          maxlength="1000"
          show-word-limit
          placeholder="简述本次开工的施工范围与主要工序"
        />
      </el-form-item>

      <el-form-item label="备注">
        <el-input v-model="formData.remark" type="textarea" :rows="2" maxlength="500" show-word-limit />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { ElMessage } from 'element-plus'
import type { Commencement, ProjectOption, TeamOption } from '../../types'

const props = defineProps<{
  visible: boolean
  projectOptions: Record<string, unknown>[]
  teamOptions: Record<string, unknown>[]
  editing?: Record<string, unknown>
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'save', payload: Record<string, unknown>): void
}>()

const formRef = ref()
const saving = ref(false)
const isEdit = computed(() => !!props.editing?.id)

const formData = reactive({
  project_id: null as number | null,
  team_id: null as number | null,
  planned_start_date: '',
  planned_end_date: '',
  work_content: '',
  safety_requirements: '',
  remark: '',
})

const formRules = {
  project_id:    [{ required: true, message: '请选择项目', trigger: 'change' }],
  team_id:       [{ required: true, message: '请选择团队', trigger: 'change' }],
  planned_start_date: [{ required: true, message: '请选择开工日期', trigger: 'change' }],
  planned_end_date:   [{ required: true, message: '请选择完工日期', trigger: 'change' }],
  work_content:  [{ required: true, message: '请填写施工内容', trigger: 'blur' }],
}

const resetForm = () => {
  formData.project_id = null
  formData.team_id = null
  formData.planned_start_date = ''
  formData.planned_end_date = ''
  formData.work_content = ''
  formData.safety_requirements = ''
  formData.remark = ''
}

const fillFromEditing = (row: Commencement) => {
  if (!row) { resetForm(); return }
  formData.project_id = row.project_id || null
  formData.team_id = row.team_id || null
  formData.planned_start_date = (row.planned_start_date as string) || (row.commencement_date as string) || ''
  formData.planned_end_date = (row.planned_end_date as string) || ''
  formData.work_content = (row.work_content as string) || (row.work_scope as string) || ''
  formData.safety_requirements = (row.safety_requirements as string) || ''
  formData.remark = row.remark || ''
}

const handleOpen = () => {
  if (props.editing) fillFromEditing(props.editing)
  else resetForm()
}

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    emit('save', {
      project_id: formData.project_id,
      team_id: formData.team_id,
      planned_start_date: formData.planned_start_date,
      planned_end_date: formData.planned_end_date,
      work_content: formData.work_content,
      safety_requirements: formData.safety_requirements,
      remark: formData.remark,
    })
  } finally {
    saving.value = false
  }
}
</script>

<style lang="scss" scoped>
.form-tip { color: #909399; font-size: 12px; margin-left: 8px; }
</style>
