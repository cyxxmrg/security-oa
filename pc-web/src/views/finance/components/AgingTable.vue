<template>
  <el-card shadow="never" class="aging-card">
    <template #header>
      <span>账龄分析（{{ type === 'payable' ? '应付' : '应收' }}）</span>
    </template>
    <el-table :data="buckets" border>
      <el-table-column prop="key" label="账龄段" width="120" />
      <el-table-column prop="count" label="单数" width="100" align="right" />
      <el-table-column prop="amount" label="金额" align="right">
        <template #default="{ row }">¥{{ Number(row.amount).toLocaleString() }}</template>
      </el-table-column>
      <el-table-column label="占比">
        <template #default="{ row }">
          <el-progress :percentage="ratio(row.amount)" :stroke-width="14" />
        </template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { ledger } from '@/api/ledger'
import type { AgingBucket } from '@/api/ledger'
import { unwrapList } from '@/utils/response'

const props = defineProps<{ type: 'payable' | 'receivable' }>()

const buckets = ref<AgingBucket[]>([])

const total = () => buckets.value.reduce((s, b) => s + Number(b.amount || 0), 0)
const ratio = (amt: number) => {
  const t = total()
  return t > 0 ? Math.round((Number(amt) / t) * 100) : 0
}

const load = async () => {
  const res = await ledger.aging(props.type)
  // V0.6.3: res = {code, data: <buckets>}
  buckets.value = unwrapList(res)
}

watch(() => props.type, load)
onMounted(load)
</script>

<style scoped>
.aging-card { margin-top: 16px; }
</style>
