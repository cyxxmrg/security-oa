<script setup lang="ts">
/**
 * 跟进日历 KPI 卡 - 4 张统计 + 完成率
 */
import { computed } from 'vue'
import { DataLine, CircleCheckFilled, Clock, WarningFilled } from '@element-plus/icons-vue'

defineProps<{
  summary: {
    total: number
    completed: number
    planned: number
    overdue: number
  }
}>()

const _ = DataLine
</script>

<template>
  <div class="kpi-row">
    <div class="kpi-card glass">
      <div class="kpi-top">
        <div>
          <div class="kpi-label">总跟进数</div>
          <div class="kpi-value gradient-blue">{{ summary.total || 0 }}</div>
        </div>
        <div class="kpi-icon" style="background: rgba(12, 68, 124, 0.12);">
          <el-icon :size="22" color="#0C447C"><DataLine /></el-icon>
        </div>
      </div>
      <div class="kpi-foot">本月</div>
    </div>

    <div class="kpi-card glass">
      <div class="kpi-top">
        <div>
          <div class="kpi-label">已完成</div>
          <div class="kpi-value gradient-green">{{ summary.completed || 0 }}</div>
        </div>
        <div class="kpi-icon" style="background: rgba(29, 158, 117, 0.12);">
          <el-icon :size="22" color="#1D9E75"><CircleCheckFilled /></el-icon>
        </div>
      </div>
      <div class="kpi-foot">完成率 {{ completionRate }}%</div>
    </div>

    <div class="kpi-card glass">
      <div class="kpi-top">
        <div>
          <div class="kpi-label">计划中</div>
          <div class="kpi-value gradient-purple">{{ summary.planned || 0 }}</div>
        </div>
        <div class="kpi-icon" style="background: rgba(83, 74, 183, 0.12);">
          <el-icon :size="22" color="#534AB7"><Clock /></el-icon>
        </div>
      </div>
      <div class="kpi-foot">未来待执行</div>
    </div>

    <div class="kpi-card glass" :class="{ 'is-warn': (summary.overdue || 0) > 0 }">
      <div class="kpi-top">
        <div>
          <div class="kpi-label">逾期</div>
          <div class="kpi-value gradient-red">{{ summary.overdue || 0 }}</div>
        </div>
        <div class="kpi-icon" style="background: rgba(163, 45, 45, 0.12);">
          <el-icon :size="22" color="#A32D2D"><WarningFilled /></el-icon>
        </div>
      </div>
      <div class="kpi-foot">需立即处理</div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}
.kpi-card {
  padding: 18px 20px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.5);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  }

  &.is-warn {
    border-color: rgba(163, 45, 45, 0.3);
    background: linear-gradient(135deg, rgba(255, 245, 245, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
  }
}
.kpi-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.kpi-label {
  font-size: 12px;
  color: #606266;
  margin-bottom: 4px;
}
.kpi-value {
  font-size: 28px;
  font-weight: 700;
  line-height: 1.2;
}
.kpi-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.kpi-foot {
  margin-top: 8px;
  font-size: 12px;
  color: #909399;
}
.gradient-blue {
  background: linear-gradient(135deg, #0C447C, #1A6FBF);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.gradient-green {
  background: linear-gradient(135deg, #1D9E75, #2DBE92);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.gradient-purple {
  background: linear-gradient(135deg, #534AB7, #7A6FE5);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.gradient-red {
  background: linear-gradient(135deg, #A32D2D, #D14545);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
</style>
