<template>
  <div class="photo-stepper">
    <div class="step-bar">
      <div v-for="(label, key) in STEPS" :key="key" class="step-pill" :class="stepClass(key)" @click="selectStep(key)">
        <span class="step-icon">{{ label.split(' ')[0] }}</span>
        <span class="step-name">{{ label.split(' ').slice(1).join(' ') }}</span>
        <el-badge v-if="counts[key]" :value="counts[key]" :max="99" type="primary" style="margin-left: 4px" />
      </div>
    </div>

    <div class="step-uploader">
      <div class="step-title">
        <span class="step-current">{{ STEPS[currentStep] }}</span>
        <span class="step-count">已上传 {{ currentStepPhotos.length }} 张</span>
      </div>
      <el-upload
        :http-request="uploadPhoto"
        :show-file-list="false"
        accept="image/*"
        multiple
      >
        <el-button type="primary" :icon="Camera" :loading="uploading">
          上传 {{ STEPS[currentStep] }} 照片
        </el-button>
      </el-upload>
      <el-input v-model="description" placeholder="描述 (可选)" style="margin-top: 8px" maxlength="500" show-word-limit />
    </div>

    <div v-if="!currentStepPhotos.length" class="empty">
      <el-icon><Picture /></el-icon>
      <span>暂无 {{ STEPS[currentStep] }} 照片</span>
    </div>
    <div v-else class="photo-grid">
      <div v-for="p in currentStepPhotos" :key="p.id" class="photo-card">
        <el-image :src="p.file_url" :preview-src-list="[p.file_url]" fit="cover" />
        <div class="photo-meta">
          <span class="photo-time">{{ formatTime(p.uploaded_at) }}</span>
          <el-button link type="danger" size="small" @click="deletePhoto(p.id)">删除</el-button>
        </div>
        <div v-if="p.description" class="photo-desc">{{ p.description }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Camera, Picture } from '@element-plus/icons-vue'
import { get, post, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'

const props = defineProps<{
  targetType: 'work_order' | 'repair_order'
  targetId: number
}>()

const STEPS: Record<string, string> = {
  diagnose:     '🔍 诊断',
  disassemble:  '🔧 拆机',
  replace:      '🔄 换件',
  debug:        '⚙️ 调试',
  power_on:     '⚡ 通电',
  test:         '✅ 测试',
  package:      '📦 包装',
  other:        '📌 其他',
}

const photos = ref<Record<string, unknown>[]>([])
const counts = ref<Record<string, number>>({})
const currentStep = ref<keyof typeof STEPS>('diagnose')
const uploading = ref(false)
const description = ref('')

const currentStepPhotos = computed(() =>
  photos.value.filter(p => p.step === currentStep.value)
)

const stepClass = (key: string) => ({
  active: key === currentStep.value,
  has_photo: (counts.value[key] || 0) > 0,
})

const selectStep = (key: keyof typeof STEPS) => {
  currentStep.value = key
  description.value = ''
}

const formatTime = (s: string) => {
  if (!s) return ''
  const d = new Date(s)
  return `${d.getMonth() + 1}-${d.getDate()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

const loadPhotos = async () => {
  try {
    const res = await get('/step-photos', {
      target_type: props.targetType,
      target_id: props.targetId,
    })
    // V0.6.3: res = {code, data: <photos>} 可能是 array 或 {items:[]}
    photos.value = unwrapList(res)
    const newCounts: Record<string, number> = {}
    for (const p of photos.value) {
      newCounts[p.step] = (newCounts[p.step] || 0) + 1
    }
    counts.value = newCounts
  } catch { photos.value = [] }
}

const uploadPhoto = async (option: Record<string, unknown>) => {
  uploading.value = true
  const fd = new FormData()
  fd.append('file', option.file)
  fd.append('target_type', props.targetType)
  fd.append('target_id', String(props.targetId))
  fd.append('step', currentStep.value)
  if (description.value) fd.append('description', description.value)
  try {
    await post('/step-photos', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    ElMessage.success('上传成功')
    description.value = ''
    await loadPhotos()
  } catch (e: unknown) {
    ElMessage.error(e?.message || '上传失败')
  } finally { uploading.value = false }
}

const deletePhoto = async (id: number) => {
  try { await ElMessageBox.confirm('确定删除这张照片?', '提示', { type: 'warning' }) } catch { return }
  try {
    await del(`/step-photos/${id}`)
    ElMessage.success('已删除')
    await loadPhotos()
  } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
}

watch(() => props.targetId, () => loadPhotos())
onMounted(() => loadPhotos())
</script>

<style scoped lang="scss">
.photo-stepper { padding: 12px; background: #fff; border-radius: 8px; }
.step-bar { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.step-pill {
  display: flex; align-items: center; gap: 4px;
  padding: 6px 12px; border-radius: 16px;
  background: #F4F4F5; color: #606266;
  font-size: 12px; cursor: pointer; transition: all 0.15s;
  border: 1px solid transparent;
}
.step-pill:hover { background: #ECF5FF; color: #409EFF; }
.step-pill.active { background: #409EFF; color: #fff; border-color: #409EFF; }
.step-pill.has_photo { border-color: #67C23A; }
.step-pill.active.has_photo { background: #67C23A; border-color: #67C23A; }
.step-icon { font-size: 14px; }
.step-name { font-weight: 500; }

.step-uploader {
  background: #FAFAFA; padding: 12px; border-radius: 6px; margin-bottom: 12px;
  display: flex; flex-direction: column;
}
.step-title { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.step-current { font-size: 14px; font-weight: 600; color: #303133; }
.step-count { font-size: 11px; color: #909399; }

.empty {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 40px; color: #C0C4CC; font-size: 13px;
}

.photo-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px;
}
.photo-card {
  background: #fff; border: 1px solid #EBEEF5; border-radius: 6px;
  padding: 4px; position: relative;
}
.photo-card .el-image {
  width: 100%; height: 120px; border-radius: 4px;
}
.photo-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; }
.photo-time { font-size: 10px; color: #909399; }
.photo-desc {
  font-size: 11px; color: #606266; margin-top: 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

@media (max-width: 768px) {
  .step-pill { font-size: 11px; padding: 4px 8px; }
  .photo-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
