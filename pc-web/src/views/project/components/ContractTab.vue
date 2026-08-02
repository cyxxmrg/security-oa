<template>
  <div class="tab-content">
    <h3 class="section-title">项目合同 ({{ contracts.length }})</h3>
    <el-table v-if="contracts.length > 0" :data="contracts" border size="default">
      <el-table-column prop="contract_no" label="合同编号" min-width="160" fixed />
      <el-table-column label="合同类型" width="90" align="center">
        <template #default="{ row }">
          <el-tag :type="row.type === 'sales' ? 'primary' : 'warning'" size="small" effect="plain">
            {{ typeLabel(row.type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="signed_at" label="签订日期" width="120">
        <template #default="{ row }">{{ formatDate(row.signed_at) }}</template>
      </el-table-column>
      <el-table-column prop="contract_amount" label="合同金额" width="140" align="right">
        <template #default="{ row }">¥ {{ formatMoney(row.contract_amount) }}</template>
      </el-table-column>
      <el-table-column prop="payment_method" label="付款方式" width="120">
        <template #default="{ row }">{{ paymentMethodLabel(row.payment_method) }}</template>
      </el-table-column>
      <el-table-column prop="contract_start" label="开始" width="110">
        <template #default="{ row }">{{ formatDate(row.contract_start) }}</template>
      </el-table-column>
      <el-table-column prop="contract_end" label="结束" width="110">
        <template #default="{ row }">{{ formatDate(row.contract_end) }}</template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="付款节点" min-width="200">
        <template #default="{ row }">
          <div v-if="row.payment_nodes && row.payment_nodes.length > 0">
            <el-tag v-for="n in row.payment_nodes.slice(0, 3)" :key="n.id" size="small" effect="plain" style="margin-right: 4px">
              {{ n.name }} {{ n.percent }}%
            </el-tag>
            <span v-if="row.payment_nodes.length > 3" style="color: #909399">+{{ row.payment_nodes.length - 3 }}</span>
          </div>
          <span v-else style="color: #909399">-</span>
        </template>
      </el-table-column>
      <el-table-column label="附件" width="80" align="center">
        <template #default="{ row }">
          <el-link v-if="row.attachment" :href="row.attachment" target="_blank" type="primary">查看</el-link>
          <span v-else style="color: #909399">-</span>
        </template>
      </el-table-column>
    </el-table>
    <el-empty v-else description="该项目暂无合同" :image-size="80" />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { get } from '@/utils/request'
import { ElMessage } from 'element-plus'

interface PaymentNode { id: number; name: string; percent: number; status?: string }
interface Contract {
  id: number; contract_no: string; signed_at?: string; contract_amount?: number;
  payment_method?: string; contract_start?: string; contract_end?: string;
  status: string; type?: string; attachment?: string; notes?: string; payment_nodes?: PaymentNode[];
}

const props = defineProps<{ projectId: number | string }>()

const contracts = ref<Contract[]>([])
const loading = ref(false)

const formatDate = (s?: string | null) => s ? s.slice(0, 10) : '-'
const formatMoney = (v?: number | string) => Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const paymentMethodLabel = (m?: string) => ({
  lump_sum: '一次性', installment: '分期', milestone: '里程碑',
}[m || ''] || m || '-')

const typeLabel = (t?: string) => ({
  sales: '销售合同', purchase: '采购合同',
}[t || ''] || t || '-')

const statusLabel = (s: string) => ({
  draft: '草稿', signed: '已签订', active: '执行中',
  in_progress: '执行中', completed: '已完成', terminated: '已终止',
  cancelled: '已取消', expired: '已到期',
}[s] || s || '-')

const statusTagType = (s: string): 'success' | 'warning' | 'info' | 'danger' | 'primary' => {
  if (s === 'completed' || s === 'signed') return 'success'
  if (s === 'active' || s === 'in_progress') return 'warning'
  if (s === 'terminated' || s === 'cancelled' || s === 'expired') return 'danger'
  if (s === 'draft') return 'info'
  return 'primary'
}

const load = async () => {
  if (!props.projectId || Number(props.projectId) <= 0) return
  loading.value = true
  try {
    const r = await get(`/projects/${props.projectId}/contracts`)
    const list = Array.isArray(r) ? r : Array.isArray((r as { data?: Contract[] })?.data) ? (r as { data: Contract[] }).data : []
    contracts.value = list
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || '加载合同失败'
    ElMessage.error(msg)
    contracts.value = []
  } finally {
    loading.value = false
  }
}

watch(() => props.projectId, load, { immediate: true })
</script>