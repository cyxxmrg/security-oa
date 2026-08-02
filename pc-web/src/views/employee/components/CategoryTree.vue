<template>
  <div class="dept-tree">
    <div class="tree-toolbar">
      <span class="title">组织架构</span>
      <el-button v-if="showActions && !readonly" link type="primary" :icon="Plus" size="small" @click="handleAddRoot">新建部门</el-button>
    </div>

    <el-input
      v-model="filterText"
      size="small"
      placeholder="搜索部门/岗位"
      clearable
      :prefix-icon="Search"
      class="tree-search"
    />

    <div class="tree-wrap" v-loading="loading">
      <el-tree
        v-if="treeRoots.length"
        ref="treeRef"
        :data="treeRoots"
        :props="treeProps"
        node-key="id"
        :filter-node-method="filterNode"
        :default-expanded-all="false"
        highlight-current
        :expand-on-click-node="false"
        class="cat-tree"
        @node-click="onNodeClick"
        @node-contextmenu="readonly ? null : onContextMenu"
      >
        <template #default="{ node, data }">
          <div class="tree-node" :class="{ 'is-readonly': readonly }" @dblclick.stop="readonly ? null : openRename(data)">
            <span v-if="editingNodeId !== data.id" class="tree-node__label">
              <el-icon :color="iconColor(data)" :size="14">
                <component :is="iconOf(data)" />
              </el-icon>
              <span class="tree-name">{{ data.label }}</span>
              <el-tag
                v-if="data.count && data.type !== 'company'"
                size="small"
                :type="data.count > 0 ? 'success' : 'info'"
                class="tree-count"
              >
                {{ data.count }} 人
              </el-tag>
              <el-icon
                v-if="canEdit(data) && !readonly"
                class="tree-node__edit"
                :size="12"
                color="#909399"
                @click.stop="openRename(data)"
                title="重命名"
              >
                <Edit />
              </el-icon>
            </span>
            <span v-else class="tree-node__edit-inline">
              <el-input
                ref="renameInputRef"
                v-model="renameDraft"
                size="small"
                maxlength="100"
                show-word-limit
                @keyup.enter="commitRename(data)"
                @keyup.esc="cancelRename"
                @blur="commitRename(data)"
              />
            </span>
          </div>
        </template>
      </el-tree>
      <el-empty v-else-if="!loading" :image-size="60" description="暂无部门" />
    </div>

    <!-- 右键菜单 (全局 fixed) -->
    <ul
      v-show="ctxMenu.visible"
      class="ctx-menu"
      :style="{ left: ctxMenu.left + 'px', top: ctxMenu.top + 'px' }"
      @click.stop
    >
      <li v-if="ctxMenu.data && ctxMenu.data.type === 'dept'" class="ctx-menu__item" @click="handleCtx('addChild')">
        <el-icon><Plus /></el-icon>新建子部门
      </li>
      <li v-if="ctxMenu.data && (ctxMenu.data.type === 'dept' || ctxMenu.data.type === 'company')" class="ctx-menu__item" @click="handleCtx('addPos')">
        <el-icon><Plus /></el-icon>新建岗位
      </li>
      <li class="ctx-menu__item" @click="handleCtx('rename')">
        <el-icon><Edit /></el-icon>重命名
      </li>
      <li
        v-if="ctxMenu.data && ctxMenu.data.type !== 'company'"
        class="ctx-menu__item ctx-menu__item--danger"
        :class="{ 'is-disabled': cannotDelete(ctxMenu.data) }"
        @click="handleCtx('delete')"
      >
        <el-icon><Delete /></el-icon>删除
        <span v-if="deleteHint(ctxMenu.data)" class="ctx-menu__hint">{{ deleteHint(ctxMenu.data) }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Edit, Delete, Search, FolderOpened, Document, OfficeBuilding, User } from '@element-plus/icons-vue'
import { get, put, del } from '@/utils/request'
import { useSystemConfigStore } from '@/stores/systemConfig'

// 部门
interface DepartmentItem {
  id: number
  name: string
  parent_id?: number
  manager_id?: number
  sort_order?: number
  count?: number
  [key: string]: unknown
}
// 岗位
interface PositionItem {
  id: number
  name: string
  department_id: number
  level?: string
  description?: string
  sort_order?: number
  count?: number
  [key: string]: unknown
}
// 树节点
interface TreeNode {
  id: string | number
  label: string
  type: 'company' | 'dept' | 'position'
  count: number
  children?: TreeNode[]
  raw?: DepartmentItem | PositionItem
}
// 编辑/新增数据
interface TreeEditData {
  id: string
  type: 'dept' | 'position'
  label: string
  isNew?: boolean
  parent_id?: number
  department_id?: number | null
}
// API 错误
interface ApiError {
  message?: string
  response?: { data?: { message?: string } }
}
// el-tree 组件实例
interface TreeComponent {
  filter: (val: string) => void
  setCurrentKey: (key: unknown) => void
  [key: string]: unknown
}
// el-input 组件实例
interface InputComponent {
  focus?: () => void
  select?: () => void
  [key: string]: unknown
}

const sysConfig = useSystemConfigStore()

const props = defineProps<{
  modelValue?: number | null
  departments: DepartmentItem[]
  positions: PositionItem[]
  showActions?: boolean
  readonly?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: number | null): void
  (e: 'refresh'): void
  (e: 'edit-dept', data: TreeEditData): void
  (e: 'edit-pos', data: TreeEditData): void
  (e: 'delete-dept', data: TreeNode): void
  (e: 'delete-pos', data: TreeNode): void
}>()

const treeRef = ref<TreeComponent>()
const renameInputRef = ref<InputComponent>()
const loading = ref(false)
const treeRoots = ref<TreeNode[]>([])
const filterText = ref('')

// 内联重命名
const editingNodeId = ref<string | number | null>(null)
const renameDraft = ref('')

const treeProps = { value: 'id', label: 'label', children: 'children' }

const ctxMenu = reactive({
  visible: false,
  left: 0,
  top: 0,
  data: null as TreeNode | null,
})

watch(filterText, (val) => treeRef.value?.filter(val))

// 监视 props 变化重建树
watch(
  () => [props.departments, props.positions],
  () => rebuildTree(),
  { deep: true, immediate: false },
)

// 监视公司名变化（其他位置改了系统名也同步刷新树根）
watch(
  () => sysConfig.settings.system_name,
  () => rebuildTree(),
)

function rebuildTree() {
  const depts = props.departments || []
  const positions = props.positions || []
  // 公司名从系统设置读，允许用户在侧边"系统设置"或直接此处重命名
  const root: TreeNode = {
    id: 'root',
    label: sysConfig.settings.system_name || 'OA科技有限公司',
    type: 'company',
    count: 0,
    children: [],
  }
  const topDepts = depts.filter(d => !d.parent_id)
  for (const d of topDepts) {
    root.children!.push(buildDeptNode(d, depts, positions))
  }
  treeRoots.value = [root]
}

function buildDeptNode(d: DepartmentItem, depts: DepartmentItem[], positions: PositionItem[]): TreeNode {
  const subDepts = depts.filter(x => x.parent_id === d.id)
  const subPos = positions.filter(p => p.department_id === d.id)
  const children: TreeNode[] = []
  for (const sd of subDepts) children.push(buildDeptNode(sd, depts, positions))
  for (const p of subPos) {
    children.push({
      id: `p-${p.id}`,
      label: p.name,
      type: 'position',
      raw: p,
      count: p.count || 0,
    })
  }
  return {
    id: `d-${d.id}`,
    label: d.name,
    type: 'dept',
    raw: d,
    count: d.count || 0,
    children,
  }
}

function iconOf(data: TreeNode) {
  if (data.type === 'company') return OfficeBuilding
  if (data.type === 'dept') return FolderOpened
  if (data.type === 'position') return User
  return Document
}
function iconColor(data: TreeNode) {
  if (data.type === 'company') return '#0C447C'
  if (data.type === 'dept') return '#1D9E75'
  if (data.type === 'position') return '#534AB7'
  return '#909399'
}
function canEdit(data: TreeNode) {
  return data.type === 'company' || data.type === 'dept' || data.type === 'position'
}
function cannotDelete(data: TreeNode) {
  if (!data) return false
  if (data.type === 'dept') {
    // 有子部门 / 有员工
    return (data.children && data.children.length > 0) || (data.count > 0)
  }
  if (data.type === 'position') {
    return (data.count > 0)
  }
  return true // root 不能删
}
function deleteHint(data: TreeNode) {
  if (!data) return ''
  if (data.type === 'dept') {
    if (data.children?.length > 0) return '(有子部门)'
    if (data.count > 0) return `(有 ${data.count} 名员工)`
  }
  if (data.type === 'position' && data.count > 0) {
    return `(有 ${data.count} 名员工)`
  }
  return ''
}

function filterNode(value: string, data: TreeNode) {
  if (!value) return true
  return data.label?.includes(value)
}

function onNodeClick(data: TreeNode) {
  // 选中部门时同步 modelValue
  if (data.type === 'dept') {
    const deptId = Number(String(data.id).replace('d-', ''))
    emit('update:modelValue', deptId)
  } else {
    emit('update:modelValue', null)
  }
}

function onContextMenu(event: MouseEvent, data: TreeNode) {
  event.preventDefault()
  event.stopPropagation()
  ctxMenu.visible = true
  ctxMenu.left = event.clientX
  ctxMenu.top = event.clientY
  ctxMenu.data = data
  treeRef.value?.setCurrentKey(data.id)
  document.addEventListener('click', hideCtxMenu, { once: true })
}

function hideCtxMenu() {
  ctxMenu.visible = false
  ctxMenu.data = null
}

function handleAddRoot() {
  // 触发父组件的"新增部门"对话框
  emit('edit-dept', { id: 'new', type: 'dept', label: '', isNew: true })
}

function handleCtx(action: 'addChild' | 'addPos' | 'rename' | 'delete') {
  hideCtxMenu()
  if (!ctxMenu.data) return
  if (action === 'addChild') {
    if (ctxMenu.data.type !== 'dept') return
    emit('edit-dept', { id: 'new', type: 'dept', label: '', parent_id: Number(String(ctxMenu.data.id).replace('d-', '')), isNew: true })
  } else if (action === 'addPos') {
    let deptId: number | null = null
    if (ctxMenu.data.type === 'dept') {
      deptId = Number(String(ctxMenu.data.id).replace('d-', ''))
    } else if (ctxMenu.data.type === 'position') {
      deptId = ctxMenu.data.raw?.department_id || null
    }
    emit('edit-pos', { id: 'new', type: 'position', label: '', department_id: deptId, isNew: true })
  } else if (action === 'rename') {
    openRename(ctxMenu.data)
  } else if (action === 'delete') {
    if (cannotDelete(ctxMenu.data)) {
      ElMessage.warning('存在子部门或员工，无法删除')
      return
    }
    if (ctxMenu.data.type === 'dept') emit('delete-dept', ctxMenu.data)
    else if (ctxMenu.data.type === 'position') emit('delete-pos', ctxMenu.data)
  }
}

function openRename(data: TreeNode) {
  if (!canEdit(data)) return
  editingNodeId.value = data.id
  renameDraft.value = data.label
  nextTick(() => {
    renameInputRef.value?.focus?.()
    renameInputRef.value?.select?.()
  })
}

function cancelRename() {
  editingNodeId.value = null
  renameDraft.value = ''
}

async function commitRename(data: TreeNode) {
  const newName = (renameDraft.value || '').trim()
  if (!newName) {
    ElMessage.warning('名称不能为空')
    return
  }
  if (newName === data.label) {
    cancelRename()
    return
  }
  try {
    if (data.type === 'company') {
      // 公司名存到 system_settings.system_name
      const ok = await sysConfig.saveSettings({ system_name: newName } as Record<string, unknown>) // eslint-disable-line @typescript-eslint/no-explicit-any (saveSettings 期望完整 Settings 类型)
      if (!ok) {
        ElMessage.error('公司名保存失败')
        return
      }
      ElMessage.success('公司名已更新')
    } else if (data.type === 'dept') {
      const id = Number(String(data.id).replace('d-', ''))
      const raw = (data.raw || {}) as DepartmentItem
      await put(`/employees/departments/${id}`, {
        name: newName,
        parent_id: raw.parent_id || null,
        manager_id: raw.manager_id || null,
        sort_order: raw.sort_order || 0,
      })
      ElMessage.success('部门已重命名')
    } else if (data.type === 'position') {
      const id = Number(String(data.id).replace('p-', ''))
      const raw = (data.raw || {}) as PositionItem
      await put(`/employees/positions/${id}`, {
        name: newName,
        department_id: raw.department_id,
        level: raw.level || 'P5',
        description: raw.description || null,
        sort_order: raw.sort_order || 0,
      })
      ElMessage.success('岗位已重命名')
    }
    cancelRename()
    emit('refresh')
  } catch (e: unknown) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '重命名失败')
  }
}

onMounted(() => {
  rebuildTree()
  window.addEventListener('keydown', onEsc)
})
onUnmounted(() => {
  window.removeEventListener('keydown', onEsc)
  document.removeEventListener('click', hideCtxMenu)
})
function onEsc(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    cancelRename()
    hideCtxMenu()
  }
}

defineExpose({ refresh: rebuildTree })
</script>

<style lang="scss" scoped>
.dept-tree {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: #fff;
  border-radius: 8px;
  padding: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}
.tree-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  .title {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
  }
}
.tree-search {
  margin-bottom: 8px;
}
.tree-wrap {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
.cat-tree { padding: 4px 0; }
.cat-tree :deep(.el-tree-node__content) { height: 36px; }

.tree-node {
  display: flex;
  align-items: center;
  width: 100%;
  padding-right: 4px;
}
.tree-node__label {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  min-width: 0;
}
.tree-name {
  font-weight: 500;
  color: #303133;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 120px;
}
.tree-count {
  font-weight: 600;
  font-size: 11px;
  height: 18px;
  padding: 0 4px;
  font-variant-numeric: tabular-nums;
}
.tree-node__edit {
  cursor: pointer;
  padding: 2px;
  border-radius: 3px;
  opacity: 0;
  transition: opacity 0.15s ease, background 0.15s ease;
  flex-shrink: 0;
}
.tree-node:hover .tree-node__edit { opacity: 1; }
.tree-node__edit:hover {
  background: rgba(12, 68, 124, 0.08);
  color: #0C447C !important;
}
.tree-node__edit-inline {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
}
.tree-node__edit-inline :deep(.el-input__wrapper) {
  padding: 1px 8px;
  box-shadow: 0 0 0 1px #0C447C inset !important;
}

.ctx-menu {
  position: fixed;
  z-index: 3000;
  list-style: none;
  margin: 0;
  padding: 4px 0;
  min-width: 160px;
  background: #fff;
  border-radius: 6px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
  border: 1px solid #ebeef5;
  &__item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    font-size: 13px;
    color: #303133;
    cursor: pointer;
    transition: background 0.2s;
    &:hover { background: #f5f7fa; }
    &--danger { color: #A32D2D; }
    &--danger:hover { background: #fef0f0; }
    &.is-disabled {
      color: #c0c4cc;
      cursor: not-allowed;
      &:hover { background: transparent; }
    }
  }
  &__hint { font-size: 11px; color: #c0c4cc; margin-left: 4px; }
}
</style>
