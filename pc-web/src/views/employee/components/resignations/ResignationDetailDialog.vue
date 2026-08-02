<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="离职详情"
    width="900px"
    destroy-on-close
  >
    <div v-if="row" class="detail">
      <el-descriptions title="基础信息" :column="3" border>
        <el-descriptions-item label="员工">{{ row.user?.name || '—' }}</el-descriptions-item>
        <el-descriptions-item label="离职类型">
          <el-tag :type="resignTypeTag(row.resign_type)" size="small">{{ resignTypeLabel(row.resign_type) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="申请日期">{{ row.notice_date || '—' }}</el-descriptions-item>
        <el-descriptions-item label="离职日期">{{ row.resign_date || '—' }}</el-descriptions-item>
        <el-descriptions-item label="最后工作日">{{ row.last_work_day || '—' }}</el-descriptions-item>
      </el-descriptions>

      <el-descriptions title="离职原因" :column="1" border style="margin-top: 16px">
        <el-descriptions-item label="原因">{{ row.reason || '—' }}</el-descriptions-item>
      </el-descriptions>

      <el-descriptions v-if="row.handover_to_user_id" title="工作交接" :column="2" border style="margin-top: 16px">
        <el-descriptions-item label="交接人 ID">{{ row.handover_to_user_id }}</el-descriptions-item>
        <el-descriptions-item label="交接说明">{{ row.handover_note || '—' }}</el-descriptions-item>
      </el-descriptions>

      <div v-if="row.assets && row.assets.length" class="block">
        <div class="block-title">资产归还</div>
        <el-table :data="row.assets" border size="small">
          <el-table-column prop="name" label="资产名称" />
          <el-table-column label="状态" width="100">
            <template #default="{ row: r }">
              <el-tag :type="r.returned ? 'success' : 'warning'" size="small">
                {{ r.returned ? '已归还' : '未归还' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="note" label="备注" />
        </el-table>
      </div>

      <el-descriptions title="工资结算" :column="3" border style="margin-top: 16px">
        <el-descriptions-item label="最终工资">¥{{ Number(row.final_salary || 0).toLocaleString() }}</el-descriptions-item>
        <el-descriptions-item label="剩余假期">{{ row.leave_balance || 0 }} 天</el-descriptions-item>
        <el-descriptions-item label="经济补偿金">¥{{ Number(row.severance_pay || 0).toLocaleString() }}</el-descriptions-item>
        <el-descriptions-item label="合计" :span="3">
          <span class="total">¥{{ Number(row.total_amount || ((row.final_salary || 0) + (row.severance_pay || 0))).toLocaleString() }}</span>
        </el-descriptions-item>
      </el-descriptions>
    </div>
  </el-dialog>
</template>

<script setup lang="ts">
import type { Resignation } from './types'
import { statusLabel, statusTag, resignTypeLabel, resignTypeTag } from './types'

defineProps<{ visible: boolean; row: Resignation | null }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void }>()
</script>

<style lang="scss" scoped>
.block { margin-top: 16px; }
.block-title { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 8px; padding-left: 8px; border-left: 3px solid #1D9E75; }
.total { font-size: 18px; font-weight: 700; color: #1D9E75; }
</style>
