<template>
  <div class="tab-content">
    <div class="tab-toolbar">
      <el-button type="primary" :icon="Plus" @click="emit('add')">添加跟进</el-button>
    </div>
    <el-timeline class="follow-timeline" v-if="records.length">
      <el-timeline-item
        v-for="(item, idx) in records"
        :key="item.id"
        :timestamp="formatDate(item.created_at)"
        :type="timelineType(item.type)"
        :hollow="idx !== 0"
        placement="top"
      >
        <el-card shadow="hover" class="timeline-card">
          <div class="card-head">
            <span class="follow-type">
              <el-tag :type="timelineType(item.type)" effect="light" size="small">
                {{ typeLabel(item.type) }}
              </el-tag>
            </span>
            <span class="follow-person">
              <el-icon><User /></el-icon>
              {{ item.user?.name || item.user?.username || '—' }}
            </span>
          </div>
          <div class="card-content">{{ item.content }}</div>
          <div v-if="item.next_follow_up_date" class="next-step">
            <el-icon><Bell /></el-icon>
            下次跟进：{{ item.next_follow_up_date }} {{ item.next_follow_up_note || '' }}
          </div>
        </el-card>
      </el-timeline-item>
    </el-timeline>
    <el-empty v-else description="暂无跟进记录" />
  </div>
</template>

<script setup lang="ts">
import { Plus, User, Bell } from '@element-plus/icons-vue'
import type { FollowRecord } from './types'
import { formatDate, timelineType, typeLabel } from './types'

// v0.3.20 抽自 customer/Detail.vue:178-215
defineProps<{
  records: FollowRecord[]
}>()

const emit = defineEmits<{
  (e: 'add'): void
}>()
</script>

<style lang="scss" scoped>
.tab-toolbar {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
}
.follow-timeline {
  padding: 16px 0;
}
.timeline-card {
  .card-head {
    display: flex;
    gap: 16px;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13px;
    color: #606266;
  }
  .card-content {
    color: #303133;
    line-height: 1.6;
  }
  .next-step {
    margin-top: 8px;
    padding: 8px 12px;
    background: #fdf6ec;
    color: #BA7517;
    border-radius: 4px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
}
</style>
