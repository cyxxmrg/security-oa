<template>
  <div class="category-tree">
    <div class="tree-toolbar">
      <span class="title">分类</span>
      <el-button link type="primary" :icon="Plus" size="small" @click="handleAddRoot">新建</el-button>
    </div>

    <el-input
      v-model="filterText"
      size="small"
      placeholder="搜索分类"
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
        @node-contextmenu="onContextMenu"
      >
        <template #default="{ node, data }">
          <div class="tree-node" @dblclick.stop="openRename(data)">
            <span v-if="editingNodeId !== data.id" class="tree-node__label">
              <el-icon :color="data.parent_id ? '#909399' : '#0C447C'" :size="14">
                <component :is="data.parent_id ? Document : FolderOpened" />
              </el-icon>
              <span class="tree-name">{{ node.label }}</span>
              <el-tag v-if="data.code" size="small" type="info" class="tree-code">{{ data.code }}</el-tag>
              <el-tag
                v-if="(data.count || data.items_count || 0) > 0"
                size="small"
                :type="(data.low_stock_count || 0) > 0 ? 'danger' : 'success'"
                class="tree-count"
              >
                {{ data.count || data.items_count }} 件
                <span v-if="(data.low_stock_count || 0) > 0">⚠️</span>
              </el-tag>
              <el-icon
                class="tree-node__edit"
                :size="12"
                color="#909399"
                @click.stop="openRename(data)"
                title="编辑名称"
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
      <el-empty v-else-if="!loading" :image-size="60" description="暂无分类" />
    </div>

    <!-- 右键菜单 (全局 fixed) -->
    <ul
      v-show="ctxMenu.visible"
      class="ctx-menu"
      :style="{ left: ctxMenu.left + 'px', top: ctxMenu.top + 'px' }"
      @click.stop
    >
      <li class="ctx-menu__item" @click="handleCtx('addChild')">
        <el-icon><Plus /></el-icon>新建子分类
      </li>
      <li class="ctx-menu__item" @click="handleCtx('rename')">
        <el-icon><Edit /></el-icon>重命名
      </li>
      <li
        class="ctx-menu__item ctx-menu__item--danger"
        :class="{ 'is-disabled': (ctxMenu.data?.items_count ?? 0) > 0 || ctxMenu.data?.has_children }"
        @click="handleCtx('delete')"
      >
        <el-icon><Delete /></el-icon>删除
        <span v-if="ctxMenu.data && (ctxMenu.data.items_count ?? 0) > 0" class="ctx-menu__hint">(有 {{ ctxMenu.data.items_count }} 件物品)</span>
        <span v-else-if="ctxMenu.data?.has_children" class="ctx-menu__hint">(有子分类)</span>
      </li>
    </ul>

    <!-- 新增/编辑 分类对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingId ? '编辑分类' : (creatingChild ? '新建子分类' : '新建分类')"
      width="500px"
      :close-on-click-modal="false"
      destroy-on-close
      @close="resetForm"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="父级分类">
        <el-tree-select
          v-model="form.parent_id"
          :data="parentTreeOptions"
          :props="{ value: 'id', label: 'name', children: 'children' }"
          node-key="id"
            check-strictly
            clearable
            placeholder="不选则为顶级分类"
            style="width: 100%"
            :render-after-expand="false"
            default-expand-all
            :disabled="creatingChild"
          />
        </el-form-item>
        <el-form-item label="分类名称" prop="name">
          <el-input v-model="form.name" placeholder="例如 监控设备" maxlength="100" show-word-limit />
        </el-form-item>
        <el-form-item label="分类编码">
          <el-input v-model="form.code" placeholder="可选, 例如 CAT-MON" maxlength="50" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" :step="10" style="width: 200px" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Edit, Delete, Search, FolderOpened, Document } from '@element-plus/icons-vue'
import {
  createInventoryCategory,
  updateInventoryCategory,
  deleteInventoryCategory,
} from '@/api/modules'
import { inventory } from '@/api/modules'
import { unwrapList } from '@/utils/response'

// 分类树节点
interface CategoryNode {
  id: number
  name: string
  code?: string
  parent_id?: number | null
  children?: CategoryNode[]
  children_list?: CategoryNode[]
  count?: number
  items_count?: number
  low_stock_count?: number
  has_children?: boolean
  sort_order?: number
  description?: string
  [key: string]: unknown
}

// 分类表单
interface CategoryForm {
  parent_id: number | null
  name: string
  code: string | null
  sort_order: number
  description: string | null
}

// API 错误
interface ApiError {
  message?: string
  response?: { data?: { message?: string } }
}

const props = defineProps<{
  modelValue: number | null
  warehouseId?: number | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: number | null): void
  (e: 'refresh', payload: { items: CategoryNode[]; lowStock: number }): void
}>()

const treeRef = ref<{
  filter: (val: string) => void
  setCurrentKey: (key: number | null) => void
} | null>(null)
const renameInputRef = ref<{
  focus: () => void
  select: () => void
} | null>(null)
const loading = ref(false)
const submitting = ref(false)
const treeRoots = ref<CategoryNode[]>([])
const filterText = ref('')

// 内联重命名状态 (双击/编辑图标进入)
const editingNodeId = ref<number | null>(null)
const renameDraft = ref('')

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const creatingChild = ref(false)
import type { FormInstance } from 'element-plus'
const formRef = ref<FormInstance | null>(null)
const form = reactive({
  parent_id: null as number | null,
  name: '',
  code: '',
  sort_order: 0,
  description: '',
})
const rules = {
  name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }],
}

const treeProps = { value: 'id', label: 'name', children: 'children' }

// 右键菜单状态
const ctxMenu = reactive({
  visible: false,
  left: 0,
  top: 0,
  data: null as CategoryNode | null,
})

// 父级选择项 (排除自己和自己的子孙)
const parentTreeOptions = computed(() => {
  if (!editingId.value) return treeRoots.value
  return filterOutSelf(treeRoots.value, editingId.value)
})

function filterOutSelf(nodes: CategoryNode[], selfId: number): CategoryNode[] {
  return nodes
    .filter(n => n.id !== selfId)
    .map(n => ({ ...n, children: filterOutSelf(n.children || [], selfId) }))
}

watch(filterText, (val) => treeRef.value?.filter(val))

// V1.2.14p: 仓库筛选变化时重新加载分类树
watch(() => props.warehouseId, () => { loadTree() })

// 弹窗打开时确保 parent_id 状态正确
watch(dialogVisible, (v) => {
  if (v && creatingChild.value && ctxMenu.data) {
    form.parent_id = ctxMenu.data.id
  }
})

async function loadTree() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {}
    if (props.warehouseId) params.warehouse_id = props.warehouseId
    const res = await inventory.treeWithCounts(params)
    const data = unwrapList(res) as CategoryNode[]

    // 兼容后端 children / children_list 两种字段
    const kids = (n: CategoryNode) => n.children || n.children_list || []
    const walk = (nodes: CategoryNode[]) => {
      for (const n of nodes) {
        n.has_children = kids(n).length > 0
        n.low_stock_count = n.low_stock_count || 0
        n.items_count = n.items_count || n.count || 0
        n.count = n.count || n.items_count || 0
        const c = kids(n)
        if (c.length) walk(c)
      }
    }
    walk(data)
    treeRoots.value = data

    // 递归累加总数, 抛给父组件
    const flat = flatten(data)
    const totalItems = flat.reduce((s: number, c: CategoryNode) => s + (c.count || c.items_count || 0), 0)
    const totalLow = flat.reduce((s: number, c: CategoryNode) => s + (c.low_stock_count || 0), 0)
    emit('refresh', { items: flat, lowStock: totalLow })
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '加载分类失败')
  } finally {
    loading.value = false
  }
}

function flatten(nodes: CategoryNode[]): CategoryNode[] {
  const out: CategoryNode[] = []
  for (const n of nodes) {
    out.push(n)
    const c = n.children || n.children_list || []
    if (c.length) out.push(...flatten(c))
  }
  return out
}

function filterNode(value: string, data: CategoryNode) {
  if (!value) return true
  return data.name?.includes(value) || data.code?.includes(value)
}

function onNodeClick(data: CategoryNode) {
  emit('update:modelValue', data.id)
}

function onContextMenu(event: MouseEvent, data: CategoryNode) {
  event.preventDefault()
  event.stopPropagation()
  ctxMenu.visible = true
  ctxMenu.left = event.clientX
  ctxMenu.top = event.clientY
  ctxMenu.data = data
  // 同时切换为高亮节点
  treeRef.value?.setCurrentKey(data.id)
  // 隐藏浏览器原生菜单
  document.addEventListener('click', hideCtxMenu, { once: true })
}

function hideCtxMenu() {
  ctxMenu.visible = false
  ctxMenu.data = null
}

function handleAddRoot() {
  editingId.value = null
  creatingChild.value = false
  resetForm()
  dialogVisible.value = true
}

function handleCtx(action: 'addChild' | 'rename' | 'delete') {
  hideCtxMenu()
  if (!ctxMenu.data) return
  if (action === 'addChild') {
    openAddChild(ctxMenu.data)
  } else if (action === 'rename') {
    openRename(ctxMenu.data)
  } else if (action === 'delete') {
    doDelete(ctxMenu.data)
  }
}

function openAddChild(data: CategoryNode) {
  editingId.value = null
  creatingChild.value = true
  resetForm()
  form.parent_id = data.id
  dialogVisible.value = true
}

function openRename(data: CategoryNode) {
  // 内联编辑模式: 直接在树节点上改
  editingNodeId.value = data.id
  renameDraft.value = data.name
  nextTick(() => {
    renameInputRef.value?.focus()
    renameInputRef.value?.select?.()
  })
}

function cancelRename() {
  editingNodeId.value = null
  renameDraft.value = ''
}

async function commitRename(data: CategoryNode) {
  const newName = (renameDraft.value || '').trim()
  if (!newName) {
    ElMessage.warning('分类名称不能为空')
    return
  }
  if (newName === data.name) {
    cancelRename()
    return
  }
  try {
    await updateInventoryCategory(data.id, {
      parent_id: data.parent_id || null,
      name: newName,
      code: data.code || null,
      sort_order: data.sort_order || 0,
      description: data.description || null,
    })
    ElMessage.success('已重命名')
    cancelRename()
    await loadTree()
  } catch (e: unknown) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '重命名失败')
  }
}

function resetForm() {
  form.parent_id = null
  form.name = ''
  form.code = ''
  form.sort_order = 0
  form.description = ''
  nextTick(() => formRef.value?.clearValidate())
}

async function doDelete(data: CategoryNode) {
  if ((data.items_count ?? 0) > 0) {
    ElMessage.warning(`分类「${data.name}」下还有 ${data.items_count ?? 0} 件物品, 不能删除`)
    return
  }
  if (data.has_children) {
    ElMessage.warning(`分类「${data.name}」下还有子分类, 不能删除`)
    return
  }
  try {
    await ElMessageBox.confirm(`确定删除分类「${data.name}」?`, '删除确认', { type: 'error' })
    await deleteInventoryCategory(data.id)
    ElMessage.success('已删除')
    // 如果删的是当前选中的, 切到 null
    if (props.modelValue === data.id) emit('update:modelValue', null)
    await loadTree()
  } catch (e: unknown) {
    if (e === 'cancel' || (e instanceof Error && e.message === 'cancel')) return
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '删除失败')
  }
}

async function handleSubmit() {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  submitting.value = true
  // parent_id 必须是有效数字, 否则后端存为 null (顶级分类)
  const parentId: number | null = (form.parent_id !== null && form.parent_id !== undefined)
    ? Number(form.parent_id)
    : null
  const payload: CategoryForm = {
    parent_id: Number.isFinite(parentId) ? parentId : null,
    name: form.name,
    code: form.code || null,
    sort_order: form.sort_order,
    description: form.description || null,
  }
  try {
    if (editingId.value) {
      await updateInventoryCategory(editingId.value, payload)
      ElMessage.success('已更新')
    } else {
      await createInventoryCategory(payload)
      ElMessage.success('分类已添加')
    }
    dialogVisible.value = false
    await loadTree()
  } catch (e: unknown) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '保存失败')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadTree()
  // ESC 关闭右键菜单
  window.addEventListener('keydown', onEsc)
})
onUnmounted(() => {
  window.removeEventListener('keydown', onEsc)
  document.removeEventListener('click', hideCtxMenu)
})
function onEsc(e: KeyboardEvent) {
  if (e.key === 'Escape') hideCtxMenu()
}

defineExpose({ refresh: loadTree })
</script>

<style lang="scss" scoped>
.category-tree {
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
.cat-tree {
  padding: 4px 0;
}
.cat-tree :deep(.el-tree-node__content) {
  height: 36px;
}
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
  max-width: 100px;
}
.tree-code {
  font-family: monospace;
  font-size: 11px;
  height: 18px;
  padding: 0 4px;
}
.tree-count {
  font-weight: 600;
  font-size: 11px;
  height: 18px;
  padding: 0 4px;
}
.tree-node__edit {
  cursor: pointer;
  padding: 2px;
  border-radius: 3px;
  opacity: 0;
  transition: opacity 0.15s ease, background 0.15s ease;
  flex-shrink: 0;
}
.tree-node:hover .tree-node__edit {
  opacity: 1;
}
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

// 右键菜单
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
