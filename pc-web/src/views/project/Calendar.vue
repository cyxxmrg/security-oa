<template>
  <div :class="embedded ? 'embedded-container' : 'page-container'">
    <div v-if="!embedded" class="page-header">
      <div class="title-area">
        <span class="page-title">合同付款日历</span>
        <el-tag effect="light" type="info">{{ filteredCalendar.length }} 个月</el-tag>
        <el-tag v-if="filteredSummary.overdue_count > 0" type="danger" effect="dark" size="small">{{ filteredSummary.overdue_count }} 个逾期</el-tag>
        <el-tag v-if="filteredSummary.soon_count > 0" type="warning" effect="dark" size="small">{{ filteredSummary.soon_count }} 个 7 天内到期</el-tag>
      </div>
      <div class="header-actions">
        <el-radio-group v-model="filterStatus" size="default" @change="loadList">
          <el-radio-button value="">全部</el-radio-button>
          <el-radio-button value="pending">待付</el-radio-button>
          <el-radio-button value="paid">已付</el-radio-button>
          <el-radio-button value="overdue">逾期</el-radio-button>
        </el-radio-group>
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
        <el-button :icon="List" @click="$router.push('/project/list')">返回列表</el-button>
      </div>
    </div>

    <!-- 顶部汇总卡片 -->
    <div class="summary-row">
      <div class="summary-card" style="border-color: #0C447C">
        <div class="sum-label">合同节点总数</div>
        <div class="sum-value">{{ filteredSummary.total_count }}</div>
        <div class="sum-extra">总金额 ¥ {{ formatMoney(filteredSummary.total_amount) }}</div>
      </div>
      <div class="summary-card" style="border-color: #1D9E75">
        <div class="sum-label">已付款</div>
        <div class="sum-value" style="color: #1D9E75">¥ {{ formatMoney(filteredSummary.paid_amount) }}</div>
        <div class="sum-extra">回款率 {{ paymentRate }}%</div>
      </div>
      <div class="summary-card" style="border-color: #BA7517">
        <div class="sum-label">待付款</div>
        <div class="sum-value" style="color: #BA7517">¥ {{ formatMoney(filteredSummary.pending_amount) }}</div>
        <div class="sum-extra">{{ filteredSummary.total_count - filteredSummary.overdue_count }} 个待付节点</div>
      </div>
      <div class="summary-card" style="border-color: #A32D2D">
        <div class="sum-label">逾期</div>
        <div class="sum-value" style="color: #A32D2D">¥ {{ formatMoney(filteredSummary.overdue_amount) }}</div>
        <div class="sum-extra">{{ filteredSummary.overdue_count }} 个逾期节点</div>
      </div>
    </div>

    <div class="content-card">
      <el-empty v-if="!filteredCalendar.length" description="暂无付款节点" :image-size="80" />
      <el-timeline v-else>
        <el-timeline-item
          v-for="month in filteredCalendar"
          :key="month.month"
          :timestamp="monthLabel(month.month)"
          placement="top"
          type="primary"
        >
          <el-card shadow="hover" class="month-card">
            <div class="month-header">
              <div>
                <span class="month-title">{{ monthLabel(month.month) }}</span>
                <el-tag size="small" type="info" effect="plain" style="margin-left: 8px">{{ month.count }} 个节点</el-tag>
              </div>
              <div class="month-amount">
                ¥ {{ formatMoney(month.total_amount) }}
                <span class="month-paid">已付 ¥{{ formatMoney(month.paid_amount) }}</span>
              </div>
            </div>
            <el-table :data="month.items" border size="default" :show-header="false">
              <el-table-column label="" width="40">
                <template #default="{ row }">
                  <el-icon :size="16" :color="statusColor(row)">
                    <component :is="statusIcon(row)" />
                  </el-icon>
                </template>
              </el-table-column>
              <el-table-column label="" min-width="200">
                <template #default="{ row }">
                  <div class="item-name">{{ row.name }}</div>
                  <div class="item-sub">
                    <span class="item-project" @click="goProject(row.project_id)">@{{ row.project_name || '-' }}</span>
                    <span class="item-customer">· {{ row.customer_name || '-' }}</span>
                  </div>
                </template>
              </el-table-column>
              <el-table-column label="" width="120" align="right">
                <template #default="{ row }">
                  <div class="item-amount">¥ {{ formatMoney(row.amount) }}</div>
                  <div v-if="row.paid_amount > 0" class="item-paid-amount">已付 ¥ {{ formatMoney(row.paid_amount) }}</div>
                </template>
              </el-table-column>
              <el-table-column label="" width="140" align="center">
                <template #default="{ row }">
                  <div class="item-date">{{ formatDate(row.planned_date) }}</div>
                  <div v-if="row.status === 'pending'" class="item-due" :class="dueClass(row)">
                    <template v-if="row.days_left !== null">
                      <template v-if="row.days_left < 0">已超 {{ -row.days_left }} 天</template>
                      <template v-else-if="row.days_left === 0">今天到期</template>
                      <template v-else>还有 {{ row.days_left }} 天</template>
                    </template>
                  </div>
                  <div v-else-if="row.actual_date" class="item-due">已于 {{ formatDate(row.actual_date) }} 付清</div>
                </template>
              </el-table-column>
              <el-table-column label="" width="100" align="center">
                <template #default="{ row }">
                  <el-tag :type="statusTagType(row)" size="small" effect="dark">{{ statusLabel(row) }}</el-tag>
                </template>
              </el-table-column>
            </el-table>
          </el-card>
        </el-timeline-item>
      </el-timeline>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { CalendarItem } from "./types"
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { get } from '@/utils/request'
import { Refresh, List, CircleCheck, Clock, Warning, CircleClose, CirclePlus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

const props = withDefaults(defineProps<{
  projectId?: number
  embedded?: boolean
}>(), { projectId: 0, embedded: false })

const router = useRouter()
const summary = ref<Record<string, number | string>>({ total_count: 0, total_amount: 0, paid_amount: 0, pending_amount: 0, overdue_count: 0, overdue_amount: 0, soon_count: 0 })
const calendar = ref<CalendarItem[]>([])
const items = ref<CalendarItem[]>([])
const filterStatus = ref<string>('')

// 按项目过滤
const filteredCalendar = computed(() => {
  if (!props.projectId) return calendar.value
  return calendar.value.filter(m => {
    const projectItems = m.items?.filter((i: Record<string, unknown>) => Number(i.project_id) === props.projectId) || []
    return projectItems.length > 0
  }).map(m => ({
    ...m,
    items: m.items?.filter((i: Record<string, unknown>) => Number(i.project_id) === props.projectId) || [],
    count: m.items?.filter((i: Record<string, unknown>) => Number(i.project_id) === props.projectId).length || 0,
  }))
})
const filteredItems = computed(() => {
  if (!props.projectId) return items.value
  return items.value.filter((i: Record<string, unknown>) => Number(i.project_id) === props.projectId)
})
const filteredSummary = computed(() => {
  if (!props.projectId) return summary.value
  const pItems = filteredItems.value
  return {
    total_count: pItems.length,
    total_amount: pItems.reduce((s: number, i: Record<string, unknown>) => s + (Number(i.amount) || 0), 0),
    paid_amount: pItems.filter((i: Record<string, unknown>) => i.status === 'paid').reduce((s: number, i: Record<string, unknown>) => s + (Number(i.amount) || 0), 0),
    pending_amount: pItems.filter((i: Record<string, unknown>) => i.status === 'pending').reduce((s: number, i: Record<string, unknown>) => s + (Number(i.amount) || 0), 0),
    overdue_count: pItems.filter((i: Record<string, unknown>) => i.status === 'overdue').length,
    overdue_amount: pItems.filter((i: Record<string, unknown>) => i.status === 'overdue').reduce((s: number, i: Record<string, unknown>) => s + (Number(i.amount) || 0), 0),
    soon_count: 0,
  }
})

const paymentRate = computed(() => {
  if (!filteredSummary.value.total_amount) return 0
  return ((Number(filteredSummary.value.paid_amount) / Number(filteredSummary.value.total_amount)) * 100).toFixed(1)
})

const loadList = async () => {
  try {
    const params: Record<string, unknown> = { per_page: 500 }
    if (filterStatus.value) params.status = filterStatus.value
    const r = await get('/projects/payment-calendar', params)
    // V0.6.3: res = {code, data: <calendar>}
    const d = r?.data ?? r ?? {}
    summary.value = d?.summary || summary.value
    calendar.value = d?.by_month || []
    items.value = d?.items || []
  } catch (e) {
    ElMessage.error('加载付款日历失败')
  }
}

const formatMoney = (n: number) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const formatDate = (d: string) => d ? d.slice(0, 10) : '-'
const monthLabel = (m: string) => {
  if (!m) return ''
  const [y, mm] = m.split('-')
  return `${y} 年 ${parseInt(mm)} 月`
}

const statusIcon = (row: CalendarItem) => {
  if (row.status === 'paid') return CircleCheck
  if (row.is_overdue) return CircleClose
  if (row.is_soon) return Warning
  return Clock
}
const statusColor = (row: CalendarItem) => {
  if (row.status === 'paid') return '#1D9E75'
  if (row.is_overdue) return '#A32D2D'
  if (row.is_soon) return '#BA7517'
  return '#909399'
}
const statusLabel = (row: CalendarItem) => {
  if (row.status === 'paid') return '已付'
  if (row.is_overdue) return '逾期'
  if (row.is_soon) return '即将到期'
  return '待付'
}
const statusTagType = (row: CalendarItem): string => {
  if (row.status === 'paid') return 'success'
  if (row.is_overdue) return 'danger'
  if (row.is_soon) return 'warning'
  return 'info'
}
const dueClass = (row: CalendarItem) => {
  if (row.is_overdue) return 'overdue'
  if (row.is_soon) return 'soon'
  return ''
}
const goProject = (pid: number) => { if (pid) router.push(`/project/detail/${pid}`) }

onMounted(() => {
  loadList()
})
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 14px 20px;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .title-area { display: flex; align-items: center; gap: 10px; }
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}

.summary-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 12px;
}
.summary-card {
  background: #fff;
  border-left: 4px solid;
  border-radius: 6px;
  padding: 14px 18px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .sum-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
  .sum-value { font-size: 22px; font-weight: 700; color: #0C447C; }
  .sum-extra { font-size: 11px; color: #909399; margin-top: 2px; }
}

.content-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.month-card {
  margin-bottom: 8px;
  .month-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    .month-title { font-size: 16px; font-weight: 600; color: #0C447C; }
    .month-amount { font-size: 16px; font-weight: 600; color: #0C447C; }
    .month-paid { font-size: 12px; color: #1D9E75; margin-left: 8px; font-weight: normal; }
  }
  :deep(.el-table) { border-radius: 4px; }
}

.item-name { font-size: 13px; font-weight: 500; color: #303133; }
.item-sub { font-size: 11px; color: #909399; margin-top: 2px; }
.item-project { color: #0C447C; cursor: pointer; &:hover { text-decoration: underline; } }
.item-customer { color: #909399; }
.item-amount { font-size: 13px; font-weight: 600; color: #303133; }
.item-paid-amount { font-size: 11px; color: #1D9E75; }
.item-date { font-size: 13px; color: #303133; }
.item-due { font-size: 11px; margin-top: 2px; &.overdue { color: #A32D2D; font-weight: 600; } &.soon { color: #BA7517; font-weight: 600; } }
</style>
