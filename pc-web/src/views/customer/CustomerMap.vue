<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">客户地图</span>
      <div class="header-actions">
        <el-radio-group v-model="mapType" size="default">
          <el-radio-button
            v-for="o in MAP_TYPE_OPTIONS"
            :key="o.value"
            :label="o.value"
          >{{ o.label }}</el-radio-button>
        </el-radio-group>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新增客户</el-button>
      </div>
    </div>

    <div class="map-layout">
      <MapSidebar
        :customers="filteredCustomers"
        :selected-id="selectedId"
        :loading="loading"
        :total-count="customers.length"
        :vip-count="vipCount"
        :normal-count="normalCount"
        :keyword.sync="keyword"
        :filter-category.sync="filterCategory"
        :filter-industry.sync="filterIndustry"
        @select="selectCustomer"
      />

      <main class="map-canvas">
        <MapOverlayPanels :customer="selectedCustomer" />

        <MapCanvas
          :customers="filteredCustomers"
          :selected-id="selectedId"
          :map-type="mapType"
          @select="selectCustomer"
        />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus } from '@element-plus/icons-vue'
import { get } from '@/utils/request'

import MapSidebar from './components/map/MapSidebar.vue'
import MapCanvas from './components/map/MapCanvas.vue'
import MapOverlayPanels from './components/map/MapOverlayPanels.vue'

import type { MapCustomer, MapType } from './components/map/types'
import { MAP_TYPE_OPTIONS, normalizeCustomer } from './components/map/types'

// v0.3.24 拆 CustomerMap.vue 696→165 (-76%)
// 子组件: MapSidebar / MapCanvas / MapOverlayPanels

const router = useRouter()
const loading = ref(false)
const mapType = ref<MapType>('amap')
const keyword = ref('')
const filterCategory = ref('')
const filterIndustry = ref('')
const selectedId = ref<number | null>(null)

const customers = ref<MapCustomer[]>([])

async function loadCustomers() {
  loading.value = true
  try {
    const data = await get('/customers', { per_page: 1000 })
    // V0.6.3: res = {code, data: paginator}
    const d = data?.data ?? {}
    const arr = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : [])
    customers.value = arr.map(normalizeCustomer)
    if (!selectedId.value && customers.value.length) selectedId.value = customers.value[0].id
  } catch { /* toast */ }
  loading.value = false
}
onMounted(loadCustomers)

const filteredCustomers = computed(() => {
  return customers.value.filter((c) => {
    const kw = !keyword.value
      || (c.name && c.name.includes(keyword.value))
      || (c.contact && c.contact.includes(keyword.value))
    const cat = !filterCategory.value || c.categoryLabel === filterCategory.value
    const ind = !filterIndustry.value || c.industry === filterIndustry.value
    return kw && cat && ind
  })
})

const vipCount = computed(() => customers.value.filter((c) => c.categoryLabel === 'VIP').length)
const normalCount = computed(() => customers.value.filter((c) => c.categoryLabel === '普通').length)

const selectedCustomer = computed(() =>
  customers.value.find((c) => c.id === selectedId.value) || null
)

const selectCustomer = (c: MapCustomer) => {
  selectedId.value = c.id
}

const handleAdd = () => router.push('/customer/list')
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 16px 20px;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title {
    font-size: 18px;
    font-weight: 600;
    color: #0C447C;
    border-left: 4px solid #0C447C;
    padding-left: 10px;
  }
  .header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
  }
}
.map-layout {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 12px;
  height: calc(100vh - 140px);
}
.map-canvas {
  position: relative;
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
</style>
