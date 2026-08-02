<template>
  <div class="map-placeholder">
    <div class="placeholder-bg">
      <svg viewBox="0 0 800 500" preserveAspectRatio="xMidYMid slice">
        <defs>
          <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#dde6f0" stroke-width="0.5" />
          </pattern>
          <radialGradient id="mapGrad" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#e8f1fa" />
            <stop offset="100%" stop-color="#d4e2ee" />
          </radialGradient>
        </defs>
        <rect width="800" height="500" fill="url(#mapGrad)" />
        <rect width="800" height="500" fill="url(#grid)" />
        <path
          d="M 100 200 Q 250 100 400 200 T 700 250 L 700 350 Q 500 400 300 350 T 100 300 Z"
          fill="#c8dceb"
          opacity="0.5"
        />
        <path
          d="M 150 150 Q 300 250 500 180 T 750 280"
          fill="none"
          stroke="#7fb3d5"
          stroke-width="2"
          opacity="0.6"
        />
      </svg>
    </div>
    <div class="placeholder-markers">
      <div
        v-for="c in customers.slice(0, 12)"
        :key="c.id"
        class="marker"
        :class="['marker-' + c.categoryLabel, { active: selectedId === c.id }]"
        :style="{ left: c.mapX + '%', top: c.mapY + '%' }"
        @click="emit('select', c)"
      >
        <div class="marker-pulse"></div>
        <div class="marker-dot">
          <span>{{ c.name.charAt(0) }}</span>
        </div>
        <div class="marker-label">{{ c.name }}</div>
      </div>
    </div>
    <div class="placeholder-info">
      <el-icon :size="56" color="#0C447C" :opacity="0.5">
        <LocationFilled />
      </el-icon>
      <h2>{{ mapTypeLabel }} 集成视图</h2>
      <p class="desc">
        此处将集成 {{ mapTypeLabel }} JavaScript API<br />
        显示客户的地理位置分布、周边项目与设备密度
      </p>
      <el-alert type="info" :closable="false" class="api-hint">
        <template #title>
          <span>集成说明：请在 public/index.html 中引入对应的地图 SDK 脚本，并在 src/config/map.ts 中配置 Key</span>
        </template>
      </el-alert>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { LocationFilled } from '@element-plus/icons-vue'
import type { MapCustomer, MapType } from './types'
import { mapTypeName } from './types'

// v0.3.24 抽自 customer/CustomerMap.vue:98-168
const props = defineProps<{
  customers: MapCustomer[]
  selectedId: number | null
  mapType: MapType
}>()

const emit = defineEmits<{
  (e: 'select', c: MapCustomer): void
}>()

const mapTypeLabel = computed(() => mapTypeName(props.mapType))
</script>

<style lang="scss" scoped>
.map-placeholder {
  position: relative;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #e8f1fa 0%, #d4e2ee 100%);
  overflow: hidden;
}
.placeholder-bg {
  position: absolute;
  inset: 0;
  svg { width: 100%; height: 100%; }
}
.placeholder-markers { position: absolute; inset: 0; }
.marker {
  position: absolute;
  transform: translate(-50%, -50%);
  cursor: pointer;
  z-index: 5;

  .marker-pulse {
    position: absolute;
    top: 50%; left: 50%;
    width: 36px; height: 36px;
    transform: translate(-50%, -50%);
    border-radius: 50%;
    background: rgba(12, 68, 124, 0.3);
    animation: pulse 2s infinite;
  }
  .marker-dot {
    position: relative;
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #0C447C;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    border: 2px solid #fff;
    z-index: 2;
  }
  .marker-label {
    position: absolute;
    top: 100%; left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    margin-top: 4px;
    opacity: 0;
    transition: opacity 0.2s;
  }
  &:hover .marker-label, &.active .marker-label { opacity: 1; }
  &.active .marker-dot { transform: scale(1.2); }
}
.marker-VIP {
  .marker-dot { background: #BA7517; }
  .marker-pulse { background: rgba(186, 117, 23, 0.3); }
}
.marker-普通 {
  .marker-dot { background: #1D9E75; }
  .marker-pulse { background: rgba(29, 158, 117, 0.3); }
}
.marker-潜在 {
  .marker-dot { background: #534AB7; }
  .marker-pulse { background: rgba(83, 74, 183, 0.3); }
}
@keyframes pulse {
  0% { transform: translate(-50%, -50%) scale(0.8); opacity: 1; }
  100% { transform: translate(-50%, -50%) scale(2); opacity: 0; }
}
.placeholder-info {
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  z-index: 1;
  background: rgba(255, 255, 255, 0.85);
  padding: 24px 32px;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  backdrop-filter: blur(4px);
  h2 { color: #0C447C; font-size: 20px; margin: 12px 0 8px; }
  .desc {
    color: #606266;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 12px;
  }
  .api-hint { text-align: left; }
}
</style>
