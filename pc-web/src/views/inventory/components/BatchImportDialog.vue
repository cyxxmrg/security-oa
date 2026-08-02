<template>
  <el-dialog
    :model-value="visible"
    title="批量导入物品"
    width="780px"
    :close-on-click-modal="false"
    destroy-on-close
    @update:model-value="(v: boolean) => emit('update:visible', v)"
  >
    <div class="batch-import">
      <!-- 步骤 1: 模板 + 上传 -->
      <div class="step-block" v-if="step === 1">
        <div class="step-tip">
          <el-alert type="info" :closable="false" show-icon>
            <template #title>
              请先下载模板, 按照格式填写后上传。支持 .xlsx / .csv 文件 (≤ 10MB)
            </template>
          </el-alert>
        </div>
        <div class="template-row">
          <el-button :icon="Download" @click="downloadTemplate" :loading="downloading">
            下载模板
          </el-button>
        </div>
        <el-upload
          ref="uploadRef"
          class="upload-area"
          :auto-upload="false"
          :limit="1"
          :accept="'.xlsx,.csv'"
          :on-change="onFileChange"
          :on-exceed="onExceed"
          drag
        >
          <el-icon class="upload-icon"><UploadFilled /></el-icon>
          <div class="el-upload__text">
            拖拽文件到此处, 或<em>点击选择</em>
          </div>
          <template #tip>
            <div class="el-upload__tip">
              仅支持 .xlsx / .csv 格式
            </div>
          </template>
        </el-upload>
        <div v-if="file" class="file-info">
          <el-icon><Document /></el-icon>
          <span>{{ file.name }}</span>
          <span class="file-size">({{ formatSize(file.size) }})</span>
          <el-button link type="danger" @click="clearFile">移除</el-button>
        </div>
      </div>

      <!-- 步骤 2: 上传中 -->
      <div class="step-block" v-else-if="step === 2">
        <div class="uploading">
          <el-icon class="is-loading" :size="40" color="#0C447C"><Loading /></el-icon>
          <p>正在导入, 请稍候...</p>
        </div>
      </div>

      <!-- 步骤 3: 结果 -->
      <div class="step-block" v-else-if="step === 3 && result">
        <div class="result-summary">
          <el-statistic title="已创建" :value="result.created?.length || 0" :value-style="{ color: '#1D9E75' }" />
          <el-statistic title="已跳过" :value="result.skipped?.length || 0" :value-style="{ color: '#909399' }" />
          <el-statistic title="错误"    :value="result.errors?.length  || 0" :value-style="{ color: '#A32D2D' }" />
        </div>

        <el-tabs v-model="resultTab" class="result-tabs">
          <el-tab-pane :label="`已创建 (${result.created?.length || 0})`" name="created">
            <el-table :data="result.created" border size="small" max-height="240">
              <el-table-column prop="row" label="行号" width="80" />
              <el-table-column prop="code" label="编码" />
              <el-table-column prop="name" label="名称" />
              <el-table-column prop="id" label="ID" width="80" />
            </el-table>
            <el-empty v-if="!result.created?.length" :image-size="60" description="无新增" />
          </el-tab-pane>

          <el-tab-pane :label="`已跳过 (${result.skipped?.length || 0})`" name="skipped">
            <el-table :data="result.skipped" border size="small" max-height="240">
              <el-table-column prop="row" label="行号" width="80" />
              <el-table-column prop="code" label="编码" />
              <el-table-column prop="name" label="名称" />
              <el-table-column prop="reason" label="原因" show-overflow-tooltip />
            </el-table>
            <el-empty v-if="!result.skipped?.length" :image-size="60" description="无跳过" />
          </el-tab-pane>

          <el-tab-pane :label="`错误 (${result.errors?.length || 0})`" name="errors">
            <el-table :data="result.errors" border size="small" max-height="240">
              <el-table-column prop="row" label="行号" width="80" />
              <el-table-column prop="field" label="字段" width="120" />
              <el-table-column prop="message" label="错误信息" show-overflow-tooltip />
            </el-table>
            <el-empty v-if="!result.errors?.length" :image-size="60" description="无错误" />
          </el-tab-pane>
        </el-tabs>
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="emit('update:visible', false)">{{ step === 3 ? '关闭' : '取消' }}</el-button>
        <el-button
          v-if="step === 1"
          type="primary"
          :disabled="!file"
          :loading="uploading"
          @click="doImport"
        >
          开始导入
        </el-button>
        <el-button
          v-if="step === 3"
          type="primary"
          @click="onComplete"
        >
          完成
        </el-button>
        <el-button
          v-if="step === 3"
          @click="resetAndReimport"
        >
          继续导入
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ElMessage, type UploadInstance, type UploadFile, type UploadRawFile, type UploadProps } from 'element-plus'
import { Download, Document, UploadFilled, Loading } from '@element-plus/icons-vue'
import { inventory } from '@/api/modules'

const props = defineProps<{
  visible: boolean
}>()

interface ImportResult {
  created?: Array<{ row?: number; code?: string; name?: string; id?: number }>
  skipped?: Array<{ row?: number; code?: string; name?: string; reason?: string }>
  errors?: Array<{ row?: number; field?: string; message?: string }>
  [k: string]: unknown
}

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'success', payload: ImportResult | null): void
}>()

const step = ref(1) // 1: 选文件  2: 上传中  3: 结果
const file = ref<UploadFile | null>(null)
const uploadRef = ref<UploadInstance>()
const uploading = ref(false)
const downloading = ref(false)
const result = ref<ImportResult | null>(null)
const resultTab = ref('created')

const ACCEPT = ['xlsx', 'csv']

function onFileChange(uploadFile: UploadFile) {
  // 校验扩展名
  const name = uploadFile.name
  const ext = name.split('.').pop()?.toLowerCase()
  if (!ext || !ACCEPT.includes(ext)) {
    ElMessage.warning('仅支持 .xlsx / .csv 文件')
    uploadRef.value?.clearFiles()
    file.value = null
    return
  }
  file.value = uploadFile
}

const onExceed: UploadProps['onExceed'] = () => {
  ElMessage.warning('只能上传一个文件, 请先移除现有文件')
}

function clearFile() {
  uploadRef.value?.clearFiles()
  file.value = null
}

function formatSize(bytes?: number) {
  const n = bytes ?? 0
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / 1024 / 1024).toFixed(2)} MB`
}

async function downloadTemplate() {
  downloading.value = true
  try {
    const blob = await inventory.exportTemplate()
    const url = window.URL.createObjectURL(new Blob([blob as BlobPart]))
    const a = document.createElement('a')
    a.href = url
    a.download = `物品导入模板_${new Date().toISOString().slice(0, 10)}.xlsx`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    window.URL.revokeObjectURL(url)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '下载失败')
  } finally {
    downloading.value = false
  }
}

async function doImport() {
  if (!file.value?.raw) {
    ElMessage.warning('请先选择文件')
    return
  }
  step.value = 2
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file.value.raw as UploadRawFile)
    const res = await inventory.batchImport(fd)
    result.value = (res as ImportResult) || { created: [], skipped: [], errors: [] }
    step.value = 3
    // 不再自动 emit success, 等用户在 step 3 点击"完成"按钮
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '导入失败')
    step.value = 1
  } finally {
    uploading.value = false
  }
}

function onComplete() {
  emit('success', result.value)
}

function resetAndReimport() {
  file.value = null
  result.value = null
  resultTab.value = 'created'
  uploadRef.value?.clearFiles()
  step.value = 1
}
</script>

<style lang="scss" scoped>
.batch-import { padding: 4px 0; }
.step-block { min-height: 260px; }
.step-tip { margin-bottom: 12px; }
.template-row { margin: 12px 0; }
.upload-area {
  :deep(.el-upload-dragger) {
    padding: 32px 16px;
  }
}
.upload-icon { font-size: 48px; color: #0C447C; margin-bottom: 8px; }
.file-info {
  display: flex; align-items: center; gap: 6px;
  margin-top: 12px; padding: 8px 12px;
  background: #f5f7fa; border-radius: 4px;
  font-size: 13px;
  .file-size { color: #909399; font-size: 12px; }
}
.uploading {
  display: flex; flex-direction: column; align-items: center;
  gap: 12px; padding: 60px 0;
  color: #606266;
}
.result-summary {
  display: flex; gap: 24px; padding: 16px;
  background: #fafbfc; border-radius: 6px; margin-bottom: 12px;
}
.result-tabs { margin-top: 8px; }
.dialog-footer { display: flex; justify-content: flex-end; gap: 8px; }
</style>
