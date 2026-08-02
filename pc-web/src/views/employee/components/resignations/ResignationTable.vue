<template>
  <el-table
    :data="list"
    stripe
    v-loading="loading"
    style="width: 100%"
    :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
  >
    <el-table-column type="index" label="#" width="55" />
    <el-table-column label="员工" min-width="120">
      <template #default="{ row }">
        <span>{{ row.user?.name || '—' }}</span>
        <span v-if="row.user?.username" style="color:#909399; margin-left:6px;">
          ({{ row.user.username }})
        </span>
      </template>
    </el-table-column>
    <el-table-column label="离职类型" min-width="100">
      <template #default="{ row }">
        <el-tag :type="resignTypeTag(row.resign_type)" size="small">
          {{ resignTypeLabel(row.resign_type) }}
        </el-tag>
      </template>
    </el-table-column>
    <el-table-column label="申请日期" min-width="110" prop="notice_date" />
    <el-table-column label="最后工作日" min-width="110" prop="last_work_day" />
    <el-table-column label="状态" min-width="100">
      <template #default="{ row }">
        <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
      </template>
    </el-table-column>
    <el-table-column label="工资合计" min-width="100" align="right">
      <template #default="{ row }">
        <span v-if="row.total_amount != null" class="num">¥{{ Number(row.total_amount).toLocaleString() }}</span>
        <span v-else>—</span>
      </template>
    </el-table-column>
    <el-table-column label="资产归还" min-width="90">
      <template #default="{ row }">
        <el-tag v-if="row.all_assets_returned" type="success" size="small">已归还</el-tag>
        <el-tag v-else-if="row.all_assets_returned === false" type="warning" size="small">未归还</el-tag>
        <span v-else>—</span>
      </template>
    </el-table-column>
    <el-table-column label="操作" width="240" fixed="right">
      <template #default="{ row }">
        <el-button type="primary" link size="small" @click="emit('detail', row)">查看</el-button>
        <el-button v-if="row.status === 'draft'" type="success" link size="small" @click="emit('submit', row)">提交</el-button>
        <el-button v-if="row.status === 'pending'" type="info" link size="small" @click="emit('cancel', row)">撤回</el-button>
        <el-button v-if="row.status === 'approved'" type="primary" link size="small" @click="emit('complete', row)">办结</el-button>
        <el-tag v-if="row.status === 'pending'" size="small" type="info" effect="plain" class="ml-8">去审批中心</el-tag>
      </template>
    </el-table-column>
  </el-table>
  <div class="pagination-wrapper">
    <el-pagination
      :current-page="page"
      :page-size="pageSize"
      :total="total"
      :page-sizes="[10, 20, 50, 100]"
      layout="total, sizes, prev, pager, next, jumper"
      @current-change="(p: number) => emit('pageChange', p)"
      @size-change="(s: number) => emit('sizeChange', s)"
    />
  </div>
</template>

<script setup lang="ts">
import type { Resignation } from './types'
import { statusLabel, statusTag, resignTypeLabel, resignTypeTag } from './types'

defineProps<{
  list: Resignation[]
  loading: boolean
  page: number
  pageSize: number
  total: number
}>()
const emit = defineEmits<{
  (e: 'detail', row: Resignation): void
  (e: 'submit', row: Resignation): void
  (e: 'approve', row: Resignation): void
  (e: 'complete', row: Resignation): void
  (e: 'cancel', row: Resignation): void
  (e: 'pageChange', p: number): void
  (e: 'sizeChange', s: number): void
}>()
</script>

<style lang="scss" scoped>
.num { font-family: 'DIN Alternate', monospace; font-weight: 500; }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
