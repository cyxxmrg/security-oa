<template>
  <div class="status-bar" :class="`status-${wo.status}`">
    <div class="status-left">
      <el-button :icon="ArrowLeft" @click="goBack" circle size="small" />
      <div class="status-info">
        <div class="status-row1">
          <code class="wo-code">{{ wo.code }}</code>
          <el-tag :type="wo.status_color" effect="dark" size="default">{{ wo.status_label }}</el-tag>
          <el-tag :type="wo.priority_color" effect="plain" size="default">{{ wo.priority_label }}</el-tag>
          <el-icon v-if="wo.is_locked" class="locked" :title="'已锁定 (转返修/已取消/已解决)'"><Lock /></el-icon>
        </div>
        <div class="status-row2">
          <span><el-icon><User /></el-icon> {{ wo.customer_name || wo.contact_name || '—' }}</span>
          <span><el-icon><Phone /></el-icon> {{ wo.contact_phone || '—' }}</span>
          <span v-if="wo.assignee_name"><el-icon><Avatar /></el-icon> {{ wo.assignee_name }}</span>
        </div>
      </div>
    </div>
    <div class="status-right">
      <el-button v-if="wo.status === 'pending' && !wo.is_locked" type="primary" :icon="Promotion" @click="$emit('assign')" size="small">派单</el-button>
      <el-button v-if="wo.status === 'assigned' && !wo.is_locked" type="warning" :icon="VideoPlay" @click="$emit('start')" size="small">开始服务</el-button>
      <el-button v-if="wo.status === 'in_progress' && !wo.is_locked" type="success" :icon="CircleCheck" @click="$emit('resolve')" size="small">完成</el-button>
      <el-button v-if="wo.status === 'in_progress' && !wo.is_locked" type="danger" :icon="RefreshRight" @click="$emit('convert')" size="small">转返修</el-button>
      <!-- V0.6.3: 完成后可发起采购需求 -->
      <el-button v-if="(wo.status === 'completed' || wo.status === 'resolved') && !wo.is_locked" type="primary" plain :icon="ShoppingCart" @click="$emit('open-purchase')" size="small">
        发起采购
      </el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ArrowLeft, User, Phone, Avatar, Lock, Promotion, VideoPlay, CircleCheck, RefreshRight, ShoppingCart } from '@element-plus/icons-vue'

defineProps<{
  wo: Record<string, unknown>
}>()

defineEmits<{
  'assign': []
  'start': []
  'resolve': []
  'convert': []
  'open-purchase': []
}>()

const goBack = () => history.back()
</script>

<style scoped lang="scss">
.status-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  padding: 16px 20px;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  border-left: 4px solid #409EFF;
  &.status-pending { border-left-color: #909399; }
  &.status-assigned { border-left-color: #409EFF; }
  &.status-in_progress { border-left-color: #E6A23C; }
  &.status-resolved { border-left-color: #67C23A; }
  &.status-cancelled { border-left-color: #909399; }
  &.status-converted_to_repair { border-left-color: #F56C6C; }
}
.status-left { display: flex; align-items: center; gap: 12px; flex: 1; }
.status-info { flex: 1; }
.status-row1 { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.wo-code { font-size: 18px; font-weight: 600; color: #303133; }
.status-row2 {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: #606266;
  span { display: flex; align-items: center; gap: 4px; }
}
.status-right { display: flex; gap: 8px; }
.locked { color: #F56C6C; font-size: 16px; }

@media (max-width: 768px) {
  .status-bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  .status-right { display: none; }
}
</style>
