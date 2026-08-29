<template>
  <el-table :data="data" v-loading="loading" stripe border>
    <el-table-column type="index" label="#" width="50" />
    <el-table-column prop="code" label="编号" width="160" />
    <el-table-column prop="name" label="供应商名称" min-width="180" show-overflow-tooltip>
      <template #default="{ row }">
        <el-link type="primary" :underline="false" @click="$emit('view', toSupplier(row))">{{ row.name }}</el-link>
      </template>
    </el-table-column>
    <el-table-column prop="type" label="类型" width="90">
      <template #default="{ row }">
        <el-tag size="small" :type="typeTagType(row.type)">{{ typeLabel(row.type) }}</el-tag>
      </template>
    </el-table-column>
    <el-table-column prop="contact_person" label="联系人" width="100" />
    <el-table-column prop="phone" label="电话" width="130" />
    <el-table-column label="联系人库" width="100" align="center">
      <template #default="{ row }">
        <el-button size="small" link type="success" @click="$emit('view', toSupplier(row))">
          {{ row.contacts_count ?? 0 }} 人
        </el-button>
      </template>
    </el-table-column>
    <el-table-column prop="payment_terms" label="账期" width="80">
      <template #default="{ row }">
        <el-tag size="small" effect="plain">{{ paymentLabel(row.payment_terms) }}</el-tag>
      </template>
    </el-table-column>
    <el-table-column prop="rating" label="评级" width="80" align="center">
      <template #default="{ row }">
        <el-rate :model-value="row.rating" disabled show-score :show-text="false" />
      </template>
    </el-table-column>
    <el-table-column prop="status" label="状态" width="90">
      <template #default="{ row }">
        <el-tag size="small" :type="statusTagType(row.status)">{{ statusLabel(row.status) }}</el-tag>
      </template>
    </el-table-column>
    <el-table-column label="操作" width="240" fixed="right">
      <template #default="{ row }">
        <el-button size="small" link type="primary" @click="$emit('view', toSupplier(row))">详情</el-button>
        <el-button size="small" link type="primary" @click="$emit('edit', toSupplier(row))">编辑</el-button>
        <el-button size="small" link type="warning" @click="$emit('evaluate', toSupplier(row))">评价</el-button>
        <el-button size="small" link type="danger" @click="$emit('delete', toSupplier(row))">删除</el-button>
      </template>
    </el-table-column>
  </el-table>
</template>

<script setup lang="ts">
import type { Supplier } from '@/api/supplier'
import { withDefaults } from 'vue'

const props = withDefaults(defineProps<{
  data: Supplier[]
  loading?: boolean
}>(), { loading: false })

defineEmits<{
  view: [row: Supplier]
  edit: [row: Supplier]
  evaluate: [row: Supplier]
  delete: [row: Supplier]
}>()

const typeLabel = (t?: string) => ({
  material: '材料', labor: '人工', outsource: '外包', service: '服务',
}[t ?? ''] ?? t ?? '-')

const typeTagType = (t?: string): 'success' | 'primary' | 'warning' | 'info' | 'danger' => ({
  material: 'primary', labor: 'success', outsource: 'warning', service: 'info',
}[t ?? ''] as 'success' | 'primary' | 'warning' | 'info' | 'danger' || 'info')

const statusLabel = (s?: string) => ({
  active: '正常', paused: '暂停', blacklist: '黑名单',
}[s ?? ''] ?? s ?? '-')

const statusTagType = (s?: string): 'success' | 'primary' | 'warning' | 'info' | 'danger' => ({
  active: 'success', paused: 'warning', blacklist: 'danger',
}[s ?? ''] as 'success' | 'primary' | 'warning' | 'info' | 'danger' || 'info')

const toSupplier = (row: unknown): Supplier => row as Supplier

const paymentLabel = (p?: string) => ({
  cash: '现款', '30days': '30天', '60days': '60天', '90days': '90天',
}[p ?? ''] ?? p ?? '-')
</script>
