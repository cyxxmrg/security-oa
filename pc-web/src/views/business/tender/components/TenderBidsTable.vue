<template>
  <div>
    <div class="bids-actions" v-if="bids.length > 0">
      <el-button type="primary" :icon="Histogram" @click="$emit('compare')">横向比价</el-button>
    </div>
    <el-table :data="bids" v-loading="loadingBids" border stripe>
      <el-table-column type="index" label="#" width="60" />
      <el-table-column label="投标编号" prop="code" width="160" />
      <el-table-column label="供应商" min-width="160">
        <template #default="{ row }">{{ row.supplier?.name }}</template>
      </el-table-column>
      <el-table-column label="总金额" width="140" align="right">
        <template #default="{ row }">¥ {{ Number(row.total_amount || 0).toLocaleString() }}</template>
      </el-table-column>
      <el-table-column label="交货期(天)" width="100" align="center">
        <template #default="{ row }">{{ row.lead_time_days ?? '-' }}</template>
      </el-table-column>
      <el-table-column label="综合得分" width="100" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.total_score != null" :type="row.status === 'awarded' ? 'success' : 'primary'" effect="dark">
            {{ row.total_score }}
          </el-tag>
          <span v-else class="muted">未评分</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="bidStatusTag(row.status)" effect="light">{{ row.status_label || row.status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="提交时间" width="160">
        <template #default="{ row }">{{ fmt(row.submitted_at) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="$emit('view', row)">查看</el-button>
          <el-button v-if="canAward && row.status !== 'awarded'" link type="success" @click="$emit('award', row)">定标</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { Histogram } from '@element-plus/icons-vue'
import type { TenderBid } from '@/api/tender'
import { bidStatusTag, fmt } from '../utils'

defineProps<{
  bids: TenderBid[]
  loadingBids: boolean
  canAward: boolean
}>()

defineEmits<{
  compare: []
  view: [row: TenderBid]
  award: [row: TenderBid]
}>()
</script>

<style scoped>
.muted { color: #999; }
.bids-actions { margin-bottom: 12px; }
</style>
