<template>
  <div class="matrix-page">
    <!-- 顶部工具条 -->
    <div class="page-header">
      <div class="header-left">
        <el-icon :size="22" color="#0C447C"><Key /></el-icon>
        <span class="page-title">权限矩阵</span>
        <el-tag type="info" effect="plain" size="small" class="ml-8">按菜单树 · 勾选式</el-tag>
      </div>
      <div class="header-right">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索菜单/页面"
          clearable
          :prefix-icon="Search"
          style="width: 220px"
          size="default"
        />
        <el-button :icon="Expand" @click="expandAll" size="default">全部展开</el-button>
        <el-button :icon="Fold" @click="collapseAll" size="default">全部收起</el-button>
      </div>
    </div>

    <!-- 携程风: 左角色 + 右菜单树 -->
    <div class="matrix-layout">
      <!-- 左栏: 角色列表 -->
      <RoleListPanel
        v-model:active-role="activeRole"
        v-model:role-filter="roleFilter"
        :loading-roles="loadingRoles"
        :filtered-roles="filteredRoles"
        :count-own-leaves="countOwnLeaves"
        @created="fetchMenuMatrix"
      />

      <!-- 右栏: 菜单树 (折叠面板 + 叶子 checkbox) -->
      <MenuTreePanel
        v-model:show-only-mine="showOnlyMine"
        v-model:expanded-menus="expandedMenus"
        :editing-role="editingRole"
        :active-role="activeRole"
        :checked-count="checkedCount"
        :total-leaves="totalLeaves"
        :missing-perm-count="missingPermCount"
        :saving="saving"
        :loading-menus="loadingMenus"
        :visible-menus="visibleMenus"
        :visible-leaves="visibleLeaves"
        :is-leaf-checked="isLeafChecked"
        :toggle-leaf="toggleLeaf"
        :is-menu-all-checked="isMenuAllChecked"
        :is-menu-indeterminate="isMenuIndeterminate"
        :toggle-menu="toggleMenu"
        :count-checked="countChecked"
        @reset-admin="resetAdminAll"
        @save="saveCurrent"
        @select-all="selectAllVisible"
        @deselect-all="deselectAllVisible"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Key, Search, Expand, Fold } from '@element-plus/icons-vue'
import { useRoleMatrix } from './composables/useRoleMatrix'
import RoleListPanel from './components/RoleListPanel.vue'
import MenuTreePanel from './components/MenuTreePanel.vue'

const {
  activeRole, loadingRoles, loadingMenus, saving,
  searchKeyword, roleFilter, showOnlyMine, expandedMenus,
  editingRole, totalLeaves, checkedCount, missingPermCount,
  filteredRoles, visibleMenus, visibleLeaves, countOwnLeaves,
  isLeafChecked, toggleLeaf, isMenuAllChecked, isMenuIndeterminate,
  toggleMenu, countChecked, selectAllVisible, deselectAllVisible,
  expandAll, collapseAll, saveCurrent, resetAdminAll, fetchMenuMatrix,
} = useRoleMatrix()
</script>

<style scoped lang="scss">
.matrix-page {
  padding: 16px 20px 20px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
  display: flex;
  flex-direction: column;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  background: #fff;
  padding: 14px 20px;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);

  .header-left {
    display: flex;
    align-items: center;
    gap: 8px;
    .page-title { font-size: 18px; font-weight: 600; color: #303133; }
  }
  .header-right {
    display: flex;
    gap: 8px;
    align-items: center;
  }
}

.matrix-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 16px;
  flex: 1;
  min-height: 0;
}

.ml-8 { margin-left: 8px; }
</style>
