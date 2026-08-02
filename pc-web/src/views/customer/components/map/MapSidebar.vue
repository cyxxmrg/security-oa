<template>
  <aside class="map-sidebar">
    <div class="sidebar-search">
      <el-input
        v-model="keyword"
        placeholder="搜索客户名称/联系人"
        clearable
        :prefix-icon="Search"
      />
    </div>
    <div class="sidebar-filter">
      <el-select v-model="filterCategory" placeholder="分类" size="small" clearable>
        <el-option
          v-for="o in CATEGORY_OPTIONS"
          :key="o.value"
          :label="o.label"
          :value="o.value"
        />
      </el-select>
      <el-select v-model="filterIndustry" placeholder="行业" size="small" clearable>
        <el-option
          v-for="o in INDUSTRY_OPTIONS"
          :key="o.value"
          :label="o.label"
          :value="o.value"
        />
      </el-select>
    </div>
    <div class="sidebar-stats">
      <div class="stat-item">
        <span class="num">{{ totalCount }}</span>
        <span class="label">客户总数</span>
      </div>
      <div class="stat-item vip">
        <span class="num">{{ vipCount }}</span>
        <span class="label">VIP客户</span>
      </div>
      <div class="stat-item normal">
        <span class="num">{{ normalCount }}</span>
        <span class="label">普通客户</span>
      </div>
    </div>
    <div class="customer-list" v-loading="loading">
      <el-scrollbar height="calc(100vh - 320px)">
        <div
          v-for="c in customers"
          :key="c.id"
          class="customer-card"
          :class="{ active: selectedId === c.id }"
          @click="emit('select', c)"
        >
          <div class="card-head">
            <el-avatar :size="36" :style="{ background: c.color }">
              {{ c.name.charAt(0) }}
            </el-avatar>
            <div class="card-title">
              <div class="title-row">
                <span class="name">{{ c.name }}</span>
                <el-tag :type="categoryType(c.categoryLabel)" size="small" effect="light">
                  {{ c.categoryLabel }}
                </el-tag>
              </div>
              <div class="meta">
                <el-icon><Location /></el-icon>
                <span>{{ c.city }}</span>
              </div>
            </div>
          </div>
          <div class="card-info">
            <div class="info-item">
              <span class="lbl">联系人：</span>
              <span class="val">{{ c.contact }}</span>
            </div>
            <div class="info-item">
              <span class="lbl">行业：</span>
              <span class="val">{{ c.industry }}</span>
            </div>
            <div class="info-item">
              <span class="lbl">项目：</span>
              <span class="val project-count">{{ c.projectCount }} 个</span>
            </div>
          </div>
        </div>
        <el-empty v-if="!loading && customers.length === 0" description="暂无客户" />
      </el-scrollbar>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Search, Location } from '@element-plus/icons-vue'
import type { MapCustomer } from './types'
import { CATEGORY_OPTIONS, INDUSTRY_OPTIONS, categoryType } from './types'

// v0.3.24 抽自 customer/CustomerMap.vue:16-96
const props = defineProps<{
  customers: MapCustomer[]
  selectedId: number | null
  loading: boolean
  keyword: string
  filterCategory: string
  filterIndustry: string
  totalCount: number
  vipCount: number
  normalCount: number
}>()

const emit = defineEmits<{
  (e: 'select', c: MapCustomer): void
  (e: 'update:keyword', v: string): void
  (e: 'update:filterCategory', v: string): void
  (e: 'update:filterIndustry', v: string): void
}>()

// 双向 v-model 适配
const keyword = computed({
  get: () => props.keyword,
  set: (v) => emit('update:keyword', v),
})
const filterCategory = computed({
  get: () => props.filterCategory,
  set: (v) => emit('update:filterCategory', v),
})
const filterIndustry = computed({
  get: () => props.filterIndustry,
  set: (v) => emit('update:filterIndustry', v),
})
</script>

<style lang="scss" scoped>
.map-sidebar {
  background: #fff;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}
.sidebar-search {
  padding: 12px;
  border-bottom: 1px solid #ebeef5;
}
.sidebar-filter {
  padding: 0 12px 12px;
  display: flex;
  gap: 8px;
  :deep(.el-select) { flex: 1; }
}
.sidebar-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  border-top: 1px solid #ebeef5;
  border-bottom: 1px solid #ebeef5;
  .stat-item {
    padding: 12px 8px;
    text-align: center;
    border-right: 1px solid #ebeef5;
    &:last-child { border-right: none; }
    .num {
      display: block;
      font-size: 18px;
      font-weight: 600;
      color: #0C447C;
    }
    &.vip .num { color: #BA7517; }
    &.normal .num { color: #1D9E75; }
    .label {
      font-size: 12px;
      color: #909399;
    }
  }
}
.customer-list {
  flex: 1;
  overflow: hidden;
  padding: 8px;
}
.customer-card {
  padding: 12px;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: all 0.2s;
  &:hover {
    border-color: #0C447C;
    box-shadow: 0 2px 8px rgba(12, 68, 124, 0.1);
  }
  &.active {
    border-color: #0C447C;
    background: linear-gradient(135deg, #f0f6fc 0%, #fff 100%);
    box-shadow: 0 2px 8px rgba(12, 68, 124, 0.15);
  }
  .card-head {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
  }
  .card-title {
    flex: 1;
    margin-left: 10px;
    overflow: hidden;
  }
  .title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    .name {
      font-weight: 600;
      color: #303133;
      font-size: 14px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  }
  .meta {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #909399;
    margin-top: 2px;
  }
  .card-info {
    font-size: 12px;
    color: #606266;
  }
  .info-item {
    display: flex;
    margin-top: 2px;
    .lbl {
      color: #909399;
      width: 50px;
    }
    .val { flex: 1; }
    .project-count {
      color: #0C447C;
      font-weight: 600;
    }
  }
}
</style>
