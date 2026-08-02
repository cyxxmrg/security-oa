<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">系统日志</span>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="fetchLogs">刷新</el-button>
        <el-button :icon="Download" @click="handleExport">导出CSV</el-button>
      </div>
    </div>

    <div class="content-card">
      <el-tabs v-model="activeTab">
        <!-- 操作日志 (审计 Observer) -->
        <el-tab-pane :label="`操作日志 (${logsTotal})`" name="operation">
          <div class="tab-toolbar">
            <el-input v-model="filters.keyword" placeholder="搜索操作人/模块/描述" clearable style="width: 280px;" :prefix-icon="Search" @input="debouncedFetch" />
            <el-select v-model="filters.action" placeholder="操作类型" clearable style="width: 130px; margin-left: 12px;" @change="fetchLogs">
              <el-option label="全部" value="" />
              <el-option label="新增" value="新增" />
              <el-option label="修改" value="修改" />
              <el-option label="删除" value="删除" />
            </el-select>
            <el-select v-model="filters.module" placeholder="业务模块" clearable style="width: 140px; margin-left: 12px;" @change="fetchLogs">
              <el-option label="项目" value="项目" />
              <el-option label="客户" value="客户" />
              <el-option label="员工" value="员工" />
              <el-option label="售后" value="售后" />
              <el-option label="报销" value="报销" />
              <el-option label="车辆" value="车辆" />
              <el-option label="库存" value="库存" />
              <el-option label="知识库" value="知识库" />
              <el-option label="采购" value="采购" />
              <el-option label="应收" value="应收" />
              <el-option label="应付" value="应付" />
              <el-option label="角色" value="角色" />
              <el-option label="权限" value="权限" />
            </el-select>
            <el-date-picker
              v-model="filters.dateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="YYYY-MM-DD"
              style="width: 260px; margin-left: 12px;"
              @change="fetchLogs"
            />
          </div>

          <el-table :data="opLogs" border stripe style="width: 100%; margin-top: 16px;" v-loading="loading">
            <el-table-column prop="id" label="编号" width="80" align="center" />
            <el-table-column prop="operator" label="操作人" width="120">
              <template #default="{ row }">
                <div class="operator-cell">
                  <el-avatar :size="24" :style="{ background: avatarColor(row.operator) }">{{ row.operator?.charAt(0) || '?' }}</el-avatar>
                  <span>{{ row.operator }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="action" label="操作类型" width="100">
              <template #default="{ row }">
                <el-tag :type="actionTagType(row.action)" size="small">{{ row.action }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="module" label="操作模块" width="120" />
            <el-table-column prop="description" label="操作详情" min-width="280" show-overflow-tooltip />
            <el-table-column prop="ip" label="IP地址" width="150" />
            <el-table-column prop="time" label="操作时间" width="170" />
            <el-table-column prop="result" label="结果" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.result === '成功' ? 'success' : 'danger'" size="small">{{ row.result }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" size="small" @click="handleViewDetail(row)">详情</el-button>
              </template>
            </el-table-column>
          </el-table>

          <el-pagination
            v-model:current-page="page"
            v-model:page-size="perPage"
            :total="logsTotal"
            :page-sizes="[20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            style="margin-top: 16px; justify-content: flex-end"
            @current-change="fetchLogs"
            @size-change="fetchLogs"
          />
        </el-tab-pane>

        <!-- 登录日志 -->
        <el-tab-pane label="登录日志" name="login">
          <div class="tab-toolbar">
            <el-input v-model="loginSearch" placeholder="搜索登录用户" clearable style="width: 260px;" :prefix-icon="Search" />
          </div>
          <el-table :data="loginLogs" border stripe style="width: 100%; margin-top: 16px;" max-height="520" v-loading="loadingLogin">
            <el-table-column prop="id" label="编号" width="80" align="center" />
            <el-table-column prop="operator" label="登录用户" width="140">
              <template #default="{ row }">
                <div class="operator-cell">
                  <el-avatar :size="24" :style="{ background: avatarColor(row.operator) }">{{ row.operator?.charAt(0) || '?' }}</el-avatar>
                  <span>{{ row.operator }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="ip" label="登录IP" width="150" />
            <el-table-column prop="time" label="登录时间" width="180" />
            <el-table-column prop="result" label="状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="row.result === '成功' ? 'success' : 'danger'" size="small">{{ row.result }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="description" label="备注" min-width="240" show-overflow-tooltip />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- 详情抽屉 -->
    <el-drawer v-model="detailVisible" title="日志详情" size="520px">
      <div v-if="detailLog" class="detail-panel">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="编号">#{{ detailLog.id }}</el-descriptions-item>
          <el-descriptions-item label="操作人">{{ detailLog.operator }} (ID: {{ detailLog.operatorId }})</el-descriptions-item>
          <el-descriptions-item label="操作类型">
            <el-tag :type="actionTagType(detailLog.action)" size="small">{{ detailLog.action }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="业务模块">{{ detailLog.module }}</el-descriptions-item>
          <el-descriptions-item label="操作详情">{{ detailLog.description }}</el-descriptions-item>
          <el-descriptions-item label="IP 地址">{{ detailLog.ip || '-' }}</el-descriptions-item>
          <el-descriptions-item label="响应码">{{ detailLog.response || '-' }}</el-descriptions-item>
          <el-descriptions-item label="操作时间">{{ detailLog.time }}</el-descriptions-item>
          <el-descriptions-item v-if="detailLog.requestData" label="请求数据">
            <pre class="json-block">{{ JSON.stringify(detailLog.requestData, null, 2) }}</pre>
          </el-descriptions-item>
        </el-descriptions>
      </div>
    </el-drawer>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Search, Download, Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get } from '@/utils/request'
import { unwrapPaginate } from '@/utils/response'
import { exportCsv } from '@/utils/exporter'

interface AuditLog {
  id: number
  operator: string
  operatorId: number
  action: string
  module: string
  description: string
  ip: string
  time: string
  response: number | null
  result: string
  requestData: Record<string, unknown>
}

const activeTab = ref('operation')
const opLogs = ref<AuditLog[]>([])
const loginLogs = ref<AuditLog[]>([])
const loading = ref(false)
const loadingLogin = ref(false)
const page = ref(1)
const perPage = ref(20)
const logsTotal = ref(0)
const loginSearch = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

const filters = reactive({
  keyword: '',
  action: '',
  module: '',
  dateRange: null as [string, string] | null,
})

const COLOR_POOL = ['#0C447C', '#1D9E75', '#BA7517', '#534AB7', '#A32D2D', '#909399']
function avatarColor(name: string) {
  if (!name) return '#909399'
  let h = 0
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0
  return COLOR_POOL[h % COLOR_POOL.length]
}

function actionTagType(action: string) {
  const map: Record<string, string> = { '新增': 'success', '修改': 'warning', '删除': 'danger', 'login': 'primary' }
  return map[action] || 'info'
}

async function fetchLogs() {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: page.value, per_page: perPage.value }
    if (filters.keyword) params.keyword = filters.keyword
    if (filters.action)  params.action = filters.action
    if (filters.module)  params.module = filters.module
    if (filters.dateRange) {
      params.start_date = filters.dateRange[0]
      params.end_date = filters.dateRange[1]
    }
    // V0.6.3: res = {code, data: paginator}
    const res = await get('/audit-logs', params)
    const pag = unwrapPaginate(res)
    opLogs.value = pag.list
    logsTotal.value = pag.total
  } catch (e) {
    /* request.ts already toasted */
  } finally {
    loading.value = false
  }
}

function debouncedFetch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchLogs()
  }, 300)
}

onMounted(() => {
  fetchLogs()
})

// ========== 详情 ==========
const detailVisible = ref(false)
const detailLog = ref<AuditLog | null>(null)

function handleViewDetail(row: AuditLog) {
  detailLog.value = row
  detailVisible.value = true
}

async function handleExport() {
  if (opLogs.value.length === 0) {
    ElMessage.warning('当前列表为空，无可导出数据')
    return
  }
  const headers = ['编号', '操作人', '操作类型', '业务模块', '描述', 'IP', '时间', '结果']
  const rows = opLogs.value.map((r: Record<string, unknown>) => [
    r.id, r.operator, r.action, r.module, r.description, r.ip, r.time, r.result,
  ])
  exportCsv(headers, rows, '审计日志')
}
</script>

<style scoped lang="scss">
.page-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100%;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;

  .page-title {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
  }
}

.content-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.tab-toolbar {
  display: flex;
  align-items: center;
  padding-bottom: 4px;
  flex-wrap: wrap;
  gap: 0;
}

.operator-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.detail-panel {
  padding: 0 20px;
}

.json-block {
  background: #f5f7fa;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  padding: 12px;
  font-size: 12px;
  font-family: 'Courier New', monospace;
  overflow: auto;
  max-height: 200px;
  white-space: pre-wrap;
  word-break: break-all;
  margin: 0;
}
</style>
