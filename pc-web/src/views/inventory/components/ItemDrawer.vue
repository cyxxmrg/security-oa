<template>
  <el-drawer
    :model-value="visible"
    :title="drawerTitle"
    direction="rtl"
    size="640px"
    :close-on-click-modal="false"
    :destroy-on-close="true"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
  >
    <div v-loading="loading" class="item-drawer">
      <template v-if="item">
        <!-- 顶部状态横幅 -->
        <div v-if="warnings.length" class="warn-banner">
          <el-icon><WarningFilled /></el-icon>
          <span>{{ warnings.join('; ') }}</span>
        </div>

        <!-- Tabs -->
        <el-tabs v-model="activeTab" class="item-tabs">
          <el-tab-pane label="基本信息" name="basic">
            <el-descriptions :column="2" border size="default">
              <el-descriptions-item label="物品名称">{{ item.name }}</el-descriptions-item>
              <el-descriptions-item label="物料编号">{{ item.code }}</el-descriptions-item>
              <el-descriptions-item label="分类">{{ item.category || '-' }}</el-descriptions-item>
              <el-descriptions-item label="规格">{{ item.specification || '-' }}</el-descriptions-item>
              <el-descriptions-item label="单位">{{ item.unit }}</el-descriptions-item>
              <el-descriptions-item label="仓库">{{ item.warehouse?.name || '-' }}</el-descriptions-item>
              <el-descriptions-item label="库位">{{ item.location || '-' }}</el-descriptions-item>
              <el-descriptions-item label="成本价">¥{{ Number(item.cost_price || 0).toFixed(2) }}</el-descriptions-item>
              <el-descriptions-item label="销售价">¥{{ Number(item.sell_price || 0).toFixed(2) }}</el-descriptions-item>
              <el-descriptions-item label="状态">
                <el-tag :type="statusType" size="small" effect="dark">{{ statusLabel }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="当前库存" :span="2">
                <span :class="stockClass">{{ item.current_stock }} {{ item.unit }}</span>
                <span class="safety-tip">安全库存 {{ item.safety_stock }} {{ item.unit }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="备注" :span="2">{{ item.description || '-' }}</el-descriptions-item>
            </el-descriptions>
          </el-tab-pane>

          <el-tab-pane :label="`库存记录 (${stockRecords.length})`" name="stock">
            <el-table :data="stockRecords" border size="small" max-height="380">
              <el-table-column prop="created_at" label="时间" width="160">
                <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
              </el-table-column>
              <el-table-column label="类型" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.type?.includes('in') || row.type === 'inbound' || row.type === 'return' ? 'success' : 'warning'" size="small">
                    {{ stockTypeLabel(row.type) }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column label="数量" width="100" align="right">
                <template #default="{ row }">
                  <span :class="row.quantity > 0 ? 'qty-in' : 'qty-out'">
                    {{ row.quantity > 0 ? '+' : '' }}{{ row.quantity }}
                  </span>
                </template>
              </el-table-column>
              <el-table-column prop="warehouse?.name" label="仓库" width="120" show-overflow-tooltip />
              <el-table-column prop="remark" label="备注" show-overflow-tooltip />
            </el-table>
            <el-empty v-if="!stockLoading && !stockRecords.length" :image-size="60" description="暂无库存记录" />
          </el-tab-pane>

          <el-tab-pane :label="`序列号 (${serialNumbers.length})`" name="serial">
            <el-table :data="serialNumbers" border size="small" max-height="380">
              <el-table-column prop="serial_number" label="序列号" min-width="200" />
              <el-table-column label="状态" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'in_stock' ? 'success' : 'info'" size="small">
                    {{ row.status === 'in_stock' ? '在库' : '已出库' }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" label="创建时间" width="160">
                <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
              </el-table-column>
            </el-table>
            <el-empty v-if="!serialLoading && !serialNumbers.length" :image-size="60" description="无序列号" />
          </el-tab-pane>

          <el-tab-pane label="预警信息" name="warn">
            <div class="warn-list">
              <div v-if="!warnings.length" class="warn-empty">
                <el-icon :size="32" color="#1D9E75"><CircleCheckFilled /></el-icon>
                <span>当前无预警</span>
              </div>
              <div v-else>
                <el-alert
                  v-for="(w, i) in warnings"
                  :key="i"
                  :title="w"
                  :type="i === 0 && isLowStock ? 'error' : 'warning'"
                  show-icon
                  :closable="false"
                  class="warn-alert"
                />
              </div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </template>
      <el-empty v-else-if="!loading" description="未选择物品" :image-size="80" />
    </div>

    <template #footer>
      <div class="drawer-footer">
        <el-button @click="emit('update:visible', false)">关闭</el-button>
        <el-button v-if="item" type="primary" :icon="Edit" @click="emit('edit', item)">编辑</el-button>
      </div>
    </template>
  </el-drawer>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { WarningFilled, Edit, CircleCheckFilled } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import type { InventoryItem, StockRecord, SerialNumber } from '../types'

const props = defineProps<{
  visible: boolean
  item: InventoryItem | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'edit', item: InventoryItem): void
}>()

const activeTab = ref('basic')
const stockRecords = ref<StockRecord[]>([])
const stockLoading = ref(false)
const serialNumbers = ref<SerialNumber[]>([])
const serialLoading = ref(false)

const drawerTitle = computed(() => props.item ? `物品详情 - ${props.item.name}` : '物品详情')

const isLowStock = computed(() => {
  if (!props.item) return false
  return props.item.is_low_stock || (props.item.current_stock || 0) <= (props.item.safety_stock || 0)
})

const warnings = computed<string[]>(() => {
  const w: string[] = []
  if (!props.item) return w
  if (isLowStock.value) w.push(`库存不足: 当前 ${props.item.current_stock} ${props.item.unit} / 安全 ${props.item.safety_stock} ${props.item.unit}`)
  if (props.item.is_expiring) w.push('物品临期, 需关注保质期')
  return w
})

const statusType = computed(() => {
  if (!props.item) return 'info'
  if (isLowStock.value) return 'danger'
  if ((props.item.current_stock || 0) <= (props.item.safety_stock || 0) * 1.5) return 'warning'
  return 'success'
})
const statusLabel = computed(() => {
  if (!props.item) return '-'
  if (isLowStock.value) return '不足'
  if ((props.item.current_stock || 0) <= (props.item.safety_stock || 0) * 1.5) return '预警'
  return '正常'
})
const stockClass = computed(() => {
  if (!props.item) return ''
  if (isLowStock.value) return 'stock-text stock-text--danger'
  if ((props.item.current_stock || 0) <= (props.item.safety_stock || 0) * 1.5) return 'stock-text stock-text--warn'
  return 'stock-text'
})

watch(() => props.visible, async (v) => {
  if (v && props.item) {
    activeTab.value = 'basic'
    stockRecords.value = []
    serialNumbers.value = []
    await Promise.all([loadStock(), loadSerials()])
  }
})

async function loadStock() {
  if (!props.item) return
  stockLoading.value = true
  try {
    const res = await get(`/inventory/${props.item.id}/stock-records`, { per_page: 50 })
    // V0.6.3: res = {code, data: <list|page>}
    stockRecords.value = unwrapList(res)
  } catch (e) {
    console.warn('[loadStock]', e)
  } finally {
    stockLoading.value = false
  }
}

async function loadSerials() {
  if (!props.item) return
  serialLoading.value = true
  try {
    const res = await get(`/inventory/${props.item.id}/serial-numbers`, { per_page: 100 })
    // V0.6.3: res = {code, data: <list>}
    serialNumbers.value = unwrapList(res)
  } catch (e) {
    console.warn('[loadSerials]', e)
  } finally {
    serialLoading.value = false
  }
}

const STOCK_TYPE_LABELS: Record<string, string> = {
  inbound: '采购入库', return: '退库入库', outbound: '领用出库', sale: '销售出库', scrap: '报废出库',
}
function stockTypeLabel(t: string) {
  return STOCK_TYPE_LABELS[t] || t
}

function formatDateTime(s: string | null | undefined) {
  if (!s) return '-'
  const d = new Date(s)
  if (Number.isNaN(d.getTime())) return '-'
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
</script>

<style lang="scss" scoped>
.item-drawer { padding: 0 4px; }
.warn-banner {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #fef0f0;
  color: #A32D2D;
  border: 1px solid #fbc4c4;
  border-radius: 4px;
  padding: 8px 12px;
  margin-bottom: 12px;
  font-size: 13px;
}
.item-tabs { margin-top: 4px; }
.stock-text { font-weight: 700; font-size: 15px; }
.stock-text--danger { color: #A32D2D; }
.stock-text--warn { color: #BA7517; }
.safety-tip { color: #909399; font-size: 12px; margin-left: 12px; }
.qty-in { color: #1D9E75; font-weight: 600; }
.qty-out { color: #BA7517; font-weight: 600; }
.warn-list { padding: 8px 0; }
.warn-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #1D9E75;
  padding: 40px 0;
}
.warn-alert { margin-bottom: 8px; }
.drawer-footer { display: flex; justify-content: flex-end; gap: 8px; }
</style>
