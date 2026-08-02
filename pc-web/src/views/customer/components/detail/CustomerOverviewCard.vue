<template>
  <div class="overview-card" v-loading="loading">
    <div class="overview-left">
      <el-avatar :size="80" :style="{ background: avatarColor(customer.id) }">
        {{ (customer.name || '?').charAt(0) }}
      </el-avatar>
      <div class="overview-info">
        <div class="name-row">
          <span class="name">{{ customer.name }}</span>
          <span class="code">{{ customer.credit_code || '—' }}</span>
        </div>
        <div class="meta-row">
          <el-icon><Location /></el-icon>
          <span>{{ addressText }}</span>
        </div>
        <div class="meta-row">
          <el-tag
            v-for="t in (customer.tags || [])"
            :key="t"
            type="info"
            effect="plain"
            size="small"
            class="tag-item"
          >
            {{ t }}
          </el-tag>
        </div>
      </div>
    </div>
    <div class="overview-stats">
      <div class="stat">
        <div class="num">{{ projectsCount }}</div>
        <div class="label">项目数</div>
      </div>
      <div class="stat">
        <div class="num">{{ (customer.receivables || []).length }}</div>
        <div class="label">应收款</div>
      </div>
      <div class="stat">
        <div class="num">{{ (customer.devices || []).length }}</div>
        <div class="label">设备数</div>
      </div>
      <div class="stat">
        <div class="num">{{ (customer.service_orders || []).length }}</div>
        <div class="label">售后单</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Location } from '@element-plus/icons-vue'
import type { Customer } from './types'
import { avatarColor, displayCategory, categoryType } from './types'

// v0.3.20 抽自 customer/Detail.vue:38-77
const props = defineProps<{
  customer: Customer
  loading: boolean
}>()

const projectsCount = computed(() => (props.customer.projects || []).length)

const addressText = computed(() => {
  const parts = [props.customer.province, props.customer.city, props.customer.district, props.customer.address]
  return parts.filter(Boolean).join(' ') || '—'
})
</script>

<style lang="scss" scoped>
.overview-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 12px;
  display: flex;
  justify-content: space-between;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.overview-left {
  display: flex;
  gap: 20px;
  align-items: center;
}
.overview-info {
  .name-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
  }
  .name { font-size: 20px; font-weight: 600; color: #303133; }
  .code { color: #909399; font-size: 12px; }
  .meta-row {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #606266;
    font-size: 13px;
    margin-top: 6px;
    .tag-item { margin-right: 4px; }
  }
}
.overview-stats {
  display: flex;
  gap: 24px;
  align-items: center;
  .stat {
    text-align: center;
    padding: 0 16px;
    .num {
      font-size: 26px;
      font-weight: 600;
      color: #0C447C;
      line-height: 1.2;
    }
    .label {
      font-size: 12px;
      color: #909399;
      margin-top: 4px;
    }
  }
}
</style>
