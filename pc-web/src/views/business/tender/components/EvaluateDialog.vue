<template>
  <el-dialog v-model="visible" title="评标打分" width="900px" :close-on-click-modal="false" @close="onClose">
    <div class="eval-tip">
      <el-alert type="info" :closable="false" show-icon>
        权重配置: 技术 {{ weights.technical }} / 价格 {{ weights.price }} / 商务 {{ weights.business }} (合计 {{ totalWeight }})
      </el-alert>
    </div>
    <el-form :model="form" label-width="80px" v-loading="loading">
      <el-table :data="form.rows" border>
        <el-table-column label="供应商" min-width="160" fixed>
          <template #default="{ row }"><strong>{{ row.supplier_name }}</strong></template>
        </el-table-column>
        <el-table-column label="总金额" width="130" align="right">
          <template #default="{ row }">¥ {{ Number(row.total_amount).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column label="技术分 (0-100)" width="160">
          <template #default="{ row }">
            <el-input-number v-model="row.technical" :min="0" :max="100" :precision="1" controls-position="right" style="width:120px" />
          </template>
        </el-table-column>
        <el-table-column label="价格分 (0-100)" width="160">
          <template #default="{ row }">
            <el-input-number v-model="row.price" :min="0" :max="100" :precision="1" controls-position="right" style="width:120px" />
          </template>
        </el-table-column>
        <el-table-column label="商务分 (0-100)" width="160">
          <template #default="{ row }">
            <el-input-number v-model="row.business" :min="0" :max="100" :precision="1" controls-position="right" style="width:120px" />
          </template>
        </el-table-column>
        <el-table-column label="综合分" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.total >= 80 ? 'success' : row.total >= 60 ? 'primary' : 'warning'" effect="dark">
              {{ row.total.toFixed(2) }}
            </el-tag>
          </template>
        </el-table-column>
      </el-table>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSave">提交评分</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { tender } from '@/api/tender'
import type { TenderBid } from '@/api/tender'

const props = defineProps<{ visible: boolean; tenderId: number; bids: TenderBid[]; score_config?: { technical: number; price: number; business: number } }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void; (e: 'saved'): void }>()

const visible = computed({ get: () => props.visible, set: (v) => emit('update:visible', v) })

const weights = computed(() => props.score_config || { technical: 40, price: 40, business: 20 })
const totalWeight = computed(() => weights.value.technical + weights.value.price + weights.value.business)

interface Row { bid_id: number; supplier_name: string; total_amount: number; technical: number; price: number; business: number; total: number }

const form = reactive<{ rows: Row[] }>({ rows: [] })
const loading = ref(false)
const saving = ref(false)

const recompute = () => {
  for (const r of form.rows) {
    const wT = weights.value.technical, wP = weights.value.price, wB = weights.value.business
    r.total = (r.technical * wT + r.price * wP + r.business * wB) / Math.max(0.0001, wT + wP + wB)
  }
}

watch(() => props.bids, (bs) => {
  form.rows = (bs || []).filter((b) => b.status !== 'rejected' && b.status !== 'withdrawn').map((b) => ({
    bid_id: b.id,
    supplier_name: b.supplier?.name || `投标#${b.id}`,
    total_amount: Number(b.total_amount || 0),
    technical: b.scores?.technical ?? 70,
    price: b.scores?.price ?? 70,
    business: b.scores?.business ?? 70,
    total: 0,
  }))
  recompute()
}, { immediate: true, deep: true })

watch(() => [form.rows.map((r) => r.technical), form.rows.map((r) => r.price), form.rows.map((r) => r.business)].flat(), () => recompute(), { deep: true })

const onSave = async () => {
  if (form.rows.length === 0) return ElMessage.warning('没有可评标的投标')
  saving.value = true
  try {
    await tender.evaluate(props.tenderId, form.rows.map((r) => ({
      bid_id: r.bid_id, technical: r.technical, price: r.price, business: r.business,
    })))
    ElMessage.success('已提交评分')
    emit('saved')
    visible.value = false
  } finally { saving.value = false }
}

const onClose = () => { form.rows = [] }
</script>

<style scoped lang="scss">
.eval-tip { margin-bottom: 12px; }
</style>
