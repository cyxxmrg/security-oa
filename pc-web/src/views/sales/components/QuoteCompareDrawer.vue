<script setup lang="ts">
/**
 * QuoteCompareDrawer — 报价单版本对比 (v0.3.14 B3)
 *
 * 横向对比两个版本的：
 * - 状态 / 折扣 / 税率 / 有效期
 * - 总金额差异
 * - items 差异（新增 / 删除 / 改价）
 */
import { computed, ref, watch } from 'vue'
import { ArrowRight, Plus, Minus, Edit, Money } from '@element-plus/icons-vue'
import { getQuoteDetail } from '@/api/sales'
import { ElMessage } from 'element-plus'
import {
  quoteStatusLabel, quoteStatusTagType, formatMoney, type QuoteItem,
} from '../quoteTypes'
import type { TagType, Quote } from '../types'

const props = defineProps<{
  visible: boolean
  leftId?: number
  rightId?: number
  /** 左侧 (基准) 版本号 */
  leftVersion?: number
  /** 右侧 (对比) 版本号 */
  rightVersion?: number
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
}>()

const loading = ref(false)
const leftDetail = ref<Quote | null>(null)
const rightDetail = ref<Quote | null>(null)

const dialogVisible = computed({
  get: () => props.visible,
  set: (v) => emit('update:visible', v),
})

watch(
  () => [props.visible, props.leftId, props.rightId],
  async ([v]) => {
    if (!v || !props.leftId || !props.rightId) return
    loading.value = true
    try {
      const [l, r] = await Promise.all([
        getQuoteDetail(props.leftId),
        getQuoteDetail(props.rightId),
      ])
      leftDetail.value = l || null
      rightDetail.value = r || null
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      ElMessage.error(err?.response?.data?.message || '加载对比失败')
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

/** 左侧 items 索引（按 product_id 或 code 区分） */
const leftKeyMap = computed(() => {
  const m = new Map<string, QuoteItem>()
  ;(leftDetail.value?.items || []).forEach((it: QuoteItem) => {
    m.set(itemKey(it), it)
  })
  return m
})

const rightKeyMap = computed(() => {
  const m = new Map<string, QuoteItem>()
  ;(rightDetail.value?.items || []).forEach((it: QuoteItem) => {
    m.set(itemKey(it), it)
  })
  return m
})

function itemKey(it: QuoteItem): string {
  if (it.product_id) return `p:${it.product_id}`
  if (it.code) return `c:${it.code}`
  return `n:${it.name}`
}

interface DiffRow {
  key: string
  name: string
  left?: QuoteItem
  right?: QuoteItem
  status: 'added' | 'removed' | 'modified' | 'unchanged'
  qtyDelta?: number
  priceDelta?: number
  totalDelta?: number
}

const diffRows = computed<DiffRow[]>(() => {
  const allKeys = new Set([...leftKeyMap.value.keys(), ...rightKeyMap.value.keys()])
  const rows: DiffRow[] = []
  for (const k of allKeys) {
    const l = leftKeyMap.value.get(k)
    const r = rightKeyMap.value.get(k)
    const leftQty = Number(l?.quantity) || 0
    const rightQty = Number(r?.quantity) || 0
    const leftPrice = Number(l?.unit_price) || 0
    const rightPrice = Number(r?.unit_price) || 0
    const leftTotal = leftQty * leftPrice
    const rightTotal = rightQty * rightPrice
    let status: DiffRow['status']
    if (!l && r) status = 'added'
    else if (l && !r) status = 'removed'
    else if (leftQty !== rightQty || Math.abs(leftPrice - rightPrice) > 0.01) status = 'modified'
    else status = 'unchanged'
    rows.push({
      key: k,
      name: (r || l)?.name || '—',
      left: l,
      right: r,
      status,
      qtyDelta: rightQty - leftQty,
      priceDelta: rightPrice - leftPrice,
      totalDelta: rightTotal - leftTotal,
    })
  }
  // 排序：先 modified / added / removed，最后 unchanged
  return rows.sort((a, b) => {
    const order = { modified: 0, added: 1, removed: 2, unchanged: 3 }
    return order[a.status] - order[b.status]
  })
})

const totalDelta = computed(() =>
  diffRows.value.reduce((sum, r) => sum + (r.totalDelta || 0), 0),
)

const totalLeft = computed(() => Number(leftDetail.value?.total_amount) || 0)
const totalRight = computed(() => Number(rightDetail.value?.total_amount) || 0)

const diffStats = computed(() => ({
  added:   diffRows.value.filter((r) => r.status === 'added').length,
  removed: diffRows.value.filter((r) => r.status === 'removed').length,
  modified: diffRows.value.filter((r) => r.status === 'modified').length,
  unchanged: diffRows.value.filter((r) => r.status === 'unchanged').length,
}))

const formatDelta = (n: number, prefix = '¥ ') => {
  const sign = n > 0 ? '+' : ''
  return `${sign}${prefix}${formatMoney(Math.abs(n))}`
}

const close = () => {
  dialogVisible.value = false
}
</script>

<template>
  <el-drawer
    v-model="dialogVisible"
    :title="`版本对比 V${leftVersion || '-'} ⇄ V${rightVersion || '-'}`"
    direction="rtl"
    size="80%"
    :close-on-click-modal="false"
    @close="close"
  >
    <div v-loading="loading" class="cmp-root">
      <!-- 头部对比卡 -->
      <div v-if="leftDetail && rightDetail" class="cmp-header">
        <div class="cmp-side">
          <div class="cs-version">V{{ leftVersion }}</div>
          <el-tag :type="quoteStatusTagType(leftDetail.status)" effect="dark" size="large">
            {{ quoteStatusLabel(leftDetail.status) }}
          </el-tag>
          <div class="cs-amount">¥ {{ formatMoney(totalLeft) }}</div>
          <div class="cs-meta">折扣 {{ leftDetail.discount_rate || 0 }}% · 税 {{ leftDetail.tax_rate || 0 }}%</div>
          <div class="cs-meta">有效期至 {{ leftDetail.valid_until?.slice(0, 10) || '—' }}</div>
        </div>
        <el-icon :size="40" color="#909399"><ArrowRight /></el-icon>
        <div class="cmp-side right">
          <div class="cs-version">V{{ rightVersion }}</div>
          <el-tag :type="quoteStatusTagType(rightDetail.status)" effect="dark" size="large">
            {{ quoteStatusLabel(rightDetail.status) }}
          </el-tag>
          <div class="cs-amount">¥ {{ formatMoney(totalRight) }}</div>
          <div class="cs-meta">折扣 {{ rightDetail.discount_rate || 0 }}% · 税 {{ rightDetail.tax_rate || 0 }}%</div>
          <div class="cs-meta">有效期至 {{ rightDetail.valid_until?.slice(0, 10) || '—' }}</div>
        </div>
      </div>

      <!-- 差异统计 -->
      <div v-if="!loading" class="diff-stats">
        <el-tag type="success" effect="plain">
          <el-icon><Plus /></el-icon>
          新增 {{ diffStats.added }}
        </el-tag>
        <el-tag type="danger" effect="plain">
          <el-icon><Minus /></el-icon>
          移除 {{ diffStats.removed }}
        </el-tag>
        <el-tag type="warning" effect="plain">
          <el-icon><Edit /></el-icon>
          变更 {{ diffStats.modified }}
        </el-tag>
        <el-tag type="info" effect="plain">
          <el-icon><Money /></el-icon>
          总额差 {{ formatDelta(totalDelta) }}
        </el-tag>
      </div>

      <!-- items 差异表 -->
      <el-table :data="diffRows" border size="default" v-if="!loading" class="diff-table">
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag
              :type="({added:'success', removed:'danger', modified:'warning', unchanged:'info'} as Record<string, string>)[row.status]"
              size="small"
              effect="dark"
            >
              {{
                ({added:'新增', removed:'移除', modified:'变更', unchanged:'一致'} as Record<string, string>)[row.status]
              }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="产品" min-width="200" />
        <el-table-column label="数量差异" width="120" align="right">
          <template #default="{ row }">
            <span v-if="row.status === 'modified'" :class="row.qtyDelta! > 0 ? 'text-success' : 'text-danger'">
              {{ formatDelta(row.qtyDelta || 0, '') }}
            </span>
            <span v-else style="color: #909399">—</span>
          </template>
        </el-table-column>
        <el-table-column label="单价差异" width="120" align="right">
          <template #default="{ row }">
            <span v-if="row.status === 'modified'" :class="row.priceDelta! > 0 ? 'text-danger' : 'text-success'">
              {{ formatDelta(row.priceDelta || 0) }}
            </span>
            <span v-else style="color: #909399">—</span>
          </template>
        </el-table-column>
        <el-table-column label="小计差异" width="140" align="right">
          <template #default="{ row }">
            <span v-if="row.status === 'modified'" :class="row.totalDelta! > 0 ? 'text-danger' : 'text-success'">
              {{ formatDelta(row.totalDelta || 0) }}
            </span>
            <span v-else style="color: #909399">—</span>
          </template>
        </el-table-column>
        <el-table-column label="左 → 右" min-width="200">
          <template #default="{ row }">
            <div class="row-arrow">
              <span v-if="row.left">{{ row.left.quantity }} × ¥{{ formatMoney(row.left.unit_price) }}</span>
              <span v-else style="color: #909399">无</span>
              <el-icon><ArrowRight /></el-icon>
              <span v-if="row.right">{{ row.right.quantity }} × ¥{{ formatMoney(row.right.unit_price) }}</span>
              <span v-else style="color: #909399">无</span>
            </div>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loading && !diffRows.length" description="两个版本无 items 数据" />
    </div>
  </el-drawer>
</template>

<style scoped>
.cmp-root {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.cmp-header {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 16px;
  background: linear-gradient(135deg, #f5f7fa 0%, #ebeef5 100%);
  border-radius: 8px;
}
.cmp-side {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px;
  background: white;
  border-radius: 6px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.cmp-side.right {
  background: linear-gradient(135deg, #fff 0%, #f5faff 100%);
}
.cs-version {
  font-size: 24px;
  font-weight: 700;
  color: #303133;
}
.cs-amount {
  font-size: 20px;
  font-weight: 600;
  color: #BA7517;
  margin-top: 4px;
}
.cs-meta {
  font-size: 12px;
  color: #606266;
}
.diff-stats {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  padding: 4px 0;
}
.diff-stats .el-icon {
  vertical-align: -2px;
  margin-right: 4px;
}
.diff-table {
  margin-top: 4px;
}
.row-arrow {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #606266;
}
.text-success { color: #67C23A; font-weight: 500; }
.text-danger  { color: #F56C6C; font-weight: 500; }
</style>
