<script setup lang="ts">
/**
 * TodoListCard — 待办事项卡 (v0.3.14 C1)
 * 支持点击跳转，空状态
 */
import { BellFilled } from '@element-plus/icons-vue'

defineProps<{
  todos: Array<{
    id: number | string
    type: string
    content: string
    time: string
    tagType: string
    link?: string
  }>
}>()

const emit = defineEmits<{
  (e: 'click', todo: { id: number | string; link?: string }): void
}>()
</script>

<template>
  <el-card shadow="never" class="todo-card">
    <template #header>
      <div class="card-header">
        <span class="card-title">待办事项</span>
        <el-badge :value="todos.length" type="primary" :hidden="!todos.length" />
      </div>
    </template>
    <div class="todo-list">
      <div
        v-for="item in todos"
        :key="item.id"
        class="todo-item"
        :class="{ clickable: !!item.link }"
        @click="item.link && emit('click', item)"
      >
        <div class="todo-left">
          <el-tag :type="(item.tagType as Record<string, unknown>)" size="small" effect="plain">{{ item.type }}</el-tag>
          <span class="todo-text">{{ item.content }}</span>
        </div>
        <span class="todo-time">{{ item.time }}</span>
      </div>
      <el-empty v-if="!todos.length" :image-size="60" description="暂无待办" />
    </div>
  </el-card>
</template>

<style scoped>
.card-header {
  display: flex; justify-content: space-between; align-items: center;
  width: 100%; height: 48px;
}
.card-title {
  font-size: 15px; font-weight: 600; color: #2c3e50;
  display: flex; align-items: center; gap: 8px;
}
.card-title::before {
  content: ''; display: inline-block;
  width: 3px; height: 14px;
  background: linear-gradient(180deg, #0C447C 0%, #1D9E75 100%);
  border-radius: 2px; flex-shrink: 0;
}
.todo-list {
  max-height: 320px; overflow-y: auto;
}
.todo-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 4px; border-bottom: 1px solid #f5f7fa;
  transition: background 0.2s;
}
.todo-item.clickable { cursor: pointer; }
.todo-item.clickable:hover { background: #f5f7fa; }
.todo-item:last-child { border-bottom: none; }
.todo-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.todo-text {
  font-size: 13px; color: #303133;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.todo-time { font-size: 11px; color: #909399; flex-shrink: 0; }
</style>
