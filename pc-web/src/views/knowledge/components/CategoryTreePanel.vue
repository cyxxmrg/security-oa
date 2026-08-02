<template>
  <el-card shadow="never" class="category-tree" v-loading="catLoading">
    <template #header>
      <div class="cat-head">
        <span>知识分类</span>
        <el-dropdown trigger="click" @command="(c: string) => $emit('category-action', c)">
          <el-button type="primary" link size="small">
            <el-icon><Plus /></el-icon>新增分类
          </el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="root">新建顶级分类</el-dropdown-item>
              <el-dropdown-item command="sub" :disabled="!currentCategory">在「{{ currentCategory?.name }}」下新建子分类</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
    </template>
    <div class="cat-all" :class="{ active: !currentCategoryId }" @click="$emit('filter-category', null)">
      <el-icon><Files /></el-icon> 全部 ({{ totalCount }})
    </div>
    <el-tree
      ref="treeRef"
      :data="categories"
      :props="{ label: 'name', children: 'children' }"
      node-key="id"
      highlight-current
      :expand-on-click-node="false"
      @node-click="(d: Record<string, unknown>) => $emit('filter-category', d.id)"
      empty-text="暂无分类，点击右上角新增"
    >
      <template #default="{ data }">
        <span class="cat-node" :class="{ active: currentCategoryId === data.id }">
          <el-icon><Folder /></el-icon>
          <span class="cat-node__name">{{ data.name }}</span>
          <el-tag v-if="data.articles_count" size="small" effect="plain" type="info">{{ data.articles_count }}</el-tag>
          <el-dropdown trigger="click" @click.stop @command="(c: string) => $emit('node-action', c, data)">
            <el-icon class="cat-node__more" @click.stop><MoreFilled /></el-icon>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="addSub">新建子分类</el-dropdown-item>
                <el-dropdown-item command="rename">重命名</el-dropdown-item>
                <el-dropdown-item command="delete" divided>删除分类</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </span>
      </template>
    </el-tree>
  </el-card>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Plus, Files, Folder, MoreFilled } from '@element-plus/icons-vue'

const props = defineProps<{
  catLoading: boolean
  categories: Record<string, unknown>[]
  currentCategoryId: number | null
  currentCategory: Record<string, unknown>
  totalCount: number
}>()

defineEmits<{
  'category-action': [cmd: string]
  'filter-category': [id: number | null]
  'node-action': [cmd: string, data: Record<string, unknown>]
}>()

const treeRef = ref()

// 同步外部 currentCategoryId → 树高亮 (替代原 composable 里 treeRef.setCurrentKey 调用)
watch(() => props.currentCategoryId, (id) => {
  treeRef.value?.setCurrentKey(id)
})
</script>

<style lang="scss" scoped>
$primary: #0C447C;

.category-tree {
  :deep(.el-card__body) { padding: 8px; }
  :deep(.el-card__header) { padding: 12px 16px; }
  :deep(.el-tree-node__content) { height: 34px; }
}
.cat-head { display: flex; align-items: center; justify-content: space-between; }
.cat-all {
  padding: 6px 10px; margin-bottom: 6px; border-radius: 6px;
  cursor: pointer; font-size: 14px; color: #303133;
  display: flex; align-items: center; gap: 6px;
  transition: background 0.2s;
  &:hover { background: #f5f7fa; }
  &.active { background: #e6f0fa; color: $primary; font-weight: 600; }
}
.cat-node { display: flex; align-items: center; gap: 6px; flex: 1; padding-right: 6px;
  &__name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  &.active { color: $primary; font-weight: 600; }
  &__more { padding: 2px 4px; border-radius: 3px; color: #9ca3af; opacity: 0; transition: opacity .2s; .cat-node:hover & { opacity: 1; } &:hover { background: #e5e7eb; color: $primary; } }
}
</style>
