<script setup lang="ts">
/**
 * InventoryWarningBanner — 库存预警横幅 (v0.3.14 C4)
 * 点击 → 跳到列表筛选
 */
import { WarningFilled } from '@element-plus/icons-vue'

defineProps<{
  lowStock: number
  expiring: number
}>()

const emit = defineEmits<{
  (e: 'click'): void
}>()
</script>

<template>
  <div v-if="lowStock + expiring > 0" class="warning-banner" @click="emit('click')">
    <el-icon :size="18" color="#A32D2D"><WarningFilled /></el-icon>
    <span class="warning-banner__text">
      库存预警:
      <template v-if="lowStock > 0">
        <strong>{{ lowStock }}</strong> 个低库存
      </template>
      <template v-if="expiring > 0">
        <template v-if="lowStock > 0"> · </template>
        <strong>{{ expiring }}</strong> 个临期
      </template>
      <span class="warning-banner__hint">点击查看</span>
    </span>
  </div>
</template>

<style scoped>
.warning-banner {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 16px; border-radius: 6px;
  background: linear-gradient(135deg, #fdecec 0%, #fff 60%);
  border-left: 4px solid #A32D2D;
  cursor: pointer; user-select: none;
  transition: transform 0.15s, box-shadow 0.15s;
}
.warning-banner:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(163, 45, 45, 0.1);
}
.warning-banner__text { font-size: 13px; color: #303133; }
.warning-banner__text strong { color: #A32D2D; margin: 0 2px; font-size: 14px; }
.warning-banner__hint { color: #909399; margin-left: 8px; font-size: 12px; }
</style>
