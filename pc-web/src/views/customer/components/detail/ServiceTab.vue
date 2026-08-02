<template>
  <div class="tab-content">
    <el-table :data="serviceOrders" border empty-text="暂无售后单">
      <el-table-column prop="code" label="工单号" width="160" />
      <el-table-column prop="type" label="服务类型" width="100" align="center" />
      <el-table-column prop="title" label="问题描述" min-width="200" />
      <el-table-column prop="priority" label="优先级" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="priorityType(row.priority)" size="small">{{ row.priority }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="engineer" label="处理工程师" width="100" />
      <el-table-column prop="status" label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="serviceStatusType(row.status)" size="small" effect="light">
            {{ row.status }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="120" align="center">
        <template #default="{ row }">
          <el-button type="primary" link size="small" @click="emit('view', row)">查看</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import type { ServiceOrder } from './types'
import { priorityType, serviceStatusType } from './types'

// v0.3.20 抽自 customer/Detail.vue:217-244
defineProps<{
  serviceOrders: ServiceOrder[]
}>()

const emit = defineEmits<{
  (e: 'view', order: ServiceOrder): void
}>()
</script>
