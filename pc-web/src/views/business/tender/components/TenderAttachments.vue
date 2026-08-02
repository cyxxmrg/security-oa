<template>
  <div>
    <el-upload :http-request="onUpload" :show-file-list="false" :before-upload="beforeUpload" accept="*/*">
      <el-button type="primary" :icon="Upload">上传招标文件</el-button>
    </el-upload>
    <el-table :data="attachments" border size="small" style="margin-top:12px" empty-text="暂无附件">
      <el-table-column prop="file_name" label="文件名" min-width="220" show-overflow-tooltip />
      <el-table-column prop="category" label="类别" width="120" />
      <el-table-column prop="visibility" label="可见性" width="100" />
      <el-table-column label="大小" width="100">
        <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openFile(row)">预览</el-button>
          <el-button link type="danger" @click="onDeleteAtt(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { Upload } from '@element-plus/icons-vue'
import type { TenderAttachment } from '@/api/tender'
import { formatSize } from '../utils'

defineProps<{
  attachments: TenderAttachment[]
  beforeUpload: (file: File) => boolean | void
  onUpload: (opt: Record<string, unknown>) => void | Promise<void>
  openFile: (att: TenderAttachment) => void
  onDeleteAtt: (att: TenderAttachment) => void | Promise<void>
}>()
</script>
