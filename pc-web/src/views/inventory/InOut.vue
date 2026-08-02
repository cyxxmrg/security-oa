<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">库存流水记录</span>
    </div>

    <div class="content-card">
      <div class="filter-bar">
        <el-select v-model="filterType" placeholder="记录类型" clearable style="width: 160px;" @change="loadList(1)">
          <el-option label="入库"   value="in" />
          <el-option label="退库"   value="return" />
          <el-option label="出库"   value="out" />
          <el-option label="销售"   value="sale" />
          <el-option label="报废"   value="scrap" />
        </el-select>
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          style="width: 260px;"
          @change="loadList(1)"
        />
        <el-input v-model="searchKey" placeholder="搜索单号/物料名称/编号" clearable style="width: 240px;" :prefix-icon="Search" @keyup.enter="loadList(1)" @clear="loadList(1)" />
      </div>

      <el-table v-loading="loading" :data="list" border stripe style="width: 100%; margin-top: 12px;" max-height="600">
        <el-table-column prop="record_no" label="流水单号" width="180" />
        <el-table-column label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="recordTypeTag(row.type)" size="small">{{ recordTypeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="物料" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.inventoryItem">{{ row.inventoryItem.name }}（{{ row.inventoryItem.code }}）</span>
            <span v-else class="muted">物料 #{{ row.inventory_item_id }}</span>
          </template>
        </el-table-column>
        <el-table-column label="仓库" width="120">
          <template #default="{ row }">{{ row.warehouse?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="变动数量" width="120" align="right">
          <template #default="{ row }">
            <span :style="{ color: isInbound(row.type) ? '#1D9E75' : '#A32D2D', fontWeight: 600 }">
              {{ isInbound(row.type) ? '+' : '-' }}{{ row.quantity }}
            </span>
            <span class="unit-text"> {{ row.inventoryItem?.unit || '' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作后库存" width="120" align="center">
          <template #default="{ row }">{{ row.remaining_stock }} {{ row.inventoryItem?.unit || '' }}</template>
        </el-table-column>
        <el-table-column label="操作人" width="100">
          <template #default="{ row }">{{ row.operator?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="时间" width="170">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="200" show-overflow-tooltip />
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          background
          layout="total, prev, pager, next"
          :total="pagination.total"
          :current-page="pagination.page"
          :page-size="pagination.per_page"
          @current-change="(p: number) => loadList(p)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { Search } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import { unwrapPaginate } from '@/utils/response'
import type { StockRecord } from './types'

const filterType = ref<string>('')
const dateRange = ref<[string, string] | null>(null)
const searchKey = ref('')
const list = ref<StockRecord[]>([])
const loading = ref(false)
const pagination = reactive({ page: 1, per_page: 15, total: 0 })

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

const typeLabelMap: Record<string, string> = {
  in: '入库', return: '退库', out: '出库', sale: '销售', scrap: '报废',
}
const typeTagMap: Record<string, TagType> = {
  in: 'success', return: 'info', out: 'warning', sale: 'primary', scrap: 'danger',
}
const recordTypeLabel = (t?: string) => typeLabelMap[t ?? ''] || t || '-'
const recordTypeTag = (t?: string): TagType => typeTagMap[t ?? ''] || 'info'
const isInbound = (t?: string) => ['in', 'return'].includes(t ?? '')

const formatDate = (s?: string | null) => {
  if (!s) return '-'
  const d = new Date(s)
  if (isNaN(d.getTime())) return s
  const pad = (n: number) => n.toString().padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

async function loadList(page = 1) {
  pagination.page = page
  loading.value = true
  try {
    const params: Record<string, unknown> = { page, per_page: pagination.per_page }
    if (filterType.value) params.type = filterType.value
    if (searchKey.value)  params.keyword = searchKey.value
    if (dateRange.value) {
      params.date_from = dateRange.value[0]
      params.date_to = dateRange.value[1]
    }
    const res = await get('/inventory/stock-records', params)
    // V0.6.3: res = {code, data: paginator}
    const pag = unwrapPaginate(res)
    list.value = pag.list
    pagination.total = pag.total
  } catch (e) {
    console.error('[loadList]', e)
    list.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

onMounted(() => loadList(1))
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
  .page-title { font-size: 20px; font-weight: 600; color: #303133; }
}
.content-card {
  background: #fff; border-radius: 8px; padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}
.filter-bar { display: flex; gap: 12px; flex-wrap: wrap; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.muted { color: #c0c4cc; }
.unit-text { color: #909399; font-size: 12px; }
</style>
