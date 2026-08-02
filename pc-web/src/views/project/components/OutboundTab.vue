<template>
  <div class="tab-content">
    <h3 class="section-title">项目出库 ({{ totalCount }} 单) · 总金额 ¥ {{ formatMoney(totalAmount) }}</h3>

    <!-- 汇总卡片 -->
    <el-row :gutter="16">
      <el-col :span="6">
        <el-card shadow="never" class="summary-card">
          <div class="label">领料出库</div>
          <div class="value text-warning">{{ groups.领料.length }} 单</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="summary-card">
          <div class="label">销售出库</div>
          <div class="value" style="color:#409EFF">{{ groups.销售.length }} 单</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="summary-card">
          <div class="label">报废出库</div>
          <div class="value text-danger">{{ groups.报废.length }} 单</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="summary-card">
          <div class="label">出库总金额</div>
          <div class="value" style="color:#1D9E75">¥ {{ formatMoney(totalAmount) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 出库单列表（按领料/销售/报废分组） -->
    <el-collapse v-model="activeGroups" class="purchase-collapse">
      <el-collapse-item name="领料" :title="`领料出库 (${groups.领料.length})`">
        <el-table v-if="groups.领料.length > 0" :data="groups.领料" border size="small">
          <el-table-column prop="record_no" label="单号" min-width="180" fixed />
          <el-table-column label="物料种类" width="90" align="center">
            <template #default="{ row }">{{ row.item_count || 1 }} 种</template>
          </el-table-column>
          <el-table-column label="出库总数" width="110" align="right">
            <template #default="{ row }">{{ row.total_quantity || 0 }}</template>
          </el-table-column>
          <el-table-column label="总金额" width="140" align="right">
            <template #default="{ row }">¥ {{ formatMoney(row.total_amount) }}</template>
          </el-table-column>
          <el-table-column label="仓库" width="100">
            <template #default="{ row }">{{ row.warehouse?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="操作人" width="100">
            <template #default="{ row }">{{ row.operator?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="出库时间" width="160">
            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-else description="无领料出库记录" :image-size="60" />
      </el-collapse-item>

      <el-collapse-item name="销售" :title="`销售出库 (${groups.销售.length})`">
        <el-table v-if="groups.销售.length > 0" :data="groups.销售" border size="small">
          <el-table-column prop="record_no" label="单号" min-width="180" fixed />
          <el-table-column label="物料种类" width="90" align="center">
            <template #default="{ row }">{{ row.item_count || 1 }} 种</template>
          </el-table-column>
          <el-table-column label="出库总数" width="110" align="right">
            <template #default="{ row }">{{ row.total_quantity || 0 }}</template>
          </el-table-column>
          <el-table-column label="总金额" width="140" align="right">
            <template #default="{ row }">¥ {{ formatMoney(row.total_amount) }}</template>
          </el-table-column>
          <el-table-column label="客户" min-width="150" show-overflow-tooltip>
            <template #default="{ row }">{{ row.party?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="付款方式" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="row.payment_method === 'receivable' ? 'warning' : 'success'" size="small">
                {{ row.payment_method === 'receivable' ? '应收账款' : '现金收款' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作人" width="100">
            <template #default="{ row }">{{ row.operator?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="出库时间" width="160">
            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-else description="无销售出库记录" :image-size="60" />
      </el-collapse-item>

      <el-collapse-item name="报废" :title="`报废出库 (${groups.报废.length})`">
        <el-table v-if="groups.报废.length > 0" :data="groups.报废" border size="small">
          <el-table-column prop="record_no" label="单号" min-width="180" fixed />
          <el-table-column label="物料种类" width="90" align="center">
            <template #default="{ row }">{{ row.item_count || 1 }} 种</template>
          </el-table-column>
          <el-table-column label="出库总数" width="110" align="right">
            <template #default="{ row }">{{ row.total_quantity || 0 }}</template>
          </el-table-column>
          <el-table-column label="仓库" width="100">
            <template #default="{ row }">{{ row.warehouse?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="操作人" width="100">
            <template #default="{ row }">{{ row.operator?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="出库时间" width="160">
            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
          </el-table-column>
          <el-table-column prop="remark" label="备注" min-width="180" show-overflow-tooltip />
        </el-table>
        <el-empty v-else description="无报废出库记录" :image-size="60" />
      </el-collapse-item>
    </el-collapse>

    <div v-if="!loading && totalCount === 0" style="text-align:center;padding:40px;color:#909399">
      <el-empty description="该项目暂无出库记录。在出库时选择此项目即可自动汇总。" :image-size="80" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { get } from '@/utils/request'
import { unwrapPaginate } from '@/utils/response'

const props = defineProps<{
  projectId: number | string
}>()

const allRecords = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const activeGroups = ref(['领料', '销售', '报废'])

const formatDate = (s?: string | null) => s ? s.replace('T', ' ').slice(0, 16) : '-'
const formatMoney = (v?: number | string) =>
  Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const groups = computed(() => {
  const byType: Record<string, Record<string, unknown>[]> = { 领料: [], 销售: [], 报废: [] }
  for (const r of allRecords.value) {
    if (r.type === 'out') byType['领料'].push(r)
    else if (r.type === 'sale') byType['销售'].push(r)
    else if (r.type === 'scrap') byType['报废'].push(r)
    else byType['领料'].push(r)
  }
  return byType
})

const totalCount = computed(() =>
  groups.value['领料'].length + groups.value['销售'].length + groups.value['报废'].length
)

const totalAmount = computed(() =>
  allRecords.value.reduce((sum, r) => sum + Number((r as { total_amount?: number }).total_amount || 0), 0)
)

const load = async () => {
  const pid = Number(props.projectId)
  if (!Number.isFinite(pid) || pid <= 0) return
  loading.value = true
  try {
    // 一次性拉全部出库类型,通过聚合接口已按 record_no 聚合过
    const res = await get('/inventory/stock-records', { project_id: pid, per_page: 200 })
    const pag = unwrapPaginate(res)
    allRecords.value = (pag.list as Record<string, unknown>[])
      .filter((r: Record<string, unknown>) => {
        const t = r.type as string
        return t === 'out' || t === 'sale' || t === 'scrap'
      })
  } catch (e: unknown) {
    console.warn('加载出库详情失败:', e)
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
.text-warning { color: #E6A23C; }
.text-danger { color: #F56C6C; }
.purchase-collapse { margin-top: 16px; }
</style>
