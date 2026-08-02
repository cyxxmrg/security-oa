<template>
  <div class="page-container">
    <div class="page-header">
      <h2>巡检计划</h2>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadList(1)">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="$router.push('/inspection/plans/create')">新建巡检计划</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-input v-model="filter.keyword" placeholder="搜索计划名称/编号" clearable style="width: 220px" @keyup.enter="loadList(1)" @clear="loadList(1)" />
      <el-select v-model="filter.status" placeholder="状态" clearable style="width: 120px" @change="loadList(1)">
        <el-option v-for="(label, value) in PLAN_STATUS_LABEL" :key="value" :label="label" :value="value" />
      </el-select>
      <el-select v-model="filter.frequency" placeholder="排程频率" clearable style="width: 120px" @change="loadList(1)">
        <el-option v-for="(label, value) in FREQUENCY_LABEL" :key="value" :label="label" :value="value" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="loadList(1)">搜索</el-button>
      <el-button @click="resetFilter">重置</el-button>
    </div>

    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column prop="plan_no" label="计划编号" width="160" />
        <el-table-column prop="name" label="计划名称" min-width="180" show-overflow-tooltip />
        <el-table-column label="合同" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.contract">{{ row.contract.contract_no }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="客户" min-width="120" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.customer?.name || '-' }}</span></template>
        </el-table-column>
        <el-table-column label="频率" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small">{{ FREQUENCY_LABEL[row.frequency] || row.frequency }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="进度" width="160" align="center">
          <template #default="{ row }">
            <el-progress :percentage="row.completion_rate || 0" :stroke-width="14" :format="(v: number) => `${v}%`" />
            <div style="font-size: 11px; color: #999; margin-top: 2px">
              {{ row.total_completed }} / {{ row.total_generated }} · 异常 {{ row.total_issues }}
            </div>
          </template>
        </el-table-column>
        <el-table-column label="排程" width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <span style="font-size: 12px">{{ row.start_date }} ~ {{ row.end_date || '无限期' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="planStatusColor(row.status)" size="small">{{ PLAN_STATUS_LABEL[row.status] || row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="showDetail(row.id)">详情</el-button>
            <el-button v-if="row.status === 'active' || row.status === 'paused'" link :type="row.status === 'active' ? 'warning' : 'success'" size="small" @click="handleToggle(row)">{{ row.status === 'active' ? '暂停' : '启用' }}</el-button>
            <el-button v-if="row.status === 'active' || row.status === 'paused'" link type="info" size="small" @click="handleGenerate(row)">生成</el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty description="暂无巡检计划, 点击右上角新建" />
        </template>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination background layout="total, prev, pager, next" :total="pagination.total" :current-page="pagination.page" :page-size="pagination.per_page" @current-change="(p) => loadList(p)" />
      </div>
    </div>

    <!-- 详情弹窗 -->
    <el-dialog v-model="showDetailDialog" title="巡检计划详情" width="1440px" :close-on-click-modal="false" destroy-on-close>
      <PlanDetail v-if="detailPlanId" :plan-id="detailPlanId" embedded />
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Plus, Search } from '@element-plus/icons-vue'
import { inspection, PLAN_STATUS_LABEL, FREQUENCY_LABEL, type InspectionPlan } from '@/api/inspection'
import PlanDetail from './PlanDetail.vue'

const loading = ref(false)
const list = ref<InspectionPlan[]>([])
const showDetailDialog = ref(false)
const detailPlanId = ref<number | null>(null)

const showDetail = (id: number) => { detailPlanId.value = id; showDetailDialog.value = true }
const pagination = reactive({ total: 0, page: 1, per_page: 15 })
const filter = reactive<{ keyword: string; status: string; frequency: string }>({ keyword: '', status: '', frequency: '' })

const planStatusColor = (s: string) => ({ active: 'success', paused: 'warning', expired: 'info', cancelled: '' }[s] || '')

const loadList = async (page = 1) => {
  pagination.page = page
  loading.value = true
  try {
    const r = await inspection.listPlans({
      keyword: filter.keyword || undefined,
      status: (filter.status as string) || undefined,
      frequency: (filter.frequency as string) || undefined,
      per_page: pagination.per_page,
      page,
    })
    const d = r?.data ?? {}
    list.value = d.data || []
    pagination.total = d.total || 0
  } catch (e: unknown) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const resetFilter = () => {
  filter.keyword = ''
  filter.status = ''
  filter.frequency = ''
  loadList(1)
}

const handleToggle = async (row: InspectionPlan) => {
  try {
    await ElMessageBox.confirm(`确定要${row.status === 'active' ? '暂停' : '启用'}计划 [${row.name}] 吗?`, '确认操作', { type: 'warning' })
    await inspection.togglePlan(row.id)
    ElMessage.success('操作成功')
    loadList(pagination.page)
  } catch (e: unknown) {
    if (e !== 'cancel') ElMessage.error(e?.message || '操作失败')
  }
}

const handleGenerate = async (row: InspectionPlan) => {
  try {
    await ElMessageBox.confirm(`立即为计划 [${row.name}] 增量生成执行任务?`, '确认', { type: 'info' })
    const r = await inspection.generateTasks(row.id)
    ElMessage.success(`已生成 ${r?.data?.generated ?? 0} 个任务`)
    loadList(pagination.page)
  } catch (e: unknown) {
    if (e !== 'cancel') ElMessage.error(e?.message || '生成失败')
  }
}

onMounted(loadList)
</script>

<style scoped>
.page-container { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; }
.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.content-card { background: #fff; padding: 16px; border-radius: 8px; }
.pagination-wrap { margin-top: 16px; text-align: right; }
.muted { color: #999; }
</style>
