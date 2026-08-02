<template>
  <div class="flow-stepper">
    <div
      v-for="(step, idx) in PURCHASE_STEPS"
      :key="step.key"
      class="step-item"
      :class="{
        active: idx === currentIdx,
        done: idx < currentIdx,
        pending: idx > currentIdx,
      }"
    >
      <div class="step-circle">
        <el-icon v-if="idx < currentIdx" :size="14"><Check /></el-icon>
        <span v-else>{{ idx + 1 }}</span>
      </div>
      <div class="step-label">{{ step.label }}</div>
      <div v-if="idx < PURCHASE_STEPS.length - 1" class="step-connector" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Check } from '@element-plus/icons-vue'
import { PURCHASE_STEPS, type PurchaseStepKey } from '@/api/purchase-flow'

const props = defineProps<{
  currentStep: PurchaseStepKey
}>()

const currentIdx = computed(() =>
  PURCHASE_STEPS.findIndex((s) => s.key === props.currentStep)
)
</script>

<style lang="scss" scoped>
.flow-stepper {
  display: flex;
  align-items: center;
  padding: 16px 24px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  overflow-x: auto;
}

.step-item {
  display: flex;
  align-items: center;
  flex-shrink: 0;

  .step-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid #dcdfe6;
    background: #f5f7fa;
    color: #909399;
  }

  .step-label {
    margin-left: 8px;
    font-size: 13px;
    color: #909399;
    white-space: nowrap;
    transition: color 0.3s ease;
  }

  .step-connector {
    width: 40px;
    height: 2px;
    background: #dcdfe6;
    margin: 0 8px;
    transition: background 0.3s ease;
  }

  &.done .step-circle {
    background: #0c447c;
    border-color: #0c447c;
    color: #fff;
  }
  &.done .step-label { color: #0c447c; }
  &.done .step-connector { background: #0c447c; }

  &.active .step-circle {
    background: #1d9e75;
    border-color: #1d9e75;
    color: #fff;
    box-shadow: 0 0 0 4px rgba(29, 158, 117, 0.15);
    transform: scale(1.1);
  }
  &.active .step-label {
    color: #1d9e75;
    font-weight: 600;
  }

  &.pending .step-circle {
    opacity: 0.5;
  }
}
</style>
