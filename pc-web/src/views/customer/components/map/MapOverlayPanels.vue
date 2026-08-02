<template>
  <div class="map-overlay-top">
    <el-card shadow="never" class="legend-card">
      <div class="legend-row">
        <span class="legend-item"><span class="dot vip"></span>VIP客户</span>
        <span class="legend-item"><span class="dot normal"></span>普通客户</span>
        <span class="legend-item"><span class="dot potential"></span>潜在客户</span>
      </div>
    </el-card>
  </div>

  <div class="map-overlay-bottom">
    <el-card shadow="hover" class="info-card" v-if="customer">
      <div class="info-head">
        <el-avatar :size="48" :style="{ background: customer.color }">
          {{ customer.name.charAt(0) }}
        </el-avatar>
        <div class="info-title">
          <div class="name">{{ customer.name }}</div>
          <div class="address">
            <el-icon><Location /></el-icon>
            {{ customer.address }}
          </div>
        </div>
      </div>
      <el-descriptions :column="3" size="small" border>
        <el-descriptions-item label="联系人">{{ customer.contact }}</el-descriptions-item>
        <el-descriptions-item label="联系电话">{{ customer.phone }}</el-descriptions-item>
        <el-descriptions-item label="所属行业">{{ customer.industry }}</el-descriptions-item>
        <el-descriptions-item label="客户分类">
          <el-tag :type="categoryType(customer.categoryLabel)" size="small">
            {{ customer.categoryLabel }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="合作项目">{{ customer.projectCount }} 个</el-descriptions-item>
        <el-descriptions-item label="最后跟进">{{ customer.lastFollowAt }}</el-descriptions-item>
      </el-descriptions>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { Location } from '@element-plus/icons-vue'
import type { MapCustomer } from './types'
import { categoryType } from './types'

// v0.3.24 抽自 customer/CustomerMap.vue:99-107 + 170-197
defineProps<{
  customer: MapCustomer | null
}>()
</script>

<style lang="scss" scoped>
.map-overlay-top {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 10;
}
.legend-card { padding: 0; }
.legend-row {
  display: flex;
  gap: 16px;
  font-size: 13px;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
  }
  .vip { background: #BA7517; }
  .normal { background: #1D9E75; }
  .potential { background: #534AB7; }
}
.map-overlay-bottom {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px;
  z-index: 10;
}
.info-card {
  .info-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    .info-title {
      flex: 1;
      .name {
        font-size: 16px;
        font-weight: 600;
        color: #303133;
      }
      .address {
        font-size: 13px;
        color: #909399;
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 2px;
      }
    }
  }
}
</style>
