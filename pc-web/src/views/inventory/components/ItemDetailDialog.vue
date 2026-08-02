<template>
  <el-dialog
    :model-value="visible"
    :title="detailItem ? `物品详情 - ${detailItem.name}` : '物品详情'"
    width="900px"
    :close-on-click-modal="false"
    :destroy-on-close="true"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
  >
    <div v-loading="loading" class="detail-body">
      <template v-if="detailItem">
        <!-- 顶部状态横幅 -->
        <div v-if="warnings.length" class="warn-banner">
          <el-icon><WarningFilled /></el-icon>
          <span>{{ warnings.join('; ') }}</span>
        </div>

        <!-- Tabs -->
        <el-tabs v-model="activeTab" class="detail-tabs">
          <el-tab-pane label="基本信息" name="basic">
            <el-descriptions :column="2" border size="default">
              <el-descriptions-item label="物品名称">{{ detailItem.name }}</el-descriptions-item>
              <el-descriptions-item label="物料编号">{{ detailItem.code }}</el-descriptions-item>
              <el-descriptions-item label="分类">{{ detailItem.categoryRef?.name || detailItem.category || '-' }}</el-descriptions-item>
              <el-descriptions-item label="规格">{{ detailItem.specification || '-' }}</el-descriptions-item>
              <el-descriptions-item label="单位">{{ detailItem.unit }}</el-descriptions-item>
              <el-descriptions-item label="仓库">{{ detailItem.warehouse?.name || '-' }}</el-descriptions-item>
              <el-descriptions-item label="库位">{{ detailItem.location || '-' }}</el-descriptions-item>
              <el-descriptions-item label="成本价">¥{{ Number(detailItem.cost_price || 0).toFixed(2) }}</el-descriptions-item>
              <el-descriptions-item label="销售价">¥{{ Number(detailItem.sell_price || 0).toFixed(2) }}</el-descriptions-item>
              <el-descriptions-item label="状态">
                <el-tag :type="statusType" size="small" effect="dark">{{ statusLabel }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="当前库存" :span="2">
                <span :class="stockClass">{{ detailItem.current_stock }} {{ detailItem.unit }}</span>
                <span class="safety-tip">安全库存 {{ detailItem.safety_stock }} {{ detailItem.unit }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="备注" :span="2">{{ detailItem.description || '-' }}</el-descriptions-item>
            </el-descriptions>
          </el-tab-pane>

          <el-tab-pane :label="`库存记录 (${(detailItem.stock_records||[]).length})`" name="stock">
            <el-table :data="detailItem.stock_records||[]" border size="small" max-height="380">
              <el-table-column label="时间" width="160">
                <template #default="{ row }">{{ formatDTTM(row.created_at) }}</template>
              </el-table-column>
              <el-table-column label="类型" width="100">
                <template #default="{ row }">
                  <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="数量" width="100" align="right">
                <template #default="{ row }">
                  <span :class="stockChgClass(row.type)">{{ stockChgSign(row.type) }}{{ row.quantity }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="warehouse?.name" label="仓库" width="120" show-overflow-tooltip />
              <el-table-column prop="remark" label="备注" show-overflow-tooltip />
            </el-table>
            <el-empty v-if="!(detailItem.stock_records||[]).length" :image-size="60" description="暂无库存记录" />
          </el-tab-pane>

          <el-tab-pane :label="`序列号 (${(detailItem.serial_numbers||[]).length})`" name="serial">
            <el-table :data="detailItem.serial_numbers||[]" border size="small" max-height="380">
              <el-table-column prop="serial_number" label="序列号" min-width="200" />
              <el-table-column label="状态" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'in_stock' ? 'success' : 'info'" size="small">
                    {{ row.status === 'in_stock' ? '在库' : '已出库' }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" label="创建时间" width="160">
                <template #default="{ row }">{{ formatDTTM(row.created_at) }}</template>
              </el-table-column>
            </el-table>
            <el-empty v-if="!(detailItem.serial_numbers||[]).length" :image-size="60" description="无序列号" />
          </el-tab-pane>

          <el-tab-pane label="预警信息" name="warn">
            <div class="warn-list">
              <div v-if="!warnings.length" class="warn-empty">
                <el-icon :size="32" color="#1D9E75"><CircleCheckFilled /></el-icon>
                <span>当前无预警</span>
              </div>
              <div v-else>
                <el-alert v-for="(w, i) in warnings" :key="i" :title="w" :type="i === 0 && isLowStock ? 'error' : 'warning'" show-icon :closable="false" class="warn-alert" />
              </div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </template>
      <el-empty v-else-if="!loading" description="未选择物品" :image-size="80" />
    </div>

    <template #footer>
      <el-button @click="emit('update:visible', false)">关闭</el-button>
      <el-button v-if="detailItem" type="primary" :icon="Edit" @click="emit('edit', detailItem)">编辑</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { WarningFilled, Edit, CircleCheckFilled } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import type { InventoryItem } from '../types'

const props = defineProps<{
  visible: boolean
  item: InventoryItem | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'edit', item: InventoryItem): void
}>()

const activeTab = ref('basic')
const loading = ref(false)
const detailItem = ref<Record<string, unknown> | null>(null)

const isLowStock = computed(() => {
  if (!detailItem.value) return false
  const cur = Number((detailItem.value as any).current_stock || 0)
  const safe = Number((detailItem.value as any).safety_stock || 0)
  return (detailItem.value as any).is_low_stock || cur <= safe
})

const warnings = computed<string[]>(() => {
  const w: string[] = []
  if (!detailItem.value) return w
  const d = detailItem.value as any
  if (isLowStock.value) w.push(`库存不足: 当前 ${d.current_stock} ${d.unit} / 安全 ${d.safety_stock} ${d.unit}`)
  if (d.is_expiring) w.push('物品临期, 需关注保质期')
  return w
})

const statusType = computed(() => {
  if (!detailItem.value) return 'info'
  if (isLowStock.value) return 'danger'
  const cur = Number((detailItem.value as any).current_stock || 0)
  const safe = Number((detailItem.value as any).safety_stock || 0)
  if (cur <= safe * 1.5) return 'warning'
  return 'success'
})

const statusLabel = computed(() => {
  if (!detailItem.value) return '-'
  if (isLowStock.value) return '不足'
  const cur = Number((detailItem.value as any).current_stock || 0)
  const safe = Number((detailItem.value as any).safety_stock || 0)
  if (cur <= safe * 1.5) return '预警'
  return '正常'
})

const stockClass = computed(() => {
  if (!detailItem.value) return ''
  if (isLowStock.value) return 'stock-text stock-text--danger'
  const cur = Number((detailItem.value as any).current_stock || 0)
  const safe = Number((detailItem.value as any).safety_stock || 0)
  if (cur <= safe * 1.5) return 'stock-text stock-text--warn'
  return 'stock-text'
})

const TYPE_LABELS: Record<string, string> = {
  in: '采购入库', return: '退库入库', out: '领用出库', sale: '销售出库', scrap: '报废出库', transfer: '调拨',
}
const inTypes = ['in', 'return']

function typeLabel(t?: string) { return TYPE_LABELS[t ?? ''] || t || '-' }
function typeTag(t?: string) { return inTypes.includes(t ?? '') ? 'success' : 'warning' }
function stockChgClass(t?: string) { return inTypes.includes(t ?? '') ? 'qty-in' : 'qty-out' }
function stockChgSign(t?: string) { return inTypes.includes(t ?? '') ? '+' : '-' }

function formatDTTM(s?: string | null) {
  if (!s) return '-'
  return s.replace('T', ' ').slice(0, 16)
}

watch(() => props.visible, async (v) => {
  if (v && props.item) {
    activeTab.value = 'basic'
    loading.value = true
    detailItem.value = null
    try {
      const res = await get(`/inventory/${props.item.id}`)
      detailItem.value = (res?.data ?? res) as Record<string, unknown>
    } catch (e) {
      console.warn('[loadDetail]', e)
      detailItem.value = props.item as unknown as Record<string, unknown>
    } finally {
      loading.value = false
    }
  }
})
</script>

<style scoped>
.detail-body { padding: 0 4px; }
.warn-banner {
  display: flex; align-items: center; gap: 6px;
  background: #fef0f0; color: #A32D2D;
  border: 1px solid #fbc4c4; border-radius: 4px;
  padding: 8px 12px; margin-bottom: 12px; font-size: 13px;
}
.detail-tabs { margin-top: 4px; }
.stock-text { font-weight: 700; font-size: 15px; }
.stock-text--danger { color: #A32D2D; }
.stock-text--warn { color: #BA7517; }
.safety-tip { color: #909399; font-size: 12px; margin-left: 12px; }
.qty-in { color: #1D9E75; font-weight: 600; }
.qty-out { color: #BA7517; font-weight: 600; }
.warn-list { padding: 8px 0; }
.warn-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; color: #1D9E75; padding: 40px 0; }
.warn-alert { margin-bottom: 8px; }
</style>
