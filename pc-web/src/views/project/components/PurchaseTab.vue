<template>
  <div class="tab-content">
    <h3 class="section-title">项目采购 ({{ totalCount }} 单)</h3>

    <!-- 汇总卡片（从 tracking 数据） -->
    <el-row :gutter="16" v-if="trackingSummary">
      <el-col :span="6">
        <el-card shadow="never" class="summary-card">
          <div class="label">采购单总数</div>
          <div class="value">{{ trackingSummary.total_orders || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="summary-card">
          <div class="label">已完成</div>
          <div class="value text-success">{{ trackingSummary.completed_orders || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="summary-card">
          <div class="label">采购总额</div>
          <div class="value text-warning">¥ {{ formatMoney(trackingSummary.total_amount) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="summary-card">
          <div class="label">履约率</div>
          <div class="value">{{ trackingSummary.fulfill_rate || 0 }}%</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 采购单列表（按类型分组） -->
    <el-collapse v-model="activeGroups" class="purchase-collapse">
      <el-collapse-item name="requirements" :title="`采购需求 (${groups.requirements.length})`">
        <el-table v-if="groups.requirements.length > 0" :data="groups.requirements" border size="small">
          <el-table-column prop="code" label="编号" min-width="160" fixed />
          <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
          <el-table-column prop="quantity" label="数量" width="100" align="right" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="180">
            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-else description="无采购需求" :image-size="60" />
      </el-collapse-item>

      <el-collapse-item name="orders" :title="`采购单 (${groups.orders.length})`">
        <el-table v-if="groups.orders.length > 0" :data="groups.orders" border size="small">
          <el-table-column prop="po_no" label="采购单号" min-width="160" fixed />
          <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
          <el-table-column label="供应商" min-width="140" show-overflow-tooltip>
            <template #default="{ row }">{{ row.supplier?.name || '-' }}</template>
          </el-table-column>
          <el-table-column prop="total_amount" label="金额" width="140" align="right">
            <template #default="{ row }">¥ {{ formatMoney(row.total_amount) }}</template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else description="无采购单" :image-size="60" />
      </el-collapse-item>

      <el-collapse-item name="contracts" :title="`采购合同 (${groups.contracts.length})`">
        <el-table v-if="groups.contracts.length > 0" :data="groups.contracts" border size="small">
          <el-table-column prop="code" label="合同号" min-width="160" fixed />
          <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
          <el-table-column label="供应商" min-width="140" show-overflow-tooltip>
            <template #default="{ row }">{{ row.supplier?.name || '-' }}</template>
          </el-table-column>
          <el-table-column prop="total_amount" label="金额" width="140" align="right">
            <template #default="{ row }">¥ {{ formatMoney(row.total_amount) }}</template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else description="无采购合同" :image-size="60" />
      </el-collapse-item>
    </el-collapse>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { get } from '@/utils/request'
import { ElMessage } from 'element-plus'

const props = defineProps<{
  projectId: number | string
  tracking?: { purchase_stats?: Record<string, unknown> }
}>()

const groups = ref<{ requirements: unknown[]; orders: unknown[]; contracts: unknown[] }>({
  requirements: [], orders: [], contracts: [],
})
const activeGroups = ref(['requirements', 'orders', 'contracts'])
const loading = ref(false)

const trackingSummary = computed(() => props.tracking?.purchase_stats)
const totalCount = computed(() =>
  groups.value.requirements.length + groups.value.orders.length + groups.value.contracts.length
)

const formatDate = (s?: string | null) => s ? s.slice(0, 10) : '-'
const formatMoney = (v?: number | string) => Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const statusLabel = (s?: string) => ({
  draft: '草稿', pending: '待审', submitted: '已提交', approved: '已审',
  in_progress: '执行中', completed: '已完成', signed: '已签', cancelled: '取消',
}[s || ''] || s || '-')

const statusTagType = (s?: string): 'success' | 'warning' | 'info' | 'danger' | 'primary' => {
  if (s === 'completed' || s === 'signed' || s === 'approved') return 'success'
  if (s === 'in_progress' || s === 'submitted') return 'warning'
  if (s === 'cancelled') return 'danger'
  if (s === 'draft') return 'info'
  return 'primary'
}

const extractList = (res: unknown): unknown[] => {
  if (Array.isArray(res)) return res
  if (res && typeof res === 'object') {
    const r = res as { data?: unknown }
    if (Array.isArray(r.data)) return r.data
  }
  return []
}

const load = async () => {
  const pid = Number(props.projectId)
  if (!Number.isFinite(pid) || pid <= 0) return
  loading.value = true
  try {
    // 三个并行请求：采购需求 / 采购单 / 采购合同，都按 project_id 过滤
    const [reqs, orders, contracts] = await Promise.all([
      get('/purchase-flow/requirements-list', { project_id: pid, per_page: 50 }).catch(() => []),
      get('/purchase-flow/orders-list', { project_id: pid, per_page: 50 }).catch(() => []),
      get('/purchase-flow/contracts-list', { project_id: pid, per_page: 50 }).catch(() => []),
    ])
    groups.value = {
      requirements: extractList(reqs),
      orders: extractList(orders),
      contracts: extractList(contracts),
    }
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || '加载采购数据失败'
    ElMessage.error(msg)
  } finally {
    loading.value = false
  }
}

watch(() => props.projectId, load, { immediate: true })
</script>

<style scoped>
.summary-card { margin-bottom: 16px; }
.summary-card .label { color: #909399; font-size: 12px; }
.summary-card .value { font-size: 24px; font-weight: 700; margin-top: 8px; }
.text-success { color: #67C23A; }
.text-warning { color: #E6A23C; }
.purchase-collapse { margin-top: 16px; }
</style>