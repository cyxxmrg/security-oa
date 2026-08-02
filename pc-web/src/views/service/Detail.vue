<template>
  <div class="page-container">
    <div class="page-header">
      <h2>工单详情</h2>
      <el-button @click="goBack">返回列表</el-button>
    </div>
    <div class="content-card" v-loading="loading">
      <template v-if="order">
        <div class="section-title">基本信息</div>
        <el-descriptions :column="3" border>
          <el-descriptions-item label="工单号">{{ order.order_no }}</el-descriptions-item>
          <el-descriptions-item label="客户">{{ order.customer?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="设备">
            {{ order.device?.name || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="关联项目" :span="2">
            {{ order.project?.name || '无' }}
          </el-descriptions-item>
          <el-descriptions-item label="紧急程度">
            <el-tag :type="urgencyTagType(order.urgency)" effect="dark" size="small">{{ urgencyLabel(order.urgency) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="维修类型" :span="2">{{ serviceTypeLabel(order.service_type) }}</el-descriptions-item>
          <el-descriptions-item label="SLA 时限" >{{ order.sla_hours || '-' }} 小时</el-descriptions-item>
          <el-descriptions-item label="维修人员" >
            <span v-if="order.assignedUser">{{ order.assignedUser.name }}</span>
            <span v-else class="muted">未派单</span>
          </el-descriptions-item>
          <el-descriptions-item label="状态" :span="3">
            <el-tag :type="statusTagType(order.status)">{{ statusLabel(order.status) }}</el-tag>
            <span class="status-time" v-if="order.assigned_at">派单于 {{ formatDate(order.assigned_at) }}</span>
            <span class="status-time" v-if="order.started_at"> · 开始于 {{ formatDate(order.started_at) }}</span>
            <span class="status-time" v-if="order.completed_at"> · 完成于 {{ formatDate(order.completed_at) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="故障描述" :span="3">
            <div style="white-space: pre-wrap;">{{ order.fault_description }}</div>
          </el-descriptions-item>
        </el-descriptions>

        <el-tabs v-model="activeTab" class="detail-tabs">
          <el-tab-pane label="维修进度" name="progress">
            <el-timeline v-if="order.logs && order.logs.length">
              <el-timeline-item
                v-for="(step, idx) in order.logs"
                :key="idx"
                :timestamp="formatDate(step.created_at)"
                :type="logType(step.action)"
                placement="top"
              >
                <div class="step-title">{{ logActionLabel(step.action) }}</div>
                <div class="step-desc">{{ step.content }}</div>
                <div class="step-user">操作人：{{ step.user?.name || '-' }}</div>
              </el-timeline-item>
            </el-timeline>
            <el-empty v-else description="暂无进度日志" :image-size="80" />
          </el-tab-pane>

          <el-tab-pane label="更换备件" name="parts">
            <el-table v-if="order.parts && order.parts.length" :data="order.parts" stripe border>
              <el-table-column prop="part_name" label="备件名称" min-width="200" />
              <el-table-column prop="quantity" label="数量" width="100" align="center" />
              <el-table-column label="单价" width="120" align="right">
                <template #default="{ row }">¥{{ Number(row.unit_cost || 0).toFixed(2) }}</template>
              </el-table-column>
              <el-table-column label="小计" width="120" align="right">
                <template #default="{ row }">
                  <span style="color:#0C447C;font-weight:500;">¥{{ Number(row.total_cost || 0).toFixed(2) }}</span>
                </template>
              </el-table-column>
            </el-table>
            <el-empty v-else description="未使用备件" :image-size="80" />
          </el-tab-pane>

          <el-tab-pane label="客户评价" name="evaluation">
            <div v-if="order.rating || order.review" class="eval-card">
              <div class="eval-row">
                <span class="eval-label">服务评分：</span>
                <el-rate v-model="order.rating" disabled show-score :max="5" />
              </div>
              <div class="eval-row" v-if="order.confirmed_at">
                <span class="eval-label">确认时间：</span>
                <span>{{ formatDate(order.confirmed_at) }}</span>
              </div>
              <div class="eval-row" v-if="order.review">
                <span class="eval-label">客户评论：</span>
              </div>
              <div class="eval-comment" v-if="order.review">{{ order.review }}</div>
            </div>
            <el-empty v-else description="客户暂未评价" :image-size="80" />
          </el-tab-pane>
        </el-tabs>
      </template>
      <el-empty v-else-if="!loading" description="工单不存在" :image-size="100" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get, post } from '@/utils/request'
import { ElMessage, ElMessageBox } from 'element-plus'

const route = useRoute()
const router = useRouter()
const order = ref<Record<string, unknown> | null>(null)
const loading = ref(false)
const activeTab = ref('progress')

const statusOptions = [
  { value: 'pending',     label: '待处理' },
  { value: 'assigned',    label: '已派单' },
  { value: 'in_progress', label: '维修中' },
  { value: 'completed',   label: '待确认' },
  { value: 'confirmed',   label: '已完成' },
]
const urgencyOptions = [
  { value: 'normal',   label: '普通' },
  { value: 'urgent',   label: '紧急' },
  { value: 'critical', label: '特急' },
]
const serviceTypeMap: Record<string, string> = {
  warranty:         '质保期内',
  out_of_warranty:  '质保期外',
  contract:         '维保合同',
  paid:             '付费维修',
}

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

const statusLabel = (s: string) => statusOptions.find(o => o.value === s)?.label || s
const urgencyLabel = (u: string) => urgencyOptions.find(o => o.value === u)?.label || u
const serviceTypeLabel = (s: string) => serviceTypeMap[s] || s

const statusTagType = (s: string): TagType => {
  const map: Record<string, TagType> = {
    pending:     'danger',
    assigned:    'warning',
    in_progress: 'primary',
    completed:   'info',
    confirmed:   'success',
  }
  return map[s] || 'info'
}
const urgencyTagType = (u: string): TagType => {
  const map: Record<string, TagType> = {
    normal:   'info',
    urgent:   'warning',
    critical: 'danger',
  }
  return map[u] || 'info'
}
const logType = (action: string): TagType => {
  const map: Record<string, TagType> = {
    created:  'primary',
    assigned: 'warning',
    started:  'primary',
    completed:'success',
    confirmed:'success',
  }
  return map[action] || 'info'
}
const logActionLabel = (action: string) => {
  const map: Record<string, string> = {
    created:   '工单创建',
    assigned:  '工单派发',
    started:   '开始维修',
    completed: '维修完成',
    confirmed: '客户确认',
  }
  return map[action] || action
}

const formatDate = (s?: string) => {
  if (!s) return '-'
  const d = new Date(s)
  if (isNaN(d.getTime())) return s
  const pad = (n: number) => n.toString().padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

async function loadOrder() {
  const id = route.params.id
  if (!id) { ElMessage.error('缺少工单 ID'); return }
  loading.value = true
  try {
    const res = await get(`/service/orders/${id}`)
    order.value = res.data || res
  } catch (e: unknown) {
    ElMessage.error(e?.response?.data?.message || e.message || '工单不存在')
    order.value = null
  } finally {
    loading.value = false
  }
}

const goBack = () => { router.push('/service') }

onMounted(loadOrder)
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 16px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
}
.content-card {
  background: #fff; border-radius: 8px; padding: 24px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.section-title {
  font-size: 16px; font-weight: 600; color: #0C447C;
  margin-bottom: 12px; padding-left: 10px;
  border-left: 3px solid #0C447C;
}
.detail-tabs { margin-top: 20px; }
.step-title { font-weight: 600; color: #333; }
.step-desc  { font-size: 13px; color: #666; margin-top: 4px; }
.step-user  { font-size: 12px; color: #999; margin-top: 4px; }
.status-time { margin-left: 12px; font-size: 12px; color: #909399; }
.muted { color: #c0c4cc; }
.eval-card {
  padding: 20px; background: #f9fafb; border-radius: 8px;
}
.eval-row {
  display: flex; align-items: center; margin-bottom: 12px;
}
.eval-label {
  font-weight: 600; color: #333; width: 100px;
}
.eval-comment {
  padding: 12px; background: #fff; border-radius: 6px;
  border: 1px solid #e4e7ed; color: #555; line-height: 1.6;
}
</style>
