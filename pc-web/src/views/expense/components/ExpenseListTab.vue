<template>
  <div>
    <div class="filter-bar">
      <el-input v-model="searchForm.keyword" placeholder="搜索报销单号/事由/申请人" clearable style="width: 240px" @keyup.enter="$emit('search', 1)" />
      <el-select v-model="searchForm.status" placeholder="审批状态" clearable style="width: 140px">
        <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
      </el-select>
      <el-select v-model="searchForm.category" placeholder="费用类别" clearable style="width: 140px">
        <el-option v-for="o in categoryOptions" :key="o.value" :label="o.label" :value="o.value" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="$emit('search', 1)">搜索</el-button>
      <el-button @click="$emit('reset')">重置</el-button>
      <el-button type="primary" plain :icon="Plus" @click="$emit('apply')">申请报销</el-button>
    </div>

    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column prop="claim_no" label="报销单号" width="160" />
        <el-table-column label="申请人" width="110">
          <template #default="{ row }">
            {{ row.user?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="费用类别" width="110" align="center">
          <template #default="{ row }">
            <el-tag size="small">{{ row.category_label || expenseCategoryLabel(row.category) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="金额(元)" width="120" align="right">
          <template #default="{ row }">
            <span style="font-weight: 600; color: #0C447C">{{ Number(row.total_amount || 0).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="关联项目" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.project">{{ row.project.name }}</span>
            <span v-else class="muted">无</span>
          </template>
        </el-table-column>
        <el-table-column label="审批状态" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="expenseStatusType(row.status)" size="small">{{ row.status_label || commonStatusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="提交日期" width="170">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="220" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="$emit('view', row)">查看</el-button>
            <el-button v-if="canCancel(row)" link type="warning" size="small" @click="$emit('cancel', row)">撤销</el-button>
            <el-button v-if="canDelete(row)" link type="danger" size="small" @click="$emit('delete', row)">删除</el-button>
            <el-button v-if="canPay(row)" link type="success" size="small" @click="$emit('pay', row)">付款</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          background
          layout="total, sizes, prev, pager, next, jumper"
          :total="pagination.total"
          :current-page="pagination.page"
          :page-size="pagination.per_page"
          :page-sizes="[10, 20, 50]"
          @current-change="(p: number) => $emit('search', p)"
          @size-change="(s: number) => $emit('size-change', s)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Search, Plus } from '@element-plus/icons-vue'

defineProps<{
  searchForm: { keyword: string; status: string; category: string }
  statusOptions: { value: string; label: string }[]
  categoryOptions: { value: string; label: string }[]
  loading: boolean
  list: Record<string, unknown>[]
  pagination: { page: number; per_page: number; total: number }
  canCancel: (row: Record<string, unknown>) => boolean
  canDelete: (row: Record<string, unknown>) => boolean
  canPay: (row: Record<string, unknown>) => boolean
  expenseStatusType: (s: string) => string
  formatDate: (s?: string) => string
  expenseCategoryLabel: (s: string) => string
  commonStatusLabel: (s: string) => string
}>()

defineEmits<{
  'search': [page: number]
  'reset': []
  'apply': []
  'view': [row: Record<string, unknown>]
  'cancel': [row: Record<string, unknown>]
  'delete': [row: Record<string, unknown>]
  'pay': [row: Record<string, unknown>]
  'size-change': [size: number]
}>()
</script>

<style scoped>
.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding: 16px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  flex-wrap: wrap;
}
.content-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
.muted { color: #c0c4cc; }
</style>
