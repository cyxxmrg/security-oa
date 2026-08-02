<template>
  <div class="page-container">
    <div class="page-header">
      <h2>巡检异常</h2>
      <el-button :icon="Refresh" @click="loadList(1)">刷新</el-button>
    </div>

    <div class="filter-bar">
      <el-input v-model="filter.keyword" placeholder="异常号/设备" clearable style="width: 200px" @keyup.enter="loadList(1)" />
      <el-select v-model="filter.status" placeholder="状态" clearable style="width: 120px" @change="loadList(1)">
        <el-option v-for="(label, value) in ISSUE_STATUS_LABEL" :key="value" :label="label" :value="value" />
      </el-select>
      <el-select v-model="filter.severity" placeholder="严重程度" clearable style="width: 120px" @change="loadList(1)">
        <el-option v-for="(label, value) in SEVERITY_LABEL" :key="value" :label="label" :value="value" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="loadList(1)">搜索</el-button>
      <el-button @click="resetFilter">重置</el-button>
    </div>

    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column prop="issue_no" label="异常号" width="140" />
        <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
        <el-table-column prop="equipment_name" label="设备" min-width="100" show-overflow-tooltip />
        <el-table-column label="类型" width="100">
          <template #default="{ row }"><el-tag size="small">{{ ISSUE_TYPE_LABEL[row.issue_type] || row.issue_type }}</el-tag></template>
        </el-table-column>
        <el-table-column label="严重" width="80" align="center">
          <template #default="{ row }"><el-tag :type="severityColor(row.severity)" size="small">{{ SEVERITY_LABEL[row.severity] || row.severity }}</el-tag></template>
        </el-table-column>
        <el-table-column label="状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="issueStatusColor(row.status)" size="small">{{ ISSUE_STATUS_LABEL[row.status] || row.status }}</el-tag>
            <el-link v-if="row.work_order_id" type="primary" :href="`/maintenance/work-orders/${row.work_order_id}`" target="_blank" style="margin-left: 4px">工单</el-link>
          </template>
        </el-table-column>
        <el-table-column label="客户" min-width="120" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.customer?.name || '-' }}</span></template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160" />
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleView(row)">查看</el-button>
            <el-button v-if="row.status === 'open'" link type="success" size="small" @click="handleResolve(row)">解决</el-button>
            <el-button v-if="row.status === 'open'" link type="warning" size="small" @click="handleIgnore(row)">忽略</el-button>
            <el-button v-if="row.status === 'open' && !row.work_order_id" link type="danger" size="small" @click="handleConvert(row)">转工单</el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty description="暂无巡检异常" />
        </template>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination background layout="total, prev, pager, next" :total="pagination.total" :current-page="pagination.page" :page-size="pagination.per_page" @current-change="(p) => loadList(p)" />
      </div>
    </div>

    <!-- 详情 Dialog -->
    <el-dialog v-model="detailVisible" title="异常详情" width="700px" :close-on-click-modal="false">
      <div v-if="currentIssue" v-loading="detailLoading">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="异常号">{{ currentIssue.issue_no }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="issueStatusColor(currentIssue.status)" size="small">{{ ISSUE_STATUS_LABEL[currentIssue.status] || currentIssue.status }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="标题">{{ currentIssue.title }}</el-descriptions-item>
          <el-descriptions-item label="设备">{{ currentIssue.equipment_name }}</el-descriptions-item>
          <el-descriptions-item label="类型">{{ ISSUE_TYPE_LABEL[currentIssue.issue_type] || currentIssue.issue_type }}</el-descriptions-item>
          <el-descriptions-item label="严重程度">
            <el-tag :type="severityColor(currentIssue.severity)" size="small">{{ SEVERITY_LABEL[currentIssue.severity] || currentIssue.severity }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="设备位置" :span="2">{{ currentIssue.equipment_location || '-' }}</el-descriptions-item>
          <el-descriptions-item label="描述" :span="2">{{ currentIssue.description }}</el-descriptions-item>
          <el-descriptions-item label="关联工单" :span="2">
            <el-link v-if="currentIssue.work_order_id" type="primary" :href="`/maintenance/work-orders/${currentIssue.work_order_id}`" target="_blank">查看工单 #{{ currentIssue.work_order_id }}</el-link>
            <span v-else class="muted">-</span>
          </el-descriptions-item>
          <el-descriptions-item v-if="currentIssue.resolution" label="处理方案" :span="2">{{ currentIssue.resolution }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import { inspection, ISSUE_STATUS_LABEL, ISSUE_TYPE_LABEL, SEVERITY_LABEL, type InspectionIssue } from '@/api/inspection'

const route = useRoute()
const loading = ref(false)
const list = ref<InspectionIssue[]>([])
const pagination = reactive({ total: 0, page: 1, per_page: 15 })
const filter = reactive<{ keyword: string; status: string; severity: string }>({ keyword: '', status: '', severity: '' })

const detailVisible = ref(false)
const detailLoading = ref(false)
const currentIssue = ref<InspectionIssue | null>(null)

const issueStatusColor = (s: string) => ({ open: 'danger', work_order_created: 'warning', resolved: 'success', ignored: '' }[s] || '')
const severityColor = (s: string) => ({ low: 'info', medium: 'warning', high: 'danger', critical: 'danger' }[s] || '')

const loadList = async (page = 1) => {
  pagination.page = page
  loading.value = true
  try {
    const r = await inspection.listIssues({
      keyword: filter.keyword || undefined,
      status: (filter.status as string) || undefined,
      severity: (filter.severity as string) || undefined,
      per_page: pagination.per_page,
      page,
    })
    const d = r?.data ?? {}
    list.value = d.data || []
    pagination.total = d.total || 0
  } finally {
    loading.value = false
  }
}

const resetFilter = () => {
  filter.keyword = ''
  filter.status = ''
  filter.severity = ''
  loadList(1)
}

const handleView = async (row: InspectionIssue) => {
  detailVisible.value = true
  detailLoading.value = true
  try {
    const r = await inspection.getIssue(row.id)
    currentIssue.value = r?.data
  } catch (e: unknown) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    detailLoading.value = false
  }
}

const handleResolve = async (row: InspectionIssue) => {
  try {
    const { value: resolution } = await ElMessageBox.prompt('请输入解决方案', '解决异常', { inputType: 'textarea' })
    if (!resolution) return
    await inspection.resolveIssue(row.id, resolution)
    ElMessage.success('已解决')
    loadList(pagination.page)
  } catch (e: unknown) {
    if (e !== 'cancel') ElMessage.error(e?.message || '操作失败')
  }
}

const handleIgnore = async (row: InspectionIssue) => {
  try {
    const { value: reason } = await ElMessageBox.prompt('请输入忽略原因', '忽略异常', { inputType: 'textarea' })
    if (!reason) return
    await inspection.ignoreIssue(row.id, reason)
    ElMessage.success('已忽略')
    loadList(pagination.page)
  } catch (e: unknown) {
    if (e !== 'cancel') ElMessage.error(e?.message || '操作失败')
  }
}

const handleConvert = async (row: InspectionIssue) => {
  try {
    await ElMessageBox.confirm('将异常转为维修工单?', '确认', { type: 'warning' })
    const r = await inspection.convertIssue(row.id)
    ElMessage.success(`已生成工单 #${r?.data?.id ?? ''}`)
    loadList(pagination.page)
  } catch (e: unknown) {
    if (e !== 'cancel') ElMessage.error(e?.message || '转工单失败')
  }
}

onMounted(async () => {
  await loadList()
  // 支持 ?view=ID 直接打开详情
  if (route.query.view) {
    const r = await inspection.getIssue(Number(route.query.view))
    if (r?.data) {
      currentIssue.value = r.data
      detailVisible.value = true
    }
  }
})
</script>

<style scoped>
.page-container { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.content-card { background: #fff; padding: 16px; border-radius: 8px; }
.pagination-wrap { margin-top: 16px; text-align: right; }
.muted { color: #999; }
</style>
