<script setup lang="ts">
/**
 * InventoryToolbar — 顶部工具条 (v0.3.14 C4)
 * 搜索框 + 批量导入/导出/新建
 */
import { ref, watch } from 'vue'
import { Search, Plus } from '@element-plus/icons-vue'

const props = defineProps<{
  modelValue: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
  (e: 'search'): void
  (e: 'import'): void
  (e: 'export'): void
  (e: 'create'): void
}>()

const local = ref(props.modelValue)
watch(() => props.modelValue, (v) => (local.value = v))
const update = (v: string) => {
  local.value = v
  emit('update:modelValue', v)
}

const onSearch = () => emit('search')
const onKeyEnter = () => emit('search')
const onClear = () => {
  local.value = ''
  emit('update:modelValue', '')
  emit('search')
}
</script>

<template>
  <div class="inventory-toolbar">
    <el-input
      :model-value="local"
      @update:model-value="update"
      placeholder="搜索物品名称/编码"
      clearable
      :prefix-icon="Search"
      style="width: 260px"
      @keyup.enter="onKeyEnter"
      @clear="onClear"
    />
    <span class="inventory-toolbar__spacer" />
    <el-button @click="emit('import')">批量导入</el-button>
    <el-button @click="emit('export')">导出 CSV</el-button>
    <el-button type="primary" :icon="Plus" @click="emit('create')">+ 新建物品</el-button>
  </div>
</template>

<style scoped>
.inventory-toolbar {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; background: #fff;
  border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.inventory-toolbar__spacer { flex: 1; }
</style>
