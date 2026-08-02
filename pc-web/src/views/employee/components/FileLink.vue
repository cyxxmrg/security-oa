<template>
  <a v-if="fileId" class="file-link" :href="downloadUrl" target="_blank" rel="noopener">
    <el-icon><Download /></el-icon>
    <span>{{ name || '下载文件' }}</span>
  </a>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Download } from '@element-plus/icons-vue'

const props = defineProps<{
  fileId: number | string
  name?: string
}>()

const downloadUrl = computed(() => {
  return `/api/disk/files/${props.fileId}/download`
})
</script>

<style lang="scss" scoped>
.file-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: 12px;
  padding: 2px 10px;
  font-size: 12px;
  color: #0C447C;
  background: #e6f1fb;
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.2s;

  &:hover {
    background: #0C447C;
    color: #fff;
  }
  .el-icon {
    font-size: 12px;
  }
}
</style>
