<template>
  <el-dialog v-model="visible" title="横向比价" width="1100px" :close-on-click-modal="false">
    <div v-if="!bids || bids.length === 0" class="empty">暂无投标</div>
    <el-table v-else :data="bids" border stripe>
      <el-table-column label="供应商" min-width="140" fixed>
        <template #default="{ row }">
          <strong>{{ row.supplier?.name }}</strong>
        </template>
      </el-table-column>
      <el-table-column label="投标编号" prop="code" width="160" />
      <el-table-column label="总金额" width="140" align="right">
        <template #default="{ row }">
          <span :class="{ 'lowest': row.id === lowestId }">
            ¥ {{ Number(row.total_amount || 0).toLocaleString() }}
            <el-tag v-if="row.id === lowestId" type="success" size="small" effect="dark">最低</el-tag>
          </span>
        </template>
      </el-table-column>
      <el-table-column label="交货期" width="100" align="center">
        <template #default="{ row }">{{ row.lead_time_days ?? '-' }} 天</template>
      </el-table-column>
      <el-table-column label="技术方案" min-width="240" show-overflow-tooltip>
        <template #default="{ row }">{{ row.technical_proposal || '-' }}</template>
      </el-table-column>
      <el-table-column label="备注" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ row.remark || '-' }}</template>
      </el-table-column>
      <el-table-column label="综合得分" width="100" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.total_score != null" :type="row.status === 'awarded' ? 'success' : 'primary'" effect="dark">
            {{ row.total_score }}
          </el-tag>
          <span v-else class="muted">未评</span>
        </template>
      </el-table-column>
    </el-table>

    <h4 v-if="requiredItems && requiredItems.length" class="sub-title">逐项比价</h4>
    <el-table v-if="requiredItems && requiredItems.length" :data="requiredItems" border size="small" empty-text="无必购项">
      <el-table-column label="物料" min-width="140">
        <template #default="{ row }">{{ row.name }} <span class="muted">({{ row.qty }}{{ row.unit || '件' }})</span></template>
      </el-table-column>
      <el-table-column v-for="b in bids" :key="b.id" :label="b.supplier?.name" min-width="180" align="right">
        <template #default="{ row }">
          <div v-for="(it, idx) in b.items" :key="idx" class="bid-item-line">
            <span v-if="it.name === row.name">
              ¥ {{ Number(it.unit_price).toFixed(2) }} × {{ it.quantity }} = <strong>¥ {{ Number(it.total_price).toLocaleString() }}</strong>
            </span>
          </div>
          <span v-if="!b.items?.some((it: any) => it.name === row.name)" class="muted">未报价</span>
        </template>
      </el-table-column>
    </el-table>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { TenderBid } from '@/api/tender'

const props = defineProps<{ visible: boolean; bids: TenderBid[]; requiredItems?: any[] }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void }>()

const visible = computed({ get: () => props.visible, set: (v) => emit('update:visible', v) })

const lowestId = computed(() => {
  if (!props.bids?.length) return null
  const min = Math.min(...props.bids.map((b) => Number(b.total_amount || 0)))
  return props.bids.find((b) => Number(b.total_amount) === min)?.id ?? null
})
</script>

<style scoped lang="scss">
.empty { padding: 30px; text-align: center; color: #999; }
.muted { color: #999; }
.lowest { color: #67c23a; font-weight: 600; }
.sub-title { margin: 16px 0 8px; font-size: 14px; font-weight: 600; }
.bid-item-line { font-size: 12px; line-height: 1.6; }
</style>
