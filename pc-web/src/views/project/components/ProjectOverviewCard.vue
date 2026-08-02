<template>
  <div class="overview-card">
    <div v-if="risks.length > 0" class="risk-banner">
      <div class="risk-banner-head">
        <el-icon :size="18" color="#A32D2D"><WarningFilled /></el-icon>
        <span class="risk-banner-title">项目风险预警</span>
        <el-tag type="danger" size="small" effect="dark">共 {{ risks.length }} 项</el-tag>
      </div>
      <div class="risk-banner-list">
        <div v-for="(r, i) in risks" :key="i" class="risk-item" :class="'risk-' + r.level">
          <el-icon :size="14" :color="r.level === 'danger' ? '#A32D2D' : '#BA7517'">
            <component :is="r.level === 'danger' ? CircleClose : WarningFilled" />
          </el-icon>
          <span class="risk-title">{{ r.title }}</span>
          <span class="risk-desc">{{ r.desc }}</span>
          <el-button
            :type="r.level === 'danger' ? 'danger' : 'warning'"
            size="small"
            plain
            style="margin-left: auto"
            @click="$emit('risk-action', r)"
          >
            {{ riskActionLabel(r.type) }}
          </el-button>
        </div>
      </div>
    </div>

    <el-row :gutter="0">
      <el-col :span="6" class="overview-item">
        <div class="ov-label">项目编号</div>
        <div class="ov-value">{{ project.project_no || project.code || '-' }}</div>
      </el-col>
      <el-col :span="6" class="overview-item">
        <div class="ov-label">所属客户</div>
        <div class="ov-value">{{ customerName }}</div>
      </el-col>
      <el-col :span="6" class="overview-item">
        <div class="ov-label">合同金额</div>
        <div class="ov-value highlight">¥ {{ totalBudget }} 万</div>
      </el-col>
      <el-col :span="6" class="overview-item">
        <div class="ov-label">已回款 ({{ paymentRate }}%)</div>
        <div class="ov-value" :style="{ color: paymentRate >= 50 ? '#1D9E75' : '#BA7517' }">
          ¥ {{ paidAmount }} 万
          <el-tag v-if="paymentOverdueCount > 0" type="danger" size="small" effect="dark" style="margin-left: 6px">
            {{ paymentOverdueCount }} 逾期
          </el-tag>
        </div>
      </el-col>
    </el-row>
    <el-row :gutter="0">
      <el-col :span="6" class="overview-item">
        <div class="ov-label">物料到位率</div>
        <div class="ov-value" :style="{ color: fulfillRate >= 80 ? '#1D9E75' : fulfillRate >= 50 ? '#BA7517' : '#A32D2D' }">
          {{ fulfillRate }}%
        </div>
      </el-col>
      <el-col :span="6" class="overview-item">
        <div class="ov-label">当前阶段</div>
        <div class="ov-value">
          <el-tag :type="stageTagType(stageLabel)" effect="dark">{{ stageLabel }}</el-tag>
        </div>
      </el-col>
      <el-col :span="6" class="overview-item">
        <div class="ov-label">项目经理</div>
        <div class="ov-value small">
          <el-avatar :size="24" style="background: #0C447C">{{ managerName.charAt(0) }}</el-avatar>
          {{ managerName }}
        </div>
      </el-col>
      <el-col :span="6" class="overview-item">
        <div class="ov-label">团队人数</div>
        <div class="ov-value">{{ (project.members || []).length }} 人</div>
      </el-col>
    </el-row>
    <el-row :gutter="0">
      <el-col :span="6" class="overview-item">
        <div class="ov-label">计划工期</div>
        <div class="ov-value small">{{ formatDate(project.start_date) }} ~ {{ formatDate(project.end_date) }}</div>
      </el-col>
      <el-col :span="6" class="overview-item">
        <div class="ov-label">完成进度</div>
        <div class="ov-value">
          <el-progress :percentage="displayProgress" :status="progressStatus" :stroke-width="12" />
        </div>
      </el-col>
      <el-col :span="6" class="overview-item">
        <div class="ov-label">采购计划</div>
        <div class="ov-value small">
          总 {{ tracking.purchase_stats.total_orders }} · 已完成 {{ tracking.purchase_stats.completed_orders }}
        </div>
      </el-col>
      <el-col :span="6" class="overview-item">
        <div class="ov-label">物料领用</div>
        <div class="ov-value small">{{ tracking.material_stats.issued_records || 0 }} 笔</div>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { WarningFilled, CircleClose } from '@element-plus/icons-vue'
import type { TagType, Risk, Project, Tracking } from '../types'

// V1.2.11 本地辅助函数（历史原因已从 types.ts 移除）
const getCustomerName = (p: Project): string => p.customer?.name || '-'
const getManagerName = (p: Project): string => p.manager?.name || '-'
const computeTotalBudgetWan = (p: Project): string => {
  // V1.2.12k: 合同金额以 project_contracts 求和 (后端 accessor contract_amount) 为准
  const v = Number(p.contract_amount || 0) + Number(p.total_amount || 0)
  if (v > 0) return (v / 10000).toFixed(2)
  return '0.00'
}
const STAGE_LABEL_MAP: Record<string, string> = {
  mobilization: '进场准备', construction: '现场施工',
  acceptance: '验收交付', settlement: '项目结算',
  warranty: '售后质保', closed: '已关闭',
}
const formatDate = (s: string | null | undefined) => s ? s.slice(0, 10) : '-'
const riskActionLabel = (r: Risk): string => {
  if (r.is_mitigated) return '已缓解'
  if (r.is_closed) return '已关闭'
  return '处理'
}

const props = defineProps<{
  project: Project
  tracking: Tracking
}>()

defineEmits<{
  'risk-action': [risk: Risk]
}>()

const customerName = computed(() => getCustomerName(props.project))
const managerName = computed(() => getManagerName(props.project))
const totalBudget = computed(() => computeTotalBudgetWan(props.project))
const paidAmount = computed(() => (Number(props.tracking.payment?.paid_amount) / 10000 || 0).toFixed(2))
const paymentRate = computed(() => Number(props.tracking.payment?.payment_rate) || 0)
const paymentOverdueCount = computed(() => Number(props.tracking.payment?.overdue_count) || 0)
const displayProgress = computed(() => Number(props.tracking.display_progress) || Number(props.project.progress) || 0)
const fulfillRate = computed(() => Number(props.tracking.purchase_stats?.fulfill_rate) || 0)
const risks = computed(() => props.tracking.risks || [])
const stageLabel = computed(() => STAGE_LABEL_MAP[props.project.stage || ''] || props.project.stage || '-')

const progressStatus = computed((): 'success' | 'warning' | 'exception' => {
  if (props.project.status === 'completed') return 'success'
  if (props.project.status === 'suspended') return 'exception'
  if (displayProgress.value >= 80) return 'success'
  if (displayProgress.value >= 50) return 'warning'
  return 'success'
})

const stageTagType = (s?: string): TagType => {
  const map: Record<string, TagType> = {
    立项: 'primary', 询价: 'info', 合同: 'warning', 采购: 'warning',
    施工: 'danger', 结算: 'success', 质保: 'success',
  }
  return map[s || ''] || 'info'
}
</script>

<style lang="scss" scoped>
.overview-card {
  background: #fff;
  border-radius: 8px;
  padding: 0 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  margin-bottom: 12px;
  overflow: hidden;
}
.overview-item {
  padding: 16px 8px;
  border-bottom: 1px solid #f0f0f0;
}
.ov-label {
  font-size: 12px;
  color: #909399;
  margin-bottom: 4px;
}
.ov-value {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  display: flex;
  align-items: center;
  gap: 8px;
}
.ov-value.small {
  font-size: 14px;
  font-weight: 500;
}
.ov-value.highlight {
  color: #0C447C;
  font-size: 20px;
}
.risk-banner {
  background: linear-gradient(90deg, #fdecec 0%, #fff5e6 100%);
  border-left: 4px solid #A32D2D;
  padding: 12px 16px;
  margin: 0 -20px 8px -20px;
  border-bottom: 1px solid #f0d5d5;
}
.risk-banner-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}
.risk-banner-title {
  font-weight: 600;
  color: #A32D2D;
}
.risk-banner-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.risk-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  background: rgba(255,255,255,0.6);
  border-radius: 4px;
  font-size: 13px;
}
.risk-title {
  font-weight: 600;
  color: #303133;
}
.risk-desc {
  color: #606266;
  font-size: 12px;
}
</style>
