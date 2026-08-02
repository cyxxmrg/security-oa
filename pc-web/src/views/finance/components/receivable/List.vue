<template>
  <div v-if="viewMode === 'card'" v-loading="loading" class="card-grid">
    <div v-for="row in list" :key="row.id" class="pcard" :class="`pcard--${row.status}`">
      <div class="pcard__head">
        <div class="pcard__title">
          <div class="pcard__customer">{{ row.customer?.name || '-' }}</div>
          <div class="pcard__project">{{ row.project?.name || '未关联项目' }}</div>
        </div>
        <el-tag :type="statusType(row.status)" effect="dark" round>{{ statusLabel(row.status) }}</el-tag>
      </div>
      <div class="pcard__amount">
        <div class="pcard__amount-label">应收金额</div>
        <div class="pcard__amount-value">¥{{ formatMoney(row.amount) }}</div>
      </div>
      <div class="pcard__progress">
        <div class="pcard__progress-row">
          <span>已收 ¥{{ formatMoney(row.received_amount) }}</span>
          <span>未收 <span :class="Number(row.remaining_amount) > 0 ? 'text-danger' : 'text-success'">¥{{ formatMoney(row.remaining_amount) }}</span></span>
        </div>
        <el-progress :percentage="computeRate(row)" :color="progressColor(row)" :stroke-width="6" :show-text="false" />
      </div>
      <div class="pcard__meta">
        <div class="pcard__meta-item">
          <el-icon><Calendar /></el-icon>
          <span>到期日：{{ row.due_date || '-' }}</span>
          <el-tag v-if="isOverdue(row)" type="danger" size="small" effect="plain">逾期 {{ overdueDays(row) }} 天</el-tag>
        </div>
        <div v-if="row.payment_term" class="pcard__meta-item">
          <el-icon><Document /></el-icon>
          <span>收款条件：{{ row.payment_term }}</span>
        </div>
      </div>
      <div class="pcard__actions">
        <el-button link type="primary" size="small" @click="emit('pay', row)" :disabled="Number(row.remaining_amount) <= 0"><el-icon><CreditCard /></el-icon>登记收款</el-button>
        <el-button link type="primary" size="small" @click="emit('edit', row)">编辑</el-button>
        <el-button link type="info" size="small" @click="emit('detail', row)">详情</el-button>
        <el-button link type="danger" size="small" @click="emit('delete', row)">删除</el-button>
      </div>
    </div>
    <div v-if="!loading && list.length === 0" class="empty-wrap">
      <el-empty description="暂无应收款" />
    </div>
  </div>

  <div v-else class="content-card">
    <el-table :data="list" stripe v-loading="loading" :row-class-name="rowClass">
      <el-table-column label="供应商/项目" min-width="220">
        <template #default="{ row }">
          <div class="cell-stack">
            <span class="cell-primary">{{ row.customer?.name || '-' }}</span>
            <span class="cell-secondary">{{ row.project?.name || '-' }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="应收金额" width="140" align="right">
        <template #default="{ row }"><span class="amount-strong">¥{{ formatMoney(row.amount) }}</span></template>
      </el-table-column>
      <el-table-column label="已收 / 未收" width="200">
        <template #default="{ row }">
          <div class="cell-stack">
            <span class="amount-success">已收 ¥{{ formatMoney(row.received_amount) }}</span>
            <span :class="Number(row.remaining_amount) > 0 ? 'text-danger' : 'text-success'">未收 ¥{{ formatMoney(row.remaining_amount) }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="收款进度" width="180">
        <template #default="{ row }">
          <el-progress :percentage="computeRate(row)" :color="progressColor(row)" :stroke-width="8" />
        </template>
      </el-table-column>
      <el-table-column prop="due_date" label="到期日" width="140">
        <template #default="{ row }">
          <div class="cell-stack">
            <span>{{ row.due_date || '-' }}</span>
            <el-tag v-if="isOverdue(row)" type="danger" size="small" effect="plain">逾期 {{ overdueDays(row) }} 天</el-tag>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="payment_term" label="收款条件" width="120">
        <template #default="{ row }">{{ row.payment_term || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="statusType(row.status)">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="240" align="center" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" @click="emit('pay', row)" :disabled="Number(row.remaining_amount) <= 0">登记收款</el-button>
          <el-button link type="primary" size="small" @click="emit('edit', row)">编辑</el-button>
          <el-button link type="danger" size="small" @click="emit('delete', row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { Calendar, Document, CreditCard } from '@element-plus/icons-vue'
import type { Receivable } from './types'
import { formatMoney, statusLabel, statusType, computeRate, progressColor, isOverdue, overdueDays } from './types'

// v0.3.25 抽自 finance/Receivable.vue:69-164
defineProps<{
  list: Receivable[]
  loading: boolean
  viewMode: 'card' | 'table'
}>()

const emit = defineEmits<{
  (e: 'pay', row: Receivable): void
  (e: 'edit', row: Receivable): void
  (e: 'detail', row: Receivable): void
  (e: 'delete', row: Receivable): void
}>()

function rowClass({ row }: { row: Receivable }): string {
  return isOverdue(row) ? 'row-overdue' : ''
}
</script>

<style lang="scss" scoped>
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 14px;
}
.content-card { background: #fff; padding: 16px 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.empty-wrap { grid-column: 1 / -1; padding: 40px 0; }
.pcard {
  background: #fff;
  border-radius: 10px;
  padding: 16px 18px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  border-left: 3px solid #0C447C;
  &--pending    { border-left-color: #BA7517; }
  &--partial    { border-left-color: #0C447C; }
  &--fully_paid { border-left-color: #1D9E75; }
  &__head { display: flex; justify-content: space-between; margin-bottom: 10px; }
  &__customer { font-size: 15px; font-weight: 600; color: #303133; }
  &__project { font-size: 12px; color: #909399; margin-top: 2px; }
  &__amount { margin: 8px 0; }
  &__amount-label { font-size: 11px; color: #909399; }
  &__amount-value { font-size: 24px; font-weight: 700; color: #0C447C; }
  &__progress-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
  &__meta { margin-top: 8px; }
  &__meta-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #606266; margin-bottom: 4px; }
  &__actions { display: flex; justify-content: flex-end; gap: 4px; margin-top: 8px; border-top: 1px solid #f0f2f5; padding-top: 8px; }
}
.cell-stack { display: flex; flex-direction: column; }
.cell-primary { font-weight: 500; color: #303133; }
.cell-secondary { font-size: 12px; color: #909399; }
.amount-strong { font-weight: 700; color: #0C447C; }
.amount-success { color: #1D9E75; }
.text-danger { color: #A32D2D; }
.text-success { color: #1D9E75; }
:deep(.row-overdue) { background: rgba(163, 45, 45, 0.05) !important; }
</style>
