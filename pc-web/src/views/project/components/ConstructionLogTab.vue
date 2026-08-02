<template>
  <div class="tab-content">
    <div class="tab-toolbar">
      <h3 class="section-title">施工日志 ({{ filteredLogs.length }})</h3>
      <div class="toolbar-actions">
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          size="default"
          style="width: 280px"
          @change="onDateChange"
        />
        <el-button :icon="Download" @click="$emit('export')">导出</el-button>
        <el-button type="primary" :icon="Plus" @click="$emit('add')">添加日志</el-button>
      </div>
    </div>

    <el-table :data="filteredLogs" border>
      <el-table-column label="日期" width="120">
        <template #default="{ row }">{{ formatDate(row.work_date || row.date) }}</template>
      </el-table-column>
      <el-table-column prop="weather" label="天气" width="100" align="center">
        <template #default="{ row }">
          <span class="weather-cell">
            <span class="weather-icon">{{ weatherIcon(row.weather) }}</span>
            {{ row.weather || '-' }}
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="content" label="施工内容" min-width="240" show-overflow-tooltip>
        <template #default="{ row }">
          <span v-if="row.is_rectification" style="display:inline-flex;align-items:center;gap:4px">
            <el-tag size="small" type="warning" effect="plain">整改</el-tag>
            {{ row.content || row.rectify_result || '-' }}
          </span>
          <span v-else>{{ row.content || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="work_hours" label="工时(h)" width="100" align="center">
        <template #default="{ row }">{{ Number(row.work_hours || 0).toFixed(1) }}</template>
      </el-table-column>
      <el-table-column prop="problems" label="问题" min-width="180" show-overflow-tooltip>
        <template #default="{ row }">{{ row.problems || '-' }}</template>
      </el-table-column>
      <el-table-column label="施工团队" width="120">
        <template #default="{ row }">
          {{ row.team?.team_name || row.team_name || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="操作" width="120" align="center" fixed="right">
        <template #default="{ row }">
          <el-button text type="primary" @click="$emit('view', row)">详情</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-empty v-if="filteredLogs.length === 0" description="暂无施工日志" :image-size="80" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Plus, Download } from '@element-plus/icons-vue'
import { type ConstructionLog, formatDate } from '../types'

const props = defineProps<{ logs: ConstructionLog[] }>()

defineEmits<{
  (e: 'add'): void
  (e: 'export'): void
  (e: 'view', row: ConstructionLog): void
}>()

const dateRange = ref<[string, string] | null>(null)

const filteredLogs = computed(() => {
  if (!dateRange.value) return props.logs
  const [start, end] = dateRange.value
  return props.logs.filter(l => {
    const d = String(l.work_date || l.date || '').slice(0, 10)
    return d >= start && d <= end
  })
})

const onDateChange = () => { /* 由 dateRange computed 自动响应 */ }

const weatherIcon = (w?: string) => {
  if (w === '晴') return '☀️'
  if (w === '多云') return '⛅'
  if (w === '雨') return '🌧'
  if (w === '雪') return '❄️'
  return '🌤'
}
</script>

<style scoped>
.tab-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
}
.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  margin: 0;
  padding-left: 8px;
  border-left: 3px solid #0C447C;
}
.toolbar-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}
.weather-cell {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.weather-icon {
  font-size: 16px;
}
</style>
