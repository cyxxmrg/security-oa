<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">返修管理</span>
      <div class="header-actions">
        <el-tag type="info" size="large">共 {{ total }} 单</el-tag>
        <el-button type="primary" :icon="Plus" @click="showCreate = true" style="margin-left: 8px;">
          <span class="hide-mobile">新建返修</span>
          <el-icon class="show-mobile"><Plus /></el-icon>
        </el-button>
      </div>
    </div>

    <div class="content-card">
      <div class="filter-bar">
        <el-input v-model="keyword" placeholder="搜索返修单号/客户/设备" clearable :prefix-icon="Search" style="width: 240px" @input="debouncedSearch" />
        <el-select v-model="filterStatus" placeholder="状态" clearable style="width: 140px" @change="loadData">
          <el-option v-for="s in STATUS_OPTIONS" :key="s.value" :label="s.label" :value="s.value">
            <el-tag :type="s.color" size="small" effect="dark">{{ s.label }}</el-tag>
          </el-option>
        </el-select>
        <el-select v-model="filterMethod" placeholder="维修方式" clearable style="width: 160px" @change="loadData">
          <el-option label="🆓 免费（保内）" value="free_warranty" />
          <el-option label="🆓 免费（合同）" value="free_contract" />
          <el-option label="💰 付费（维修）" value="paid_repair" />
          <el-option label="💰 付费（换新）" value="paid_replace" />
          <el-option label="↩️ 退回" value="returned" />
        </el-select>
        <el-select v-model="filterSource" placeholder="来源" clearable style="width: 120px" @change="loadData">
          <el-option label="客户送修" value="customer" />
          <el-option label="工单转单" value="work_order" />
          <el-option label="内部" value="internal" />
        </el-select>
        <el-button :icon="Refresh" @click="loadData">刷新</el-button>
      </div>

      <el-table :data="list" border stripe v-loading="loading" class="pc-only" style="width:100%;">
        <el-table-column prop="code" label="返修单号" width="130">
          <template #default="{ row }">
            <code class="link" @click="goDetail(row.id)">{{ row.code }}</code>
            <div v-if="row.source_type === 'work_order'" class="src-tag">工单 #{{ row.source_code }}</div>
          </template>
        </el-table-column>
        <el-table-column label="客户" min-width="140">
          <template #default="{ row }">
            {{ row.customer_name || row.contact_name || '—' }}
            <div class="muted" v-if="row.contact_phone">{{ row.contact_phone }}</div>
          </template>
        </el-table-column>
        <el-table-column label="设备/故障" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            <div v-if="row.equipment_brand">{{ row.equipment_brand }} {{ row.equipment_model }}</div>
            <div v-else>—</div>
            <div class="muted">{{ row.fault_description }}</div>
          </template>
        </el-table-column>
        <el-table-column label="来源" width="80">
          <template #default="{ row }">
            <el-tag :type="row.source_type === 'work_order' ? 'warning' : 'info'" size="small" effect="plain">
              {{ row.source_type === 'work_order' ? '工单' : '客户' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="维修方式" width="120">
          <template #default="{ row }">
            <span v-if="row.method_label">{{ row.method_label }}</span>
            <span v-else class="muted">未选</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status_color" size="small" effect="dark">{{ row.status_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="费用" width="100">
          <template #default="{ row }">¥ {{ row.total_cost }}</template>
        </el-table-column>
        <el-table-column label="接件时间" width="120">
          <template #default="{ row }">{{ formatTime(row.received_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="80" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="goDetail(row.id)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 移动卡片 -->
      <div class="mobile-cards">
        <div v-for="r in list" :key="r.id" class="mobile-card" @click="goDetail(r.id)">
          <div class="mc-row1">
            <code class="wo-code">{{ r.code }}</code>
            <el-tag :type="r.status_color" size="small" effect="dark">{{ r.status_label }}</el-tag>
          </div>
          <div class="mc-row2">
            <el-icon><User /></el-icon>
            {{ r.customer_name || r.contact_name || '—' }}
            <span v-if="r.source_type === 'work_order'" class="src">@工单 {{ r.source_code }}</span>
          </div>
          <div class="mc-row3">{{ r.equipment_brand }} {{ r.equipment_model }}</div>
          <div class="mc-row4">
            <el-tag v-if="r.method_label" size="small" effect="plain">{{ r.method_label }}</el-tag>
            <span class="cost">¥ {{ r.total_cost }}</span>
            <span class="time">{{ timeAgo(r.received_at) }}</span>
          </div>
        </div>
        <el-empty v-if="!list.length && !loading" />
      </div>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="perPage"
          :page-sizes="[10, 20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="loadData"
          @size-change="loadData"
        />
      </div>
    </div>
  </div>
  <RepairViewDialog v-model="showRoView" :ro-id="viewRoId" />
  <CreateRepairDialog v-model="showCreate" @done="loadData" />
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Search, Refresh, Plus, User } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import { unwrapPaginate } from '@/utils/response'
import RepairViewDialog from './components/RepairViewDialog.vue'
import CreateRepairDialog from './components/CreateRepairDialog.vue'

const router = useRouter()
const STATUS_OPTIONS = [
  { value: 'received', label: '已接件', color: 'primary' },
  { value: 'sent_for_repair', label: '寄修中', color: 'warning' },
  { value: 'in_repair', label: '维修中', color: 'warning' },
  { value: 'repaired', label: '已修好', color: 'success' },
  { value: 'sent_back', label: '寄回中', color: 'warning' },
  { value: 'closed', label: '已关闭', color: 'info' },
  { value: 'cancelled', label: '已取消', color: 'info' },
]

const list = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const keyword = ref('')
const filterStatus = ref('')
const filterMethod = ref('')
const filterSource = ref('')

let debounceTimer: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; loadData() }, 300)
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await get('/repair-orders', {
      page: page.value,
      per_page: perPage.value,
      keyword: keyword.value,
      status: filterStatus.value,
      method_type: filterMethod.value,
      source_type: filterSource.value,
    })
    // V0.6.3: res = {code, data: paginator}
    const pag = unwrapPaginate(res)
    list.value = pag.list
    total.value = pag.total
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + (e?.message || ''))
  } finally { loading.value = false }
}

const goDetail = (id: number) => { showRoView.value = true; viewRoId.value = id }
const showRoView = ref(false)
const viewRoId = ref<number | null>(null)
const showCreate = ref(false)
const formatTime = (s: string) => s ? new Date(s).toLocaleDateString('zh-CN') : ''
const timeAgo = (s: string) => {
  if (!s) return ''
  const ms = Date.now() - new Date(s).getTime()
  const day = Math.floor(ms / 86400000)
  return day === 0 ? '今天' : `${day} 天前`
}

onMounted(() => loadData())
</script>

<style scoped lang="scss">
.page-container { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.page-title { font-size: 20px; font-weight: 600; }
.header-actions { display: flex; align-items: center; gap: 8px; }
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.filter-bar { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.muted { font-size: 12px; color: #909399; }
.link { color: #409EFF; cursor: pointer; text-decoration: underline; }
.src-tag { font-size: 11px; color: #E6A23C; margin-top: 2px; }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }

.pc-only { display: block; }
.mobile-cards { display: none; }
.mobile-card {
  background: #fafbfc; border-radius: 8px; padding: 12px; margin-bottom: 8px;
  border: 1px solid #ebeef5; cursor: pointer;
  &:active { background: #f0f2f5; }
}
.mc-row1 { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.mc-row2 { font-size: 13px; color: #303133; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; .src { color: #E6A23C; font-size: 11px; } }
.mc-row3 { font-size: 13px; color: #606266; margin-bottom: 4px; }
.mc-row4 { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #909399; .cost { color: #F56C6C; font-weight: 600; } .time { margin-left: auto; } }
.wo-code { font-size: 13px; font-weight: 600; }

@media (max-width: 768px) {
  .page-container { padding: 12px; }
  .pc-only { display: none; }
  .mobile-cards { display: block; }
  .mobile-card { display: block; }
  .hide-mobile { display: none; }
  .filter-bar { gap: 8px; }
  .filter-bar .el-input, .filter-bar .el-select { flex: 1; min-width: 100px; }
  .pagination-wrapper { justify-content: center; }
}
</style>
