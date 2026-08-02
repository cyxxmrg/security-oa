<template>
  <el-card class="info-card" shadow="never">
    <template #header>
      <div class="card-header">
        <span class="card-title">
          <el-icon><CircleCheck /></el-icon>验收记录
          <el-tag size="small" type="info" effect="plain" style="margin-left: 6px">
            {{ inspectionList.length }} 条
          </el-tag>
        </span>
        <el-button type="primary" :icon="Plus" size="small" @click="emit('add')">
          新增验收
        </el-button>
      </div>
    </template>

    <el-table
      :data="inspectionList"
      stripe
      border
      style="width: 100%"
      empty-text="暂无验收记录"
      :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
      :row-style="{ height: '50px' }"
    >
      <el-table-column prop="id" label="ID" width="70" align="center">
        <template #default="{ row }">
          <span class="id-text">#{{ row.id }}</span>
        </template>
      </el-table-column>
      <el-table-column label="验收类型" width="100" align="center">
        <template #default="{ row }">{{ inspectionTypeLabel(row.inspection_type) }}</template>
      </el-table-column>
      <el-table-column label="验收人" min-width="120">
        <template #default="{ row }">
          <span v-if="getInspectorName(row)">{{ getInspectorName(row) }}</span>
          <span v-else class="muted">-</span>
        </template>
      </el-table-column>
      <el-table-column label="验收时间" min-width="160">
        <template #default="{ row }">
          <span class="date-text">{{ formatDate(row.inspection_date || row.inspected_at) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="评分" width="80" align="center">
        <template #default="{ row }">
          <span
            v-if="row.score !== null && row.score !== undefined"
            style="color: #0C447C; font-weight: 600"
          >{{ row.score }}</span>
          <span v-else class="muted">-</span>
        </template>
      </el-table-column>
      <el-table-column label="结果" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="resultTagType(resultKey(row.result))" effect="dark" size="small">
            {{ resultLabel(resultKey(row.result)) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="整改项/缺陷" min-width="180" show-overflow-tooltip>
        <template #default="{ row }">
          <span v-if="getDefects(row)" class="defect-text">{{ getDefects(row) }}</span>
          <span v-else class="muted">-</span>
        </template>
      </el-table-column>
      <el-table-column label="备注" min-width="180" show-overflow-tooltip>
        <template #default="{ row }">
          <span v-if="row.remark">{{ row.remark }}</span>
          <span v-else class="muted">-</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="120" align="center" fixed="right">
        <template #default="{ row }">
          <el-button link type="danger" size="small" @click="emit('delete', row as unknown as Inspection)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<script setup lang="ts">
import { CircleCheck, Plus } from '@element-plus/icons-vue'
import {
  inspectionTypeLabel, resultKey, resultLabel, resultTagType,
  formatDate, getInspectorName, getDefects,
  type Inspection,
} from '../types'

defineProps<{ inspectionList: Inspection[] }>()
const emit = defineEmits<{
  (e: 'add'): void
  (e: 'delete', row: Inspection): void
}>()
</script>

<style scoped>
.info-card {
  border-radius: 8px;
  border: none;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
:deep(.el-card__header) {
  padding: 14px 20px;
  background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
  border-bottom: 1px solid #f0f2f5;
}
:deep(.el-card__body) { padding: 18px 20px; }
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.card-title {
  font-size: 15px;
  font-weight: 600;
  color: #2c3e50;
  display: flex;
  align-items: center;
  gap: 8px;
}
.card-title::before {
  content: '';
  display: inline-block;
  width: 3px;
  height: 14px;
  background: linear-gradient(180deg, #0C447C 0%, #1D9E75 100%);
  border-radius: 2px;
  margin-right: 4px;
}
.id-text { font-family: 'SF Mono', Consolas, monospace; color: #0C447C; font-weight: 600; }
.date-text { font-family: 'SF Mono', Consolas, monospace; font-size: 12px; color: #606266; }
.muted { color: #c0c4cc; }
.defect-text { color: #A32D2D; font-weight: 500; }
</style>
