<script setup lang="ts">
/**
 * ImportDialog — 批量导入客户 (v0.3.14 C2)
 * 上传 + 显示导入结果
 */
import { ref, watch } from 'vue'
import { UploadFilled, Download } from '@element-plus/icons-vue'

const props = withDefaults(
  defineProps<{
    visible: boolean
    importing: boolean
    result: { success: number; failed: number } | null
  }>(),
  { visible: false, importing: false, result: null },
)

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'confirm', file: File): void
  (e: 'download-template'): void
}>()

const dialogVisible = ref(props.visible)
watch(() => props.visible, (v) => (dialogVisible.value = v))
const close = () => emit('update:visible', false)

const uploadRef = ref()
const file = ref<File | null>(null)
const handleChange = (f: { size: number; name?: string }) => {
  file.value = f.raw
}

const handleConfirm = () => {
  if (!file.value) {
    return
  }
  emit('confirm', file.value)
}
</script>

<template>
  <el-dialog
    :model-value="dialogVisible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="批量导入客户"
    width="560px"
    destroy-on-close
    :close-on-click-modal="false"
    @close="close"
  >
    <el-alert
      title="使用说明"
      type="info"
      :closable="false"
      style="margin-bottom: 12px"
    >
      <p>1. 下载导入模板，填写客户信息</p>
      <p>2. 上传 CSV 文件（UTF-8 编码，列名匹配）</p>
      <p>3. 必填列：客户名称、所属行业、联系人、联系电话、客户分类</p>
    </el-alert>

    <el-upload
      ref="uploadRef"
      drag
      :auto-upload="false"
      :show-file-list="true"
      :limit="1"
      :on-change="handleChange"
      accept=".csv,.xlsx,.xls"
    >
      <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
      <div class="el-upload__text">
        拖拽文件到此处，或<em>点击选择</em>
      </div>
      <template #tip>
        <div class="el-upload__tip">
          支持 CSV / Excel 格式，单文件不超过 10MB
        </div>
      </template>
    </el-upload>

    <div v-if="result" class="import-result">
      <el-alert
        :title="`导入完成：成功 ${result.success} 条，失败 ${result.failed} 条`"
        :type="result.failed > 0 ? 'warning' : 'success'"
        :closable="false"
        show-icon
      />
    </div>

    <template #footer>
      <el-button :icon="Download" @click="emit('download-template')">下载模板</el-button>
      <el-button @click="close">取消</el-button>
      <el-button type="primary" :loading="importing" :disabled="!file" @click="handleConfirm">
        开始导入
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.import-result { margin-top: 12px; }
:deep(.el-upload-dragger) { padding: 24px; }
</style>
