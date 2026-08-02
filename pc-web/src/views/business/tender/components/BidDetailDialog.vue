<template>
  <el-dialog v-model="visible" :title="`投标详情 - ${bid?.code || ''}`" width="800px">
    <el-descriptions v-if="bid" :column="2" border>
      <el-descriptions-item label="投标编号">{{ bid.code }}</el-descriptions-item>
      <el-descriptions-item label="供应商">{{ bid.supplier?.name }}</el-descriptions-item>
      <el-descriptions-item label="总金额">¥ {{ Number(bid.total_amount || 0).toLocaleString() }}</el-descriptions-item>
      <el-descriptions-item label="交货期">{{ bid.lead_time_days ?? '-' }} 天</el-descriptions-item>
      <el-descriptions-item label="状态">
        <el-tag size="small" :type="bid.status === 'awarded' ? 'success' : bid.status === 'rejected' ? 'danger' : 'primary'">
          {{ bid.status_label || bid.status }}
        </el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="提交时间">{{ bid.submitted_at?.replace('T', ' ').slice(0, 16) || '-' }}</el-descriptions-item>
      <el-descriptions-item label="技术方案" :span="2">{{ bid.technical_proposal || '-' }}</el-descriptions-item>
      <el-descriptions-item label="备注" :span="2">{{ bid.remark || '-' }}</el-descriptions-item>
      <el-descriptions-item v-if="bid.scores" label="评分明细" :span="2">
        技术 {{ bid.scores.technical }} / 价格 {{ bid.scores.price }} / 商务 {{ bid.scores.business }} = <strong>{{ bid.total_score }}</strong>
      </el-descriptions-item>
    </el-descriptions>

    <h4 class="sub-title" v-if="bid?.items?.length">行项目</h4>
    <el-table v-if="bid?.items?.length" :data="bid.items" border size="small">
      <el-table-column prop="name" label="物料/服务" min-width="200" />
      <el-table-column prop="spec" label="规格" width="140" />
      <el-table-column prop="unit" label="单位" width="80" />
      <el-table-column prop="quantity" label="数量" width="100" align="right" />
      <el-table-column prop="unit_price" label="单价" width="120" align="right">
        <template #default="{ row }">¥ {{ Number(row.unit_price).toFixed(2) }}</template>
      </el-table-column>
      <el-table-column prop="total_price" label="小计" width="140" align="right">
        <template #default="{ row }"><strong>¥ {{ Number(row.total_price).toLocaleString() }}</strong></template>
      </el-table-column>
    </el-table>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { TenderBid } from '@/api/tender'

const props = defineProps<{ visible: boolean; bid: TenderBid | null }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void }>()

const visible = computed({ get: () => props.visible, set: (v) => emit('update:visible', v) })
</script>

<style scoped lang="scss">
.sub-title { margin: 16px 0 8px; font-size: 14px; font-weight: 600; }
</style>
