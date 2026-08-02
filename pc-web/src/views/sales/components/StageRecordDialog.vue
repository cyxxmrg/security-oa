<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="$emit('update:visible', $event)"
    :title="dialogTitle"
    width="1200px"
    :close-on-click-modal="false"
    destroy-on-close
    top="3vh"
  >
    <el-form
      v-if="stageSchema"
      ref="formRef"
      :model="formData"
      :rules="rules"
      label-width="130px"
      label-position="right"
    >
      <!-- 阶段 + 录入时间 — 一行 -->
      <div class="row-2-col">
        <el-form-item label="当前阶段">
          <el-tag :type="stageTagType(stage)" effect="dark" size="small">{{ stageLabel(stage) }}</el-tag>
        </el-form-item>
        <el-form-item label="录入时间" prop="entered_at">
          <el-date-picker
            v-model="formData.entered_at"
            type="datetime"
            placeholder="选择录入时间"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 100%"
          />
        </el-form-item>
      </div>

      <!-- 字段按两列布局 -->
      <el-row :gutter="20">
        <template v-for="(field, key) in currentSchema" :key="key">
          <el-col :span="12">
            <el-form-item :label="field.label" :prop="`data.${key}`" :required="field.required">
              <el-input
                v-if="field.type === 'text'"
                v-model="formData.data[key]"
                :placeholder="`请输入${field.label}`"
                clearable
              />
              <el-input
                v-else-if="field.type === 'textarea'"
                v-model="formData.data[key]"
                type="textarea"
                :rows="3"
                :placeholder="`请输入${field.label}`"
              />
              <el-input-number
                v-else-if="field.type === 'number'"
                v-model="formData.data[key]"
                :precision="2"
                :step="1000"
                :min="0"
                style="width: 100%"
              />
              <el-date-picker
                v-else-if="field.type === 'date'"
                v-model="formData.data[key]"
                type="date"
                value-format="YYYY-MM-DD"
                style="width: 100%"
              />
              <el-select
                v-else-if="field.type === 'select'"
                v-model="formData.data[key]"
                :placeholder="`请选择${field.label}`"
                style="width: 100%"
              >
                <el-option v-for="opt in (field.options || [])" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
              <UserSelect v-else-if="field.type === 'user'" v-model="formData.data[key]" :placeholder="`请选择${field.label}`" />
              <UserSelect v-else-if="field.type === 'users'" v-model="formData.data[key]" :placeholder="`请选择${field.label}（可多选）`" multiple />
            </el-form-item>
          </el-col>
        </template>
      </el-row>

      <!-- 备注 -->
      <el-form-item label="备注" prop="note">
        <el-input v-model="formData.note" type="textarea" :rows="2" placeholder="可选, 录入本次阶段流转的备注说明" />
      </el-form-item>

      <!-- 下一流转人 -->
      <el-divider content-position="left">
        <span class="divider-title">下一流转人</span>
        <span class="divider-hint">本次录入完成后, 下一步交给谁（@内部员工）</span>
      </el-divider>
      <el-row :gutter="20">
        <el-col :span="14">
          <el-form-item label="下一流转人" prop="next_assignee_id">
            <UserSelect v-model="formData.next_assignee_id" placeholder="搜索姓名/部门/岗位..." clearable />
          </el-form-item>
        </el-col>
        <el-col :span="10">
          <el-form-item label="期望完成时间" prop="next_due_at">
            <el-date-picker v-model="formData.next_due_at" type="datetime" placeholder="可选" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
          </el-form-item>
        </el-col>
      </el-row>

      <!-- 附件：上传 + 已有文件列表 -->
      <el-divider content-position="left">
        <span class="divider-title">阶段附件</span>
        <span class="divider-hint">上传与当前阶段相关的文件（{{ stageHint }}）</span>
      </el-divider>

      <!-- 上传区 -->
      <div class="file-upload-bar">
        <el-upload ref="uploadRef" :auto-upload="false" :show-file-list="false" :on-change="handleFilesChange" :multiple="true" accept="*/*">
          <template #trigger>
            <el-button :icon="Upload" size="small" type="primary" plain>添加附件</el-button>
          </template>
        </el-upload>
        <span class="file-hint">可多选，每次选中后批量上传</span>
      </div>

      <!-- 待上传文件列表 -->
      <div v-if="pendingFiles.length > 0" class="pending-files">
        <div class="pending-header">
          <span>待上传 ({{ pendingFiles.length }})</span>
          <el-button size="small" type="primary" :loading="fileUploading" :disabled="fileUploading" @click="handleBatchUpload">
            {{ fileUploading ? `上传中 ${uploadProgress}/${pendingFiles.length}...` : `上传全部 ${pendingFiles.length} 个文件` }}
          </el-button>
        </div>
        <div v-for="(f, idx) in pendingFiles" :key="f.name + f.size" class="pending-row">
          <el-icon :size="14" color="#909399"><Document /></el-icon>
          <span class="pending-name">{{ f.name }}</span>
          <el-tag size="small" effect="plain" type="info">{{ (f.size / 1024).toFixed(1) }} KB</el-tag>
          <el-button link type="danger" :icon="Delete" size="small" @click="removePendingFile(idx)" :disabled="fileUploading" />
        </div>
        <div class="pending-notes">
          <el-input v-model="fileNotesInput" placeholder="给所有待上传文件加备注（可选）" size="small" clearable />
        </div>
      </div>

      <!-- 已有文件列表 -->
      <div v-if="fileList.length > 0" class="file-list">
        <div v-for="f in fileList" :key="f.id" class="file-row">
          <div class="file-icon">
            <el-icon :size="18" :color="fileIconColor(f.mime_type)"><Document /></el-icon>
          </div>
          <div class="file-name">{{ f.original_name }}</div>
          <el-tag size="small" effect="plain" type="info">{{ f.formatted_size }}</el-tag>
          <div v-if="f.notes" class="file-notes" :title="f.notes">{{ f.notes }}</div>
          <div class="file-actions">
            <el-button link type="primary" :icon="Download" size="small" @click="handleFileDownload(f)" />
            <el-popconfirm title="删除此文件？" @confirm="handleFileDelete(f)">
              <template #reference>
                <el-button link type="danger" :icon="Delete" size="small" />
              </template>
            </el-popconfirm>
          </div>
        </div>
      </div>
      <div v-else class="file-empty">暂无已上传附件</div>
    </el-form>

    <template #footer>
      <el-button @click="$emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="loading" @click="handleSubmit">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { Upload, Download, Delete, Document } from '@element-plus/icons-vue'
import { stageLabel, stageTagType } from '../types'
import { get, post, del } from '@/utils/request'
import UserSelect from '@/components/common/UserSelect.vue'

interface StageField {
  type: 'text' | 'textarea' | 'number' | 'date' | 'select' | 'user' | 'users'
  label: string
  required: boolean
  options?: { value: string; label: string }[]
}

interface Record {
  id?: number; stage: string; data: Record<string, unknown>
  note?: string; entered_at?: string
  next_assignee_id?: number | null; next_assignee_name?: string | null; next_due_at?: string | null
  [k: string]: unknown
}

interface StageFile {
  id: number; original_name: string; mime_type: string | null; file_size: number | null
  formatted_size: string; notes: string | null; url: string; exists: boolean; uploaded_by: string; created_at: string | null
}

// ========== props / emit ==========
const props = defineProps<{
  visible: boolean
  oppId: number | string
  stage: string
  record: Record | null
  stageSchema: Record<string, Record<string, StageField>>
  loading: boolean
}>()

const emit = defineEmits<{
  'update:visible': [v: boolean]
  save: [payload: Record<string, unknown>]
}>()

// ========== 表单状态 ==========
interface FormState { data: Record<string, unknown>; note: string; entered_at: string; next_assignee_id: number | null; next_due_at: string }
const formRef = ref<FormInstance | null>(null)
const formData = ref<FormState>({ data: {}, note: '', entered_at: '', next_assignee_id: null, next_due_at: '' })

const dialogTitle = computed(() => props.record ? `编辑「${stageLabel(props.stage)}」阶段记录` : `录入「${stageLabel(props.stage)}」阶段数据`)
const currentSchema = computed(() => props.stageSchema[props.stage] || {})

const rules = computed<FormRules>(() => {
  const r: FormRules = { 'data.*': [{ required: false, trigger: 'blur' }] }
  for (const [key, f] of Object.entries(currentSchema.value)) {
    if (f.required) {
      r[`data.${key}`] = [{ required: true, message: `请填写「${f.label}」`, trigger: f.type === 'select' || f.type === 'user' || f.type === 'users' ? 'change' : 'blur' }]
    }
  }
  return r
})

// ========== 附件状态 (多文件批量上传) ==========
const fileList = ref<StageFile[]>([])
const pendingFiles = ref<File[]>([])
const fileNotesInput = ref('')
const fileUploading = ref(false)
const uploadProgress = ref(0)

const loadFiles = async () => {
  if (!props.oppId) return
  try {
    const r = await get(`/sales/opps/${props.oppId}/stage-files`, { stage: props.stage })
    const resp = r as unknown as { data?: StageFile[] }
    fileList.value = resp?.data || []
  } catch { fileList.value = [] }
}

const handleFilesChange = (uploadFile: { raw?: File }) => {
  if (uploadFile.raw) {
    // 去重：同名同大小不重复加
    const dup = pendingFiles.value.some((f) => f.name === uploadFile.raw?.name && f.size === uploadFile.raw?.size)
    if (!dup) pendingFiles.value.push(uploadFile.raw)
  }
}

const removePendingFile = (idx: number) => {
  pendingFiles.value.splice(idx, 1)
}

const handleBatchUpload = async () => {
  if (pendingFiles.value.length === 0) { ElMessage.warning('请先选择文件'); return }
  fileUploading.value = true
  uploadProgress.value = 0
  let success = 0
  let fail = 0
  const notes = fileNotesInput.value

  for (const f of pendingFiles.value) {
    try {
      const formData = new FormData()
      formData.append('file', f)
      formData.append('stage', props.stage)
      if (notes) formData.append('notes', notes)
      const r = await post(`/sales/opps/${props.oppId}/stage-files`, formData)
      const json = r as unknown as { code?: number }
      if (json.code === 0) success++
      else fail++
    } catch {
      fail++
    }
    uploadProgress.value++
  }

  ElMessage.success(`上传完成：成功 ${success} 个${fail > 0 ? `，失败 ${fail} 个` : ''}`)
  pendingFiles.value = []
  fileNotesInput.value = ''
  fileUploading.value = false
  uploadProgress.value = 0
  await loadFiles()
}

const handleFileDownload = async (f: StageFile) => {
  if (!f.exists) { ElMessage.warning('文件已被删除'); return }
  try {
    // 用 axios 下载（自动带 token），然后触发浏览器下载
    const blob = await get(`/sales/opps/${props.oppId}/stage-files/${f.id}/download`, {}, { responseType: 'blob' } as Record<string, unknown>)
    const url = URL.createObjectURL(blob as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = f.original_name
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    ElMessage.error('下载失败')
  }
}

const handleFileDelete = async (f: StageFile) => {
  try {
    await del(`/sales/opps/${props.oppId}/stage-files/${f.id}`)
    ElMessage.success('已删除')
    await loadFiles()
  } catch { ElMessage.error('删除失败') }
}

const fileIconColor = (mime?: string | null): string => {
  if (!mime) return '#409EFF'
  if (mime.startsWith('image/')) return '#67C23A'
  if (mime.includes('pdf')) return '#F56C6C'
  if (mime.includes('cad') || mime.includes('dwg') || mime.includes('dxf')) return '#BA7517'
  return '#409EFF'
}

/** 上传提示 */
const stageHint = computed(() => {
  const hints: Record<string, string> = {
    inquiry: '客户需求文档、招标书、原始图纸',
    qualification: '资质文件、营业执照、许可证',
    site_survey: '地勘手图、CAD 简图、现场照片',
    proposal: '详细图纸、设备清单、方案文档',
    negotiating: '报价单、谈判记录、其他文件',
    quoted: '最终报价文件',
    won: '合同扫描件、成交通知书',
    lost: '战败分析报告',
  }
  return hints[props.stage] || '相关文件'
})

// ========== 初始化 ==========
watch(() => [props.visible, props.stage, props.record, props.oppId], async ([v]) => {
  if (!v) return
  await nextTick()
  if (props.record) {
    formData.value = {
      data: { ...(props.record.data || {}) },
      note: props.record.note || '',
      entered_at: props.record.entered_at || '',
      next_assignee_id: props.record.next_assignee_id || null,
      next_due_at: props.record.next_due_at || '',
    }
  } else {
    formData.value = { data: {}, note: '', entered_at: new Date().toISOString().slice(0, 19).replace('T', ' '), next_assignee_id: null, next_due_at: '' }
  }
  // 同步加载附件
  await loadFiles()
})

const handleSubmit = async () => {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  const cleanedData: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(formData.value.data)) {
    if (v !== null && v !== '' && v !== undefined && !(Array.isArray(v) && v.length === 0)) cleanedData[k] = v
  }
  emit('save', {
    stage: props.stage, data: cleanedData, note: formData.value.note || null,
    entered_at: formData.value.entered_at || null,
    next_assignee_id: formData.value.next_assignee_id || null,
    next_due_at: formData.value.next_due_at || null,
  })
}
</script>

<style lang="scss" scoped>
.row-2-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.divider-title { font-size: 14px; font-weight: 600; color: #0C447C; margin-right: 8px; }
.divider-hint { font-size: 12px; color: #909399; font-weight: normal; }

/* 上传栏 */
.file-upload-bar {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  padding: 8px 12px; background: #fafafa; border: 1px dashed #dcdfe6; border-radius: 6px; margin-bottom: 8px;
}
.file-hint { font-size: 11px; color: #909399; }

/* 待上传文件列表 */
.pending-files {
  padding: 8px 12px; background: #fff; border: 1px solid #e4e7ed; border-radius: 6px; margin-bottom: 8px;
}
.pending-header {
  display: flex; justify-content: space-between; align-items: center;
  font-size: 13px; font-weight: 600; color: #303133; margin-bottom: 6px;
}
.pending-row {
  display: flex; align-items: center; gap: 6px; padding: 4px 0;
  font-size: 12px; color: #606266;
}
.pending-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pending-notes { margin-top: 6px; }

.file-list { display: flex; flex-direction: column; gap: 4px; }
.file-row {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 8px; background: #f5f7fa; border-radius: 4px;
  .file-icon { flex-shrink: 0; }
  .file-name { flex: 1; font-size: 13px; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
  .file-notes { font-size: 11px; color: #BA7517; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .file-actions { flex-shrink: 0; display: flex; gap: 2px; }
}
.file-empty { font-size: 12px; color: #c0c4cc; text-align: center; padding: 8px; }
</style>