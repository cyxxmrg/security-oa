<template>
  <el-dialog v-model="visible" title="定标" width="640px" :close-on-click-modal="false" @close="onClose">
    <el-alert type="warning" :closable="false" show-icon style="margin-bottom: 12px">
      <p>定标后, 系统将自动:</p>
      <ul style="margin: 4px 0 0 18px; padding: 0;">
        <li>将该投标状态置为「中标」, 其他投标「未中标」</li>
        <li>生成对应采购单 (PO) — 供应商: <strong>{{ chosenBid?.supplier?.name }}</strong></li>
        <li>生成对应应付账款 (30天账期)</li>
      </ul>
    </el-alert>

    <el-form :model="form" label-width="100px">
      <el-form-item label="选中投标" required>
        <el-select v-model="form.bid_id" placeholder="选择中标投标" style="width:100%">
          <el-option v-for="b in eligibleBids" :key="b.id" :value="b.id" :label="`[${b.code}] ${b.supplier?.name} - ¥${Number(b.total_amount || 0).toLocaleString()}`" />
        </el-select>
      </el-form-item>
      <el-form-item v-if="form.bid_id" label="投标详情">
        <div v-if="chosenBid" class="bid-info">
          <div><strong>{{ chosenBid.supplier?.name }}</strong></div>
          <div>金额: ¥ {{ Number(chosenBid.total_amount).toLocaleString() }}</div>
          <div>交货: {{ chosenBid.lead_time_days ?? '-' }} 天</div>
          <div v-if="chosenBid.total_score != null">综合分: <el-tag effect="dark" type="primary">{{ chosenBid.total_score }}</el-tag></div>
        </div>
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="success" :loading="saving" :disabled="!form.bid_id" @click="onConfirm">确认定标</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { tender } from '@/api/tender'
import type { TenderBid } from '@/api/tender'
import { unwrapItem } from '@/utils/response'

const props = defineProps<{ visible: boolean; tenderId: number; bids: TenderBid[]; defaultBidId?: number }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void; (e: 'awarded'): void }>()

const visible = computed({ get: () => props.visible, set: (v) => emit('update:visible', v) })
const form = ref<{ bid_id?: number }>({})
const saving = ref(false)

const eligibleBids = computed(() => (props.bids || []).filter((b) => !['rejected', 'withdrawn'].includes(b.status)))
const chosenBid = computed(() => props.bids.find((b) => b.id === form.value.bid_id))

watch(() => props.visible, (v) => {
  if (v) form.value.bid_id = props.defaultBidId
})
watch(() => props.defaultBidId, (v) => { if (props.visible) form.value.bid_id = v })

const onConfirm = async () => {
  if (!form.value.bid_id) return
  saving.value = true
  try {
    // V0.6.3: res = {code, data: <result {auto: {po, payable}}>}
    const res = await tender.award(props.tenderId, form.value.bid_id)
    const result = unwrapItem(res) || {}
    const po = result?.auto?.po
    const pay = result?.auto?.payable
    ElMessage.success(`定标成功${po ? `, 已生成采购单 ${po.code}` : ''}${pay ? ` 与应付款 ${pay.ref_no}` : ''}`)
    emit('awarded')
  } finally { saving.value = false }
}

const onClose = () => { form.value = {} }
</script>

<style scoped lang="scss">
.bid-info { line-height: 1.8; color: #555; }
</style>
