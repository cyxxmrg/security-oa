<template>
  <div>
    <el-table :data="list" stripe border v-loading="loading" :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }">
      <el-table-column label="计划编号" width="180" fixed>
        <template #default="{ row }">
          <span class="link-text" @click="emit('view', row)">{{ row.code || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column label="标题" min-width="220" show-overflow-tooltip>
        <template #default="{ row }">
          {{ row.title || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="金额" width="130" align="right">
        <template #default="{ row }">
          <span class="money-text">¥ {{ formatMoney(row.total_amount) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="120" align="center">
        <template #default="{ row }">
          <el-tag :type="statusTagType(row.status)" effect="plain" size="small">{{ statusLabel(row.status) }}</el-tag>
          <el-tag
            v-if="row.status === 'approved'"
            type="success"
            effect="dark"
            size="small"
            style="margin-left:4px"
            title="已审批通过"
          >
            <el-icon style="vertical-align: -2px;"><Check /></el-icon> 已审批
          </el-tag>
          <el-tag
            v-else-if="row.status === 'rejected'"
            type="danger"
            effect="dark"
            size="small"
            style="margin-left:4px"
            title="已驳回"
          >
            <el-icon style="vertical-align: -2px;"><Close /></el-icon> 已驳回
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="优先级" width="90" align="center">
        <template #default="{ row }">
          <el-tag :type="priorityTagType(row.priority)" effect="light" size="small">{{ priorityLabel(row.priority) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="计划日期" width="110" align="center">
        <template #default="{ row }">
          {{ row.plan_date ? String(row.plan_date).slice(0, 10) : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="审批人" width="100" align="center">
        <template #default="{ row }">
          <span v-if="row.approver_id">#{{ row.approver_id }}</span>
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="240" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="emit('view', row)">查看</el-button>
          <el-tag v-if="row.status === 'submitted'" size="small" type="info" effect="plain">去审批中心</el-tag>
          <el-button link type="warning" :disabled="row.status === 'approved' || row.status === 'fulfilled'" @click="emit('edit', row)">编辑</el-button>
          <el-button
            v-if="row.status === 'approved'"
            link
            type="primary"
            @click="emit('convertToOrder', row)"
          >转采购订单</el-button>
          <el-button link type="danger" :disabled="row.status === 'approved' || row.status === 'fulfilled'" @click="emit('delete', row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <div class="pagination-wrapper">
      <el-pagination
        :current-page="page" :page-size="pageSize"
        :page-sizes="[5, 10, 20]" :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @current-change="(p: number) => emit('pageChange', p)"
        @size-change="(s: number) => emit('sizeChange', s)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Check, Close } from '@element-plus/icons-vue'

const priorityOptions = [
  { value: 'low', label: '低', type: 'info' },
  { value: 'medium', label: '中', type: '' },
  { value: 'high', label: '高', type: 'warning' },
  { value: 'urgent', label: '紧急', type: 'danger' },
] as const

const statusOptions = [
  { value: 'draft', label: '草稿', type: 'info' },
  { value: 'submitted', label: '已提交', type: 'warning' },
  { value: 'approved', label: '已审批', type: 'success' },
  { value: 'rejected', label: '已驳回', type: 'danger' },
  { value: 'cancelled', label: '已取消', type: 'info' },
  { value: 'fulfilled', label: '已完成', type: 'success' },
] as const

const priorityLabel = (v: string) => priorityOptions.find(o => o.value === v)?.label || v
const statusLabel = (s: string) => statusOptions.find(o => o.value === s)?.label || s || '-'
const statusTagType = (s: string): string => statusOptions.find(o => o.value === s)?.type || 'info'
const priorityTagType = (p: string): string => priorityOptions.find(o => o.value === p)?.type || ''
const formatMoney = (n: number) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })

import type { PurchasePlan } from '../../types'

defineProps<{
  list: PurchasePlan[]
  loading: boolean
  page: number
  pageSize: number
  total: number
}>()

const emit = defineEmits<{
  (e: 'view', row: PurchasePlan): void
  (e: 'edit', row: PurchasePlan): void
  (e: 'delete', row: PurchasePlan): void
  (e: 'approve', row: PurchasePlan): void
  (e: 'convertToOrder', row: PurchasePlan): void
  (e: 'pageChange', p: number): void
  (e: 'sizeChange', s: number): void
}>()
</script>

<style lang="scss" scoped>
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; &:hover { text-decoration: underline; } }
.money-text { color: #1D9E75; font-weight: 600; }
</style>
