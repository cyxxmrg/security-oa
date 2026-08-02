<template>
  <el-card class="form-card">
    <template #header>
      <div class="card-header">
        <el-icon color="#0C447C"><Check /></el-icon>
        <span>项目信息确认</span>
      </div>
    </template>
    <el-descriptions title="项目基础信息" :column="2" border>
      <el-descriptions-item label="项目名称">{{ form.name || '—' }}</el-descriptions-item>
      <el-descriptions-item label="项目编号">{{ form.code || '提交后自动生成' }}</el-descriptions-item>
      <el-descriptions-item label="所属客户">{{ form.customer || '—' }}</el-descriptions-item>
      <el-descriptions-item label="项目类型">
        <el-tag size="small">{{ form.type }}</el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="项目地点">{{ form.location || '—' }}</el-descriptions-item>
      <el-descriptions-item label="合同金额">{{ form.amount }} 万元</el-descriptions-item>
      <el-descriptions-item label="计划开始">{{ form.startDate || '—' }}</el-descriptions-item>
      <el-descriptions-item label="计划完成">{{ form.endDate || '—' }}</el-descriptions-item>
      <el-descriptions-item label="项目经理">{{ form.manager || '—' }}</el-descriptions-item>
      <el-descriptions-item label="团队人数">{{ form.team.length }} 人</el-descriptions-item>
      <el-descriptions-item label="项目预算" :span="2">
        <span class="total-amount">¥ {{ formatMoney(totalBudget * 10000) }}</span>
      </el-descriptions-item>
      <el-descriptions-item label="项目描述" :span="2">{{ form.description || '—' }}</el-descriptions-item>
    </el-descriptions>

    <el-alert
      type="info"
      :closable="false"
      show-icon
      class="confirm-tips"
    >
      <template #title>
        提交后将进入立项审批流程，预计 1-2 个工作日完成审批。
      </template>
    </el-alert>
  </el-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Check } from '@element-plus/icons-vue'
import type { ProjectForm } from '../createTypes'
import { formatMoney } from '../createTypes'

const props = defineProps<{ form: ProjectForm }>()

const totalBudget = computed(() => {
  const b = props.form.budget
  return (
    b.equipment.reduce((s, e) => s + e.qty * e.price, 0) +
    b.material.reduce((s, m) => s + m.qty * m.price, 0) +
    b.labor.reduce((s, l) => s + l.qty * l.days * l.dailyRate, 0) +
    b.outsource.reduce((s, o) => s + o.amount, 0) +
    b.other.reduce((s, o) => s + o.amount, 0)
  ) / 10000
})
</script>

<style scoped>
.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}
.total-amount {
  color: #0C447C;
  font-size: 18px;
  font-weight: 700;
}
.confirm-tips {
  margin-top: 16px;
}
</style>
