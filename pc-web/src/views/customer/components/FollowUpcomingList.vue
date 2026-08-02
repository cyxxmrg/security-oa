<script setup lang="ts">
/**
 * 未来 7 天待办列表
 */
import { User } from '@element-plus/icons-vue'

defineProps<{
  upcoming: Record<string, unknown>[]
  formatTime: (t: string | undefined) => string
  countdownLabel: (t: string | undefined) => string
  statusTagType: (s: string | null | undefined) => 'success' | 'primary' | 'warning' | 'danger' | 'info'
  statusLabel: (s: string | null | undefined) => string
}>()

const emit = defineEmits<{
  (e: 'openItem', item: Record<string, unknown>): void
}>()
</script>

<template>
  <div class="upcoming-card glass">
    <div class="up-head">
      <span class="up-title">今日 + 未来 7 天</span>
      <span class="up-count">{{ upcoming.length }} 条</span>
    </div>
    <div class="up-list">
      <div v-if="upcoming.length === 0" class="up-empty">
        <el-empty description="近期无计划跟进" :image-size="80" />
      </div>
      <div
        v-for="item in upcoming"
        :key="item.id"
        class="up-item"
        @click="emit('openItem', item)"
      >
        <div class="up-time-col">
          <div class="up-time">{{ formatTime(item.scheduled_at || item.time) }}</div>
          <div class="up-day">{{ countdownLabel(item.scheduled_at || item.time) }}</div>
        </div>
        <div class="up-body">
          <div class="up-customer">{{ item.customer_name || '—' }}</div>
          <div class="up-meta">
            <el-icon><User /></el-icon>
            <span>{{ item.user_name || item.owner_name || item.follower || '—' }}</span>
            <el-tag :type="statusTagType(item.status || item.type)" size="small" effect="light" class="up-status">
              {{ statusLabel(item.status || item.type) }}
            </el-tag>
          </div>
          <div v-if="item.note" class="up-note">{{ item.note }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.upcoming-card {
  background: rgba(255, 255, 255, 0.75);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.5);
  border-radius: 12px;
  padding: 16px 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  max-height: 640px;
  overflow-y: auto;
}
.up-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #ebeef5;
}
.up-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}
.up-count {
  font-size: 12px;
  color: #409EFF;
  background: rgba(64, 158, 255, 0.1);
  padding: 2px 8px;
  border-radius: 10px;
}
.up-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.up-item {
  display: flex;
  gap: 12px;
  padding: 10px 12px;
  background: rgba(245, 247, 250, 0.6);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);

  &:hover {
    background: rgba(64, 158, 255, 0.06);
    transform: translateX(2px);
  }
}
.up-time-col {
  flex-shrink: 0;
  text-align: center;
  min-width: 60px;
}
.up-time {
  font-size: 14px;
  font-weight: 700;
  color: #409EFF;
}
.up-day {
  font-size: 11px;
  color: #909399;
  margin-top: 2px;
}
.up-body {
  flex: 1;
  min-width: 0;
}
.up-customer {
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 4px;
}
.up-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #606266;
}
.up-status {
  margin-left: auto;
}
.up-note {
  margin-top: 4px;
  font-size: 12px;
  color: #909399;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.up-empty {
  padding: 30px 0;
}
</style>
