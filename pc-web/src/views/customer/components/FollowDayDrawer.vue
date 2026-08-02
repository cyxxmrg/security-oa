<script setup lang="ts">
/**
 * 跟进详情抽屉
 */
import { User, Postcard } from '@element-plus/icons-vue'

defineProps<{
  visible: boolean
  title: string
  events: Record<string, unknown>[]
  formatTime: (t: string | undefined) => string
  statusTagType: (s: string | null | undefined) => 'success' | 'primary' | 'warning' | 'danger' | 'info'
  statusLabel: (s: string | null | undefined) => string
  followTypeLabel: (t: string | null | undefined) => string
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
}>()
</script>

<template>
  <el-drawer
    :model-value="visible"
    :title="title"
    size="480px"
    direction="rtl"
    @update:model-value="emit('update:visible', $event)"
  >
    <div v-if="events.length === 0" class="drawer-empty">
      <el-empty description="当日无跟进记录" :image-size="80" />
    </div>
    <div v-else class="drawer-list">
      <div v-for="ev in events" :key="ev.id" class="drawer-item">
        <div class="di-top">
          <span class="di-time">{{ formatTime(ev.scheduled_at || ev.time) }}</span>
          <el-tag :type="statusTagType(ev.status)" size="small" effect="dark" round>
            {{ statusLabel(ev.status) }}
          </el-tag>
        </div>
        <div class="di-customer">{{ ev.customer_name || '—' }}</div>
        <div class="di-meta">
          <span><el-icon><User /></el-icon> {{ ev.user_name || ev.owner_name || '—' }}</span>
          <span><el-icon><Postcard /></el-icon> {{ followTypeLabel(ev.type) }}</span>
        </div>
        <div v-if="ev.content" class="di-content">{{ ev.content }}</div>
      </div>
    </div>
  </el-drawer>
</template>

<style lang="scss" scoped>
.drawer-empty {
  padding: 80px 0;
}
.drawer-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.drawer-item {
  padding: 14px 16px;
  background: rgba(245, 247, 250, 0.7);
  border-radius: 10px;
  border: 1px solid rgba(0, 0, 0, 0.04);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);

  &:hover {
    border-color: rgba(64, 158, 255, 0.3);
    box-shadow: 0 2px 8px rgba(64, 158, 255, 0.1);
  }
}
.di-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}
.di-time {
  font-size: 14px;
  font-weight: 700;
  color: #409EFF;
}
.di-customer {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 6px;
}
.di-meta {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: #606266;
  margin-bottom: 6px;

  span {
    display: flex;
    align-items: center;
    gap: 4px;
  }
}
.di-content {
  font-size: 13px;
  color: #303133;
  padding: 8px 10px;
  background: rgba(255, 255, 255, 0.7);
  border-radius: 6px;
  border-left: 3px solid #409EFF;
  line-height: 1.5;
}
</style>
