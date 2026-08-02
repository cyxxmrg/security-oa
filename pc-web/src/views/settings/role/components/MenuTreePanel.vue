<template>
  <div class="matrix-right">
    <div v-if="editingRole" class="right-content">
      <!-- 头部: 当前角色信息 -->
      <div class="right-header">
        <div class="role-info">
          <div class="role-color-dot lg" :style="{ background: editingRole.color || '#0C447C' }"></div>
          <div>
            <div class="role-info-name">
              {{ editingRole.name }}
              <el-tag v-if="editingRole.name === 'admin' || editingRole.name === 'system_admin'" size="small" type="info" effect="plain" class="ml-8">系统角色</el-tag>
              <el-tag v-else size="small" type="success" effect="plain" class="ml-8">自定义</el-tag>
            </div>
            <div class="role-info-desc">{{ editingRole.description || '—' }}</div>
          </div>
        </div>
        <div class="role-actions">
          <el-button size="small" :icon="Refresh" plain @click="$emit('reset-admin')" v-if="activeRole==='admin'">重置 admin 全选</el-button>
          <span class="stat-line">
            <span class="stat-num">{{ checkedCount }}</span> / {{ totalLeaves }} 界面已勾选
            ·
            <span style="color: var(--el-color-warning)">{{ missingPermCount }}</span> 待补 perm
          </span>
          <el-button type="primary" :icon="Check" :loading="saving" @click="$emit('save')" size="small">
            保存
          </el-button>
        </div>
      </div>

      <!-- 顶部操作条 -->
      <div class="permission-toolbar">
        <div class="toolbar-left">
          <el-button :icon="Check" size="small" @click="$emit('select-all')">全选可见</el-button>
          <el-button :icon="CircleClose" size="small" plain @click="$emit('deselect-all')">反选可见</el-button>
          <el-checkbox v-model="showOnlyMineModel" class="ml-8">只看已选</el-checkbox>
        </div>
      </div>

      <!-- 菜单树 -->
      <div class="menu-groups" v-loading="loadingMenus">
        <el-collapse v-model="expandedMenusModel" class="menu-collapse">
          <el-collapse-item
            v-for="menu in visibleMenus"
            :key="menu.path"
            :name="menu.path"
            class="menu-collapse-item"
          >
            <template #title>
              <div class="menu-collapse-title">
                <el-checkbox
                  :model-value="isMenuAllChecked(menu)"
                  :indeterminate="isMenuIndeterminate(menu)"
                  @change="(v: boolean) => toggleMenu(menu, v)"
                  @click.stop
                />
                <span class="menu-name">{{ menu.title }}</span>
                <el-tag size="small" type="info" effect="plain">{{ countChecked(menu) }}/{{ menu.leaves.length }}</el-tag>
                <el-tag v-if="menu.path === 'admin'" size="small" type="warning" effect="plain">system 专属</el-tag>
              </div>
            </template>
            <div class="leaves-grid">
              <label
                v-for="leaf in visibleLeaves(menu)"
                :key="menu.path + '|' + leaf.path"
                class="leaf-item"
                :class="{ 'leaf-disabled': leaf.perm_key && !leaf.perm_exists }"
              >
                <el-checkbox
                  :model-value="isLeafChecked(menu, leaf)"
                  :disabled="!leaf.perm_key || !leaf.perm_exists"
                  @change="(v: boolean) => toggleLeaf(menu, leaf, v)"
                >
                  <span class="leaf-name">{{ leaf.title }}</span>
                  <el-tag
                    v-if="leaf.perm_key && !leaf.perm_exists"
                    size="small"
                    type="warning"
                    effect="plain"
                    class="leaf-tag"
                  >待补 perm</el-tag>
                </el-checkbox>
              </label>
            </div>
          </el-collapse-item>
        </el-collapse>
        <div v-if="!visibleMenus.length" class="menu-empty">
          <el-empty description="无匹配菜单" :image-size="80" />
        </div>
      </div>
    </div>
    <div v-else class="right-placeholder">
      <el-empty description="请选择左侧角色" :image-size="120" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Check, CircleClose, Refresh } from '@element-plus/icons-vue'

defineProps<{
  editingRole: Record<string, unknown>
  activeRole: string
  checkedCount: number
  totalLeaves: number
  missingPermCount: number
  saving: boolean
  loadingMenus: boolean
  visibleMenus: Record<string, unknown>[]
  visibleLeaves: (menu: Record<string, unknown>) => Record<string, unknown>[]
  isLeafChecked: (menu: Record<string, unknown>, leaf: Record<string, unknown>) => boolean
  toggleLeaf: (menu: Record<string, unknown>, leaf: Record<string, unknown>, checked: boolean) => void
  isMenuAllChecked: (menu: Record<string, unknown>) => boolean
  isMenuIndeterminate: (menu: Record<string, unknown>) => boolean
  toggleMenu: (menu: Record<string, unknown>, checked: boolean) => void
  countChecked: (menu: Record<string, unknown>) => number
}>()

defineEmits<{
  'reset-admin': []
  'save': []
  'select-all': []
  'deselect-all': []
}>()

const showOnlyMineModel = defineModel<boolean>('showOnlyMine', { default: false })
const expandedMenusModel = defineModel<string[]>('expandedMenus', { default: () => [] })
</script>

<style scoped lang="scss">
.matrix-right {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.right-placeholder {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.right-content {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.right-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #ebeef5;
  background: linear-gradient(135deg, #fafbfc 0%, #f5f7fa 100%);

  .role-info { display: flex; align-items: center; gap: 12px; }
  .role-color-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    &.lg { width: 14px; height: 14px; }
  }
  .role-info-name {
    font-size: 16px; font-weight: 600; color: #303133;
    display: flex; align-items: center;
  }
  .role-info-desc { font-size: 12px; color: #909399; margin-top: 2px; }
  .role-actions { display: flex; gap: 8px; align-items: center; }
  .stat-line {
    font-size: 13px; color: #606266;
    .stat-num { font-weight: 600; color: #409eff; }
  }
}

.permission-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 20px;
  border-bottom: 1px solid #ebeef5;
  background: #fafbfc;
  .toolbar-left { display: flex; align-items: center; gap: 8px; }
}

.menu-groups {
  flex: 1;
  overflow-y: auto;
  padding: 12px 20px 20px;
}

.menu-collapse { border: none; }

.menu-collapse-item {
  margin-bottom: 8px;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  overflow: hidden;
  :deep(.el-collapse-item__header) {
    background: #f5f7fa;
    padding: 0 16px;
    border-bottom: 1px solid #ebeef5;
    height: 48px;
  }
  :deep(.el-collapse-item__content) { padding: 12px 16px; }
}

.menu-collapse-title {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  .menu-name { font-size: 14px; font-weight: 600; color: #303133; }
}

.leaves-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 8px 16px;
}

.leaf-item {
  display: flex;
  align-items: center;
  padding: 4px 6px;
  border-radius: 4px;
  transition: background 0.15s;
  &:hover { background: #f5f7fa; }
  .leaf-name { font-size: 13px; color: #303133; }
  .leaf-tag { margin-left: 6px; }
  &.leaf-disabled { opacity: 0.55; }
}

.menu-empty { padding: 60px 20px; }
.ml-8 { margin-left: 8px; }
</style>
