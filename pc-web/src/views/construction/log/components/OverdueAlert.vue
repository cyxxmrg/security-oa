<template>
  <div class="overdue-alert">
    <el-alert
      v-for="item in overdueList"
      :key="item.id"
      :title="`漏报：${item.team_name || '团队'} 在 ${item.date} 未提交施工日志（项目：${item.project_name || '-'}）`"
      type="error"
      show-icon
      :closable="false"
      class="alert-item"
    >
      <template #default>
        <div class="alert-content">
          <span class="alert-text">
            漏报：<b>{{ item.team_name || '团队' }}</b> 在 <b>{{ item.date }}</b> 未提交施工日志
            <span v-if="item.project_name">（项目：{{ item.project_name }}）</span>
          </span>
          <el-button v-if="item.commencement_id" type="primary" link :icon="EditPen" @click="handleFill(item)">
            立即补报
          </el-button>
        </div>
      </template>
    </el-alert>
  </div>
</template>

<script setup lang="ts">
import { EditPen } from '@element-plus/icons-vue'
import type { OverdueItem } from '../../types'

defineProps<{
  overdueList: OverdueItem[]
}>()

const emit = defineEmits<{
  (e: 'fill', item: OverdueItem): void
}>()

const handleFill = (item: OverdueItem) => emit('fill', item)
</script>

<style lang="scss" scoped>
.overdue-alert { display: flex; flex-direction: column; gap: 8px; }
.alert-item { margin-bottom: 0; }
.alert-content { display: flex; align-items: center; justify-content: space-between; width: 100%; }
.alert-text { flex: 1; }
</style>
