<template>
  <div>
    <el-table
      :data="list"
      stripe border
      v-loading="loading"
      :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
    >
      <el-table-column prop="code" label="合同编号" width="160" fixed>
        <template #default="{ row }">
          <span class="link-text" @click="emit('view', row)">{{ row.code }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="title" label="合同名称" min-width="200" show-overflow-tooltip />
      <el-table-column label="关联采购计划" width="160">
        <template #default="{ row }">
          <span class="link-text" @click="emit('viewPlan', row)">{{ row.plan?.code || row.plan_id || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column label="供应商 ID" width="100" align="center">
        <template #default="{ row }">#{{ row.supplier_id }}</template>
      </el-table-column>
      <el-table-column prop="total_amount" label="合同金额" width="140" align="right">
        <template #default="{ row }">¥ {{ formatMoney(row.total_amount) }}</template>
      </el-table-column>
      <el-table-column label="签订日期" width="120" align="center">
        <template #default="{ row }">{{ sliceDate(row.signed_at) }}</template>
      </el-table-column>
      <el-table-column label="有效期至" width="120" align="center">
        <template #default="{ row }">{{ sliceDate(row.end_date) }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="statusTagType(row.status)" effect="plain" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="340" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="emit('view', row)">查看</el-button>
          <el-button link type="success" :disabled="row.status !== 'draft' && row.status !== 'pending'" @click="emit('sign', row)">签署</el-button>
          <el-button link type="warning" :disabled="['shipping', 'completed', 'cancelled', 'signed'].includes(row.status)" @click="emit('edit', row)">编辑</el-button>
          <el-button link type="success" :disabled="!['signed', 'shipping'].includes(row.status)" @click="emit('ship', row)">发货</el-button>
          <el-button link type="danger" :disabled="row.status === 'completed'" @click="emit('delete', row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <div class="pagination-wrapper">
      <el-pagination
        :current-page="page"
        :page-size="pageSize"
        :page-sizes="[5, 10, 20]"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @current-change="(p: number) => emit('pageChange', p)"
        @size-change="(s: number) => emit('sizeChange', s)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { statusLabel, statusTagType, formatMoney, sliceDate } from './types'
import type { PurchaseContract } from '../../types'

defineProps<{
  list: PurchaseContract[]
  loading: boolean
  page: number
  pageSize: number
  total: number
}>()
const emit = defineEmits<{
  (e: 'view', row: PurchaseContract): void
  (e: 'viewPlan', row: PurchaseContract): void
  (e: 'edit', row: PurchaseContract): void
  (e: 'ship', row: PurchaseContract): void
  (e: 'sign', row: PurchaseContract): void
  (e: 'delete', row: PurchaseContract): void
  (e: 'pageChange', p: number): void
  (e: 'sizeChange', s: number): void
}>()
</script>

<style lang="scss" scoped>
.link-text { color: #0C447C; cursor: pointer; }
.link-text:hover { text-decoration: underline; }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
