<template>
  <div>
    <div class="tab-toolbar">
      <el-button type="primary" :icon="Plus" @click="emit('addDept', null)">新增部门</el-button>
      <el-button :icon="RefreshRight" @click="emit('refresh')">刷新</el-button>
    </div>
    <el-tree
      v-if="tree.length"
      :data="tree"
      :props="treeProps"
      node-key="id"
      default-expand-all
      highlight-current
      :expand-on-click-node="false"
    >
      <template #default="{ node, data }">
        <div class="tree-node">
          <span class="tree-node__label">
            <el-icon color="#0C447C" :size="16"><FolderOpened /></el-icon>
            <span>{{ node.label }}</span>
            <el-tag size="small" type="info" class="tree-node__count">{{ data.count }}人</el-tag>
            <span class="tree-node__manager" v-if="data.manager">{{ data.manager }}</span>
          </span>
          <span class="tree-node__actions">
            <el-button link type="primary" size="small" :icon="Plus" @click.stop="emit('addDept', data)">新增子部门</el-button>
            <el-button link type="primary" size="small" :icon="Edit" @click.stop="emit('editDept', data)">编辑</el-button>
            <el-button link type="info" size="small" @click.stop="emit('showEmployees', data)">员工</el-button>
            <el-popconfirm :title="`确定删除部门「${data.label}」?`" @confirm="emit('deleteDept', data)">
              <template #reference>
                <el-button link type="danger" size="small" :icon="Delete" @click.stop>删除</el-button>
              </template>
            </el-popconfirm>
          </span>
        </div>
      </template>
    </el-tree>
    <el-empty v-else description="暂无部门数据" />
  </div>
</template>

<script setup lang="ts">
import { Plus, RefreshRight, FolderOpened, Edit, Delete } from '@element-plus/icons-vue'

export interface DeptNode {
  id: number
  label: string
  name?: string
  count?: number
  manager?: string | null
  children?: DeptNode[]
}

defineProps<{ tree: DeptNode[]; treeProps: { children: string; label: string } }>()
const emit = defineEmits<{
  (e: 'addDept', data: DeptNode | null): void
  (e: 'editDept', data: DeptNode): void
  (e: 'deleteDept', data: DeptNode): void
  (e: 'showEmployees', data: DeptNode): void
  (e: 'refresh'): void
}>()
</script>

<style lang="scss" scoped>
.tree-node {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding: 0 8px;
  font-size: 13px;
  &__label { display: flex; align-items: center; gap: 8px; flex: 1; }
  &__count { margin-left: 4px; }
  &__manager { color: #909399; font-size: 12px; }
  &__actions { display: flex; gap: 4px; }
}
:deep(.el-tree-node__content) { height: 40px; }
.tab-toolbar { margin-bottom: 16px; display: flex; gap: 8px; }
</style>
