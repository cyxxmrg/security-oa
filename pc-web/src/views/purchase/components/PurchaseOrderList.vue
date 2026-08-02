<template>
  <div class="content-card">
    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="$emit('search')">
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            placeholder="PO 号 / 供应商 / 标题"
            clearable
            style="width: 280px"
            @keyup.enter="$emit('search')"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 140px">
            <el-option v-for="s in orderStatusOptions" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="$emit('search')">查询</el-button>
          <el-button :icon="Refresh" @click="$emit('reset')">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-table
      :data="pagedOrders"
      stripe
      border
      v-loading="loadingOrders"
      highlight-current-row
      @row-click="(row: Record<string, unknown>) => $emit('select-order', row)"
      :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
    >
      <el-table-column label="PO 号" width="170" fixed>
        <template #default="{ row }">
          <span class="link-text">{{ row.code || row.po_no || `#${row.id}` }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="title" label="订单标题" min-width="200" show-overflow-tooltip />
      <el-table-column label="供应商" min-width="150" show-overflow-tooltip>
        <template #default="{ row }">{{ row.supplier?.name || `#${row.supplier_id}` }}</template>
      </el-table-column>
      <el-table-column label="金额" width="130" align="right">
        <template #default="{ row }">
          <span class="money-text">¥ {{ formatMoney(row.total_amount) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="orderStatusTagType(row.status)" effect="plain" size="small">{{ orderStatusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" width="160" align="center">
        <template #default="{ row }">{{ row.created_at ? String(row.created_at).slice(0, 16) : '-' }}</template>
      </el-table-column>
    </el-table>
    <div class="pagination-wrapper">
      <el-pagination
        v-model:current-page="pageModel"
        v-model:page-size="pageSizeModel"
        :page-sizes="[10, 20, 50]"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Search, Refresh } from '@element-plus/icons-vue'

const props = defineProps<{
  searchForm: { keyword: string; status: string }
  loadingOrders: boolean
  orderStatusOptions: { value: string; label: string }[]
  pagedOrders: Record<string, unknown>[]
  total: number
}>()

defineEmits<{
  (e: 'search'): void
  (e: 'reset'): void
  (e: 'select-order', row: Record<string, unknown>): void
}>()

const pageModel = defineModel<number>('page', { default: 1 })
const pageSizeModel = defineModel<number>('pageSize', { default: 10 })

const formatMoney = (n: number) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const orderStatusLabel = (s: string) => props.orderStatusOptions.find(o => o.value === s)?.label || s || '-'
const ORDER_STATUS_TYPES: Record<string, string> = { draft: 'info', pending: 'warning', approved: 'success', fulfilled: 'success', rejected: 'danger', cancelled: 'info' }
const orderStatusTagType = (s: string): string => ORDER_STATUS_TYPES[s] || ''
</script>

<style scoped>
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; }
.money-text { color: #1D9E75; font-weight: 600; }
</style>
