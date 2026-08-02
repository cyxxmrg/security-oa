<template>
  <div class="tab-content">
    <h3 class="section-title">项目基础信息</h3>
    <el-descriptions :column="3" border>
      <el-descriptions-item label="项目编号">{{ project.project_no || project.code || '-' }}</el-descriptions-item>
      <el-descriptions-item label="项目名称">{{ project.name || '-' }}</el-descriptions-item>
      <el-descriptions-item label="项目类型">
        <el-tag size="small">{{ typeLabel(project.type) }}</el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="所属客户">
        <span class="link-text">{{ customerName }}</span>
      </el-descriptions-item>
      <el-descriptions-item label="项目地点" :span="2">{{ project.address || project.location || '-' }}</el-descriptions-item>
      <el-descriptions-item label="合同编号">{{ contractNo || '-' }}</el-descriptions-item>
      <el-descriptions-item label="合同金额">¥ {{ totalBudget }} 万元</el-descriptions-item>
      <el-descriptions-item label="已付款">¥ {{ paidAmount }} 万元</el-descriptions-item>
      <el-descriptions-item label="项目经理">{{ managerName }}</el-descriptions-item>
      <el-descriptions-item label="团队人数">{{ (project.members || []).length }} 人</el-descriptions-item>
      <el-descriptions-item label="计划开始">{{ formatDate(project.start_date) }}</el-descriptions-item>
      <el-descriptions-item label="计划完成">{{ formatDate(project.end_date) }}</el-descriptions-item>
      <el-descriptions-item label="实际开始">{{ formatDate(project.actual_start) || '-' }}</el-descriptions-item>
      <el-descriptions-item label="优先级">
        <el-rate v-model="priorityValue" disabled size="small" />
      </el-descriptions-item>
      <el-descriptions-item label="项目描述" :span="3">{{ project.description || '-' }}</el-descriptions-item>
    </el-descriptions>

    <h3 class="section-title">团队成员 ({{ (project.members || []).length }} 人)</h3>
    <div v-if="(project.members || []).length > 0" class="team-grid">
      <div v-for="m in project.members" :key="m.id" class="team-card">
        <el-avatar :size="40" style="background: #0C447C">{{ (m.name || '?').charAt(0) }}</el-avatar>
        <div class="team-info">
          <div class="team-name">{{ m.name || '-' }}</div>
          <div class="team-role">{{ m.pivot?.role || '成员' }}</div>
        </div>
      </div>
    </div>
    <el-empty v-else description="暂无团队成员" :image-size="60" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { type Project } from '../types'

// V1.2.11 本地辅助函数（历史原因已从 types.ts 移除）
const getCustomerName = (p: Project): string => p.customer?.name || '-'
const getManagerName = (p: Project): string => p.manager?.name || '-'
const computeTotalBudgetWan = (p: Project): string => {
  // V1.2.12k: 合同金额以 project_contracts 求和 (后端 accessor contract_amount) 为准
  // 兜底: total_amount (项目预算) / contract_amount (旧字段)
  const v = Number(p.contract_amount || 0) + Number(p.total_amount || 0)
  if (v > 0) return (v / 10000).toFixed(2)
  return '0.00'
}
const typeLabel = (t: string) => ({
  camera: '监控', access_control: '门禁', alarm: '报警',
  comprehensive: '综合', network: '网络', cloud_platform: '云平台',
}[t] || t)
const formatDate = (s: string | null | undefined) => s ? s.slice(0, 10) : '-'

const props = defineProps<{
  project: Project
  paidAmount: string
  contractNo: string
}>()

const customerName = computed(() => getCustomerName(props.project))
const managerName = computed(() => getManagerName(props.project))
const totalBudget = computed(() => computeTotalBudgetWan(props.project))

const priorityValue = computed(() => {
  const map: Record<string, number> = { low: 1, medium: 2, high: 3, urgent: 4, critical: 5 }
  return map[props.project.priority as string] || Number(props.project.priority) || 0
})
</script>

<style scoped>
.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  margin: 0 0 16px 0;
  padding-left: 8px;
  border-left: 3px solid #0C447C;
}
.link-text {
  color: #0C447C;
  cursor: pointer;
}
.team-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
  margin-top: 12px;
}
.team-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 8px;
  transition: all 0.2s;
}
.team-card:hover {
  background: #e6f0fa;
  transform: translateY(-2px);
}
.team-info {
  flex: 1;
  min-width: 0;
}
.team-name {
  font-weight: 600;
  color: #303133;
}
.team-role {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}
</style>
