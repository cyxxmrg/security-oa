<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="readonly ? '查看施工日志' : isEdit ? '编辑日志' : '新增施工日志'"
    width="820px"
    :close-on-click-modal="false"
    @open="handleOpen"
  >
    <el-form ref="formRef" :model="formData" :rules="readonly ? {} : formRules" label-width="100px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="日期" prop="date">
            <el-date-picker
              v-model="formData.date"
              type="date"
              value-format="YYYY-MM-DD"
              :disabled="readonly"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="天气" prop="weather">
            <el-select v-model="formData.weather" placeholder="请选择" :disabled="readonly" style="width: 100%">
              <el-option v-for="w in weatherOptions" :key="w" :label="w" :value="w" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="项目" prop="project_id">
            <el-select
              v-model="formData.project_id"
              placeholder="请选择项目"
              filterable
              clearable
              :disabled="readonly"
              style="width: 100%"
            >
              <el-option
                v-for="p in projectOptions"
                :key="p.id"
                :label="`${p.name}${p.code ? ' (' + p.code + ')' : ''}`"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="工序" prop="process_id">
            <el-select
              v-model="formData.process_id"
              placeholder="请选择工序"
              filterable
              clearable
              style="width: 100%"
              :disabled="readonly"
            >
              <el-option
                v-for="p in processOptions"
                :key="p.id"
                :label="`${p.name}${p.code ? ' (' + p.code + ')' : ''}`"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="施工团队" prop="team_id">
            <el-select
              v-model="formData.team_id"
              placeholder="请选择施工团队"
              filterable
              clearable
              :disabled="readonly"
              style="width: 100%"
            >
              <el-option
                v-for="t in teamOptions"
                :key="t.id"
                :label="`${t.team_name || t.name}`"
                :value="t.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="工人数量" prop="worker_count">
            <el-input-number v-model="formData.worker_count" :min="1" :step="1" style="width: 100%" :disabled="readonly" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="工时" prop="work_hours">
            <el-input-number v-model="formData.work_hours" :min="0" :step="1" style="width: 100%" :disabled="readonly" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="进度" prop="progress">
            <el-input-number v-model="formData.progress" :min="0" :max="100" :step="5" style="width: 100%" :disabled="readonly" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="问题与风险" prop="issues">
        <el-input
          v-model="formData.issues"
          type="textarea"
          :rows="3"
          maxlength="1000"
          show-word-limit
          :disabled="readonly"
          placeholder="今日遇到的问题 / 风险 / 需要协调的事项（可选）"
        />
      </el-form-item>

      <el-form-item label="照片">
        <el-input v-model="formData.photos" placeholder="照片 URL，多个用逗号分隔（可选）" :disabled="readonly" />
      </el-form-item>

      <el-form-item label="备注">
        <el-input v-model="formData.remark" type="textarea" :rows="2" maxlength="500" show-word-limit :disabled="readonly" />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="emit('update:visible', false)">{{ readonly ? '关闭' : '取消' }}</el-button>
      <el-button v-if="!readonly" type="primary" :loading="saving" @click="handleSave('draft')">保存草稿</el-button>
      <el-button v-if="!readonly && !isEdit" type="success" :loading="saving" @click="handleSave('submit')">提交</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { ElMessage } from 'element-plus'
import type { LogRow, ProcessOption } from '../../types'

const props = defineProps<{
  visible: boolean
  projectOptions: Record<string, unknown>[]
  processOptions: Record<string, unknown>[]
  teamOptions: Record<string, unknown>[]
  editing?: Record<string, unknown>
  defaultDate?: string
  readonly?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'save', payload: Record<string, unknown>, action: 'draft' | 'submit'): void
}>()

const formRef = ref()
const saving = ref(false)
const isEdit = computed(() => !!props.editing?.id)

const weatherOptions = ['晴', '多云', '阴', '小雨', '中雨', '大雨', '雪', '雾', '大风']

const formData = reactive({
  date: '',
  weather: '晴',
  project_id: null as number | null,
  process_id: null as number | null,
  team_id: null as number | null,
  worker_count: 1,
  work_hours: 0,
  progress: 0,
  issues: '',
  photos: '',
  remark: '',
})

const formRules = {
  date:        [{ required: true, message: '请选择日期', trigger: 'change' }],
  weather:     [{ required: true, message: '请选择天气', trigger: 'change' }],
  project_id:  [{ required: true, message: '请选择项目', trigger: 'change' }],
  worker_count:[{ required: true, message: '请填写工人数量', trigger: 'blur' }],
  work_hours:  [{ required: true, message: '请填写工时', trigger: 'blur' }],
  progress:    [{ required: true, message: '请填写进度', trigger: 'blur' }],
}

const resetForm = () => {
  formData.date = props.defaultDate || new Date().toISOString().slice(0, 10)
  formData.weather = '晴'
  formData.project_id = null
  formData.process_id = null
  formData.team_id = null
  formData.worker_count = 1
  formData.work_hours = 0
  formData.progress = 0
  formData.issues = ''
  formData.photos = ''
  formData.remark = ''
}

const fillFromEditing = (row: LogRow) => {
  if (!row) { resetForm(); return }
  const r = row as Record<string, unknown>
  formData.date = (r.work_date as string) || (r.date as string) || ''
  formData.weather = row.weather || '晴'
  formData.project_id = row.project_id || null
  formData.process_id = row.process_id || null
  formData.team_id = row.team_id || null
  formData.worker_count = Number(row.worker_count || 1)
  formData.work_hours = Number(row.work_hours || 0)
  formData.progress = Number(row.progress || 0)
  formData.issues = row.issues || ''
  formData.photos = Array.isArray(row.photos) ? row.photos.join(',') : (row.photos || '')
  formData.remark = row.remark || ''
}

const handleOpen = () => {
  if (props.editing) fillFromEditing(props.editing)
  else resetForm()
}

const handleSave = async (action: 'draft' | 'submit') => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    emit('save', {
      date: formData.date,
      weather: formData.weather,
      project_id: formData.project_id,
      process_id: formData.process_id,
      team_id: formData.team_id,
      worker_count: Number(formData.worker_count || 1),
      work_hours: Number(formData.work_hours || 0),
      progress: Number(formData.progress || 0),
      issues: formData.issues,
      photos: formData.photos,
      remark: formData.remark,
    }, action)
  } finally {
    saving.value = false
  }
}
</script>
