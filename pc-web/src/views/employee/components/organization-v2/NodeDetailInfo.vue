<template>
  <div>
    <template v-if="node && node.type === 'dept'">
      <div class="detail-info">
        <div class="info-row">
          <span class="info-label">上级部门</span>
          <span class="info-value">{{ node.parent || fallbackName || '公司根' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">部门负责人</span>
          <span class="info-value">{{ node.manager || '--' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">员工人数</span>
          <span class="info-value">{{ node.count || 0 }} 人</span>
        </div>
        <div class="info-row">
          <span class="info-label">下属岗位</span>
          <span class="info-value">{{ subPositionCount }} 个</span>
        </div>
      </div>
      <div class="detail-divider">部门员工</div>
    </template>
    <template v-else-if="node && node.type === 'position'">
      <div class="detail-info">
        <div class="info-row">
          <span class="info-label">所属部门</span>
          <span class="info-value">{{ node.department || '--' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">职级</span>
          <span class="info-value">{{ node.level || '--' }}</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  node: Record<string, unknown>
  fallbackName: string
  subPositionCount: number
}>()
</script>

<style lang="scss" scoped>
.detail-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: #f5f7fa;
  border-radius: 6px;
  margin-bottom: 12px;
}
.info-row { display: flex; gap: 12px; font-size: 13px; }
.info-label { color: #909399; min-width: 80px; }
.info-value { color: #303133; font-weight: 500; }
.detail-divider {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin: 16px 0 12px;
  padding-left: 8px;
  border-left: 3px solid #1D9E75;
}
</style>
