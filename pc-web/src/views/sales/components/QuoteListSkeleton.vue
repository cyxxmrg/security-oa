<script setup lang="ts">
/**
 * QuoteListSkeleton — 报价单列表骨架屏 (v0.3.14 B3)
 * 用于 loadList 加载时显示，避免左侧列表空白
 */
import { Document } from '@element-plus/icons-vue'

defineProps<{ count?: number }>()
</script>

<template>
  <div class="skeleton-list">
    <div class="list-header">报价版本</div>
    <div v-for="i in (count ?? 3)" :key="i" class="skel-item">
      <div class="skel-row">
        <el-skeleton-item variant="text" style="width: 30px; height: 18px" />
        <el-skeleton-item variant="text" style="width: 50px; height: 18px" />
      </div>
      <el-skeleton-item variant="text" style="width: 80%; margin-top: 6px" />
      <el-skeleton-item variant="text" style="width: 60%; margin-top: 4px" />
      <el-skeleton-item variant="text" style="width: 40%; margin-top: 4px" />
    </div>
    <div class="skel-foot">
      <el-icon class="is-loading"><Document /></el-icon>
      <span>报价单加载中…</span>
    </div>
  </div>
</template>

<style scoped>
.skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 4px 0;
}
.list-header {
  font-size: 12px;
  color: #909399;
  padding: 0 12px 4px;
  border-bottom: 1px solid #ebeef5;
}
.skel-item {
  padding: 12px;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  background: #fafafa;
}
.skel-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.skel-foot {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  color: #909399;
  padding: 8px;
}
</style>
