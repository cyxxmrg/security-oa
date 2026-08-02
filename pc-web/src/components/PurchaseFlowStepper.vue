<template>
  <div class="purchase-flow-stepper">
    <div class="stepper-title">
      <el-icon><Connection /></el-icon>
      <span>采购协同 8 步流转</span>
      <el-tag v-if="currentLabel" type="info" size="small" effect="plain">
        当前：{{ currentLabel }}
      </el-tag>
    </div>
    <div class="stepper-track">
      <div
        v-for="(s, i) in steps"
        :key="s.key"
        class="step"
        :class="{
          done: i < currentIndex,
          active: i === currentIndex,
          todo: i > currentIndex,
        }"
      >
        <div class="step-circle">
          <el-icon v-if="i < currentIndex"><Check /></el-icon>
          <el-icon v-else><component :is="s.icon" /></el-icon>
        </div>
        <div class="step-label">{{ s.label }}</div>
        <div v-if="i < steps.length - 1" class="step-line" :class="{ filled: i < currentIndex }" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Connection, Check, List, Files, ChatLineSquare, Document, Tickets, Money, Box, House } from '@element-plus/icons-vue'
import { PURCHASE_STEPS, inferCurrentStep, type PurchaseStepKey } from '@/api/purchase-flow'

const props = withDefaults(
  defineProps<{
    /** 当前实体类型 (requirement/plan/order/contract/...) */
    entityType?: string
    /** 当前实体状态 (pending/approved/fulfilled/...) */
    entityStatus?: string
  }>(),
  { entityType: 'requirement', entityStatus: 'pending' }
)

const steps = PURCHASE_STEPS

// 8 步当前在哪一步
const currentIndex = computed(() => {
  const key = inferCurrentStep(props.entityType)
  const idx = steps.findIndex((s) => s.key === key)
  return idx === -1 ? 0 : idx
})

const currentLabel = computed(() => steps[currentIndex.value]?.label)
</script>

<style scoped lang="scss">
.purchase-flow-stepper {
  background: linear-gradient(135deg, #f6f8fc 0%, #eef2f8 100%);
  border: 1px solid #e4e7ed;
  border-radius: 10px;
  padding: 14px 18px 10px;
  margin-bottom: 12px;
}

.stepper-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #303133;
  font-weight: 600;
  margin-bottom: 10px;
  .el-icon { color: #409eff; }
  .el-tag { margin-left: 8px; }
}

.stepper-track {
  display: flex;
  align-items: flex-start;
  gap: 0;
}

.step {
  position: relative;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 0;
}

.step-circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid #dcdfe6;
  color: #909399;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  z-index: 1;
  transition: all 0.25s;
}

.step.done .step-circle {
  background: #67c23a;
  border-color: #67c23a;
  color: #fff;
}

.step.active .step-circle {
  background: #409eff;
  border-color: #409eff;
  color: #fff;
  box-shadow: 0 0 0 4px rgba(64, 158, 255, 0.15);
}

.step-label {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  white-space: nowrap;
  text-align: center;
}

.step.done .step-label { color: #67c23a; }
.step.active .step-label { color: #409eff; font-weight: 600; }

.step-line {
  position: absolute;
  top: 14px;
  left: 50%;
  right: -50%;
  height: 2px;
  background: #dcdfe6;
  z-index: 0;
}

.step-line.filled {
  background: #67c23a;
}
</style>
