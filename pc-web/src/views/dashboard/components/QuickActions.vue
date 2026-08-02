<script setup lang="ts">
/**
 * QuickActions — 8 个快捷入口 (v0.3.14 C1)
 */
defineProps<{
  actions: Array<{
    label: string
    icon: string
    path: string
    bg: string
    color: string
  }>
}>()

const emit = defineEmits<{
  (e: 'jump', path: string): void
}>()
</script>

<template>
  <el-card shadow="never" class="quick-actions">
    <div class="action-grid">
      <div
        v-for="action in actions"
        :key="action.label"
        class="action-item"
        @click="emit('jump', action.path)"
      >
        <div class="action-icon" :style="{ background: action.bg, color: action.color }">
          <el-icon :size="22"><component :is="action.icon" /></el-icon>
        </div>
        <span class="action-label">{{ action.label }}</span>
      </div>
    </div>
  </el-card>
</template>

<style scoped>
.quick-actions { margin-bottom: 16px; }
.action-grid {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 8px;
}
.action-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 12px 4px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s, transform 0.2s;
}
.action-item:hover {
  background: #f5f7fa;
  transform: translateY(-2px);
}
.action-icon {
  width: 48px; height: 48px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
}
.action-label {
  font-size: 12px; color: #606266;
  text-align: center;
}
@media (max-width: 1280px) {
  .action-grid { grid-template-columns: repeat(4, 1fr); }
}
</style>
