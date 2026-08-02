<template>
  <el-card class="content-card" shadow="never">
    <template #header>
      <div class="card-header">
        <span class="card-header__bar" />
        <span class="card-header__title">入职档案</span>
        <span class="card-header__count">{{ total }}</span>
        <span class="card-header__suffix">条</span>
      </div>
    </template>
    <el-table
      :data="list"
      stripe
      v-loading="loading"
      style="width: 100%"
      :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
    >
      <el-table-column type="index" label="#" width="55" />
      <el-table-column label="姓名" min-width="90">
        <template #default="{ row }">{{ row.user?.name || row.name || '-' }}</template>
      </el-table-column>
      <el-table-column label="账号" min-width="100">
        <template #default="{ row }">{{ row.user?.username || row.username || '-' }}</template>
      </el-table-column>
      <el-table-column label="部门" min-width="120">
        <template #default="{ row }">{{ row.department?.name || '-' }}</template>
      </el-table-column>
      <el-table-column label="岗位" min-width="120">
        <template #default="{ row }">{{ row.position?.name || '-' }}</template>
      </el-table-column>
      <el-table-column label="试用期" min-width="80">
        <template #default="{ row }">
          <span v-if="row.probation_months">{{ row.probation_months }} 个月</span>
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="入职日期" min-width="110" prop="hire_date" />
      <el-table-column label="合同结束" min-width="110">
        <template #default="{ row }">
          <span v-if="row.contract_end_date || row.contract_end">{{ row.contract_end_date || row.contract_end }}</span>
          <span v-else>-</span>
          <el-tag
            v-if="isContractExpiring(row.contract_end_date || row.contract_end)"
            type="warning"
            size="small"
            style="margin-left: 6px"
          >即将到期</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" min-width="90">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="260" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" link size="small" @click="emit('view', row)">查看档案</el-button>
          <el-button type="warning" link size="small" @click="emit('renew', row)">续签合同</el-button>
          <el-popconfirm
            title="确定归档此入职档案？归档后该员工账号将停用"
            confirm-button-text="确定"
            cancel-button-text="取消"
            @confirm="emit('archive', row)"
          >
            <template #reference>
              <el-button type="danger" link size="small">归档</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
    <div class="pagination-wrapper">
      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.pageSize"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @current-change="emit('reload')"
        @size-change="emit('reload')"
      />
    </div>
  </el-card>
</template>

<script setup lang="ts">
import type { Onboarding, OnboardingPagination } from './types'
import { statusLabel, statusTag, isContractExpiring } from './types'

defineProps<{
  list: Onboarding[]
  total: number
  loading: boolean
  pagination: OnboardingPagination
}>()

const emit = defineEmits<{
  (e: 'view', row: Onboarding): void
  (e: 'renew', row: Onboarding): void
  (e: 'archive', row: Onboarding): void
  (e: 'reload'): void
}>()
</script>

<style lang="scss" scoped>
.content-card {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  :deep(.el-card__header) {
    padding: 0 20px;
    height: 48px;
  }
}
.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 48px;
  &__bar {
    width: 3px;
    height: 14px;
    border-radius: 2px;
    background: linear-gradient(180deg, #0C447C 0%, #1D9E75 100%);
  }
  &__title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
  }
  &__count {
    font-family: 'JetBrains Mono', 'Consolas', monospace;
    font-size: 16px;
    font-weight: 700;
    color: #0C447C;
    font-variant-numeric: tabular-nums;
  }
  &__suffix {
    font-size: 13px;
    color: #909399;
  }
}
.pagination-wrapper {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
}
</style>
