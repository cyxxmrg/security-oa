<script setup lang="ts">
/**
 * InventoryBatchBar — 批量操作浮动栏 (v0.3.14 C4)
 * 选中项 > 0 时滑出，含 4 种批量操作 + 取消
 */
import { Check, EditPen, CircleCheck, CircleClose, Delete } from '@element-plus/icons-vue'

const props = withDefaults(
  defineProps<{
    count: number
  }>(),
  { count: 0 },
)

const emit = defineEmits<{
  (e: 'edit'): void
  (e: 'set-active'): void
  (e: 'set-inactive'): void
  (e: 'delete'): void
  (e: 'clear'): void
}>()
</script>

<template>
  <transition name="slide-down">
    <div v-if="count > 0" class="batch-bar">
      <div class="batch-bar__info">
        <el-icon :size="18" color="#0C447C"><Check /></el-icon>
        <span>已选 <strong>{{ count }}</strong> 项</span>
      </div>
      <div class="batch-bar__actions">
        <el-button :icon="EditPen" @click="emit('edit')">批量编辑</el-button>
        <el-button :icon="CircleCheck" type="success" @click="emit('set-active')">批量启用</el-button>
        <el-button :icon="CircleClose" type="warning" @click="emit('set-inactive')">批量禁用</el-button>
        <el-button :icon="Delete" type="danger" @click="emit('delete')">批量删除</el-button>
      </div>
      <el-button link class="batch-bar__clear" @click="emit('clear')">取消选择</el-button>
    </div>
  </transition>
</template>

<style scoped>
.batch-bar {
  display: flex; align-items: center; gap: 16px;
  padding: 10px 16px;
  background: linear-gradient(135deg, #E6F1FB 0%, #fff 60%);
  border-left: 4px solid #0C447C;
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(12, 68, 124, 0.08);
}
.batch-bar__info {
  display: flex; align-items: center; gap: 6px;
  font-size: 13px; color: #0C447C;
}
.batch-bar__info strong { color: #185FA5; font-size: 14px; }
.batch-bar__actions { flex: 1; display: flex; gap: 8px; }
.batch-bar__clear { color: #909399; }
.slide-down-enter-active, .slide-down-leave-active {
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-down-enter-from, .slide-down-leave-to {
  opacity: 0; transform: translateY(-8px);
}
</style>
